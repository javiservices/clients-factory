<?php
namespace App\Controller\Api;

use App\Entity\AccessLog;
use App\Entity\Client;
use App\Repository\AccessLogRepository;
use App\Repository\ClientRepository;
use App\Repository\ProcessedLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class ApiCardController extends AbstractController
{
    private FilesystemAdapter $cache;
    private const CACHE_KEY      = 'last_card_id';
    private const CACHE_USED_KEY = 'card_id_used';
    private const RATE_LIMIT_KEY = 'rate_limit_ip_';
    private const PASSBACK_KEY   = 'passback_card_';
    private const RATE_LIMIT_MAX = 300; // peticiones por minuto por IP
    private const PASSBACK_TTL   = 5;   // segundos mínimos entre accesos del mismo card

    // Coordenadas oficiales y geofence de Santamaría Gym (Logroño)
    public const GYM_LATITUDE            = 42.465168341854756;
    public const GYM_LONGITUDE           = -2.469648710013292;
    public const GYM_MAX_DISTANCE_METERS = 75.0; // Radio máximo permitido en metros
    private const REMOTE_PASSBACK_KEY    = 'remote_passback_client_';
    private const REMOTE_PASSBACK_TTL    = 45;   // Cooldown de 45s para evitar doble pulsación accidental o abusiva

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->cache = new FilesystemAdapter();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers de seguridad
    // ─────────────────────────────────────────────────────────────────────────

    private function checkApiKey(Request $request): bool
    {
        $expected = $_ENV['API_KEY'] ?? 'gym-api-key-changeme';
        $provided = $request->headers->get('X-Api-Key')
            ?? $request->query->get('api_key');
        return $provided === $expected;
    }

    private function checkRateLimit(Request $request): bool
    {
        $ip  = $request->getClientIp() ?? 'unknown';
        $key = self::RATE_LIMIT_KEY . md5($ip);
        $item = $this->cache->getItem($key);
        $count = $item->isHit() ? (int) $item->get() : 0;

        if ($count >= self::RATE_LIMIT_MAX) {
            return false;
        }

        $item->set($count + 1);
        $item->expiresAfter(60);
        $this->cache->save($item);
        return true;
    }

    /** Anti-passback: false si la misma tarjeta accedió hace menos de PASSBACK_TTL segundos */
    private function checkAntiPassback(string $cardId): bool
    {
        $key  = self::PASSBACK_KEY . md5($cardId);
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            return false;
        }

        $item->set(true);
        $item->expiresAfter(self::PASSBACK_TTL);
        $this->cache->save($item);
        return true;
    }

    /**
     * Calcula la distancia en metros entre dos coordenadas geográficas mediante la fórmula de Haversine.
     */
    private function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0; // Radio medio de la Tierra en metros
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Verificación de tarjeta – endpoint principal Arduino
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/check-card', name: 'check_card', methods: ['GET'])]
    public function checkCard(
        Request $request,
        ClientRepository $clientRepository,
        AccessLogRepository $accessLogRepo
    ): JsonResponse
    {
        if (!$this->checkApiKey($request)) {
            return $this->json(['success' => false, 'message' => 'Clave de API inválida'], 401);
        }

        if (!$this->checkRateLimit($request)) {
            return $this->json(['success' => false, 'message' => 'Demasiadas peticiones, espera un momento'], 429);
        }

        $cardId = $request->query->get('card_id');

        if (!$cardId) {
            return $this->json(['success' => false, 'message' => 'No se proporcionó ID de tarjeta'], 400);
        }

        // Resolución de QR dinámico antifraude (rotación cada 30s)
        $resolvedCardId = $this->resolveCardCode($cardId);
        if ($resolvedCardId === null) {
            return $this->json(['success' => false, 'message' => 'Código QR dinámico caducado o inválido. Abre tu app SantaDoor de nuevo.'], 403);
        }
        $cardId = $resolvedCardId;

        if (!$this->checkAntiPassback($cardId)) {
            return $this->json(['success' => false, 'message' => 'Acceso muy frecuente, espera unos segundos'], 429);
        }

        $client = $clientRepository->findOneByCardCode($cardId);

        if (!$client) {
            return $this->json(['success' => false, 'message' => 'Tarjeta no autorizada'], 404);
        }

        $activeSubscription = null;
        foreach ($client->getSubscriptions() as $subscription) {
            if ($subscription->isActive()) {
                $activeSubscription = $subscription;
                break;
            }
        }

        if (!$activeSubscription) {
            $this->logAccess($client, 'denied');
            return $this->json([
                'success' => false,
                'message' => 'Cliente sin suscripción activa',
                'client'  => ['id' => $client->getId(), 'name' => $client->getName()],
            ], 403);
        }

        if ($activeSubscription->isFrozen()) {
            $this->logAccess($client, 'denied');
            return $this->json([
                'success' => false,
                'message' => 'Suscripción congelada temporalmente',
                'client'  => ['id' => $client->getId(), 'name' => $client->getName()],
            ], 403);
        }

        if ($activeSubscription->getSubscriptionType()->isDailyPass()) {
            if ($activeSubscription->getRemainingPasses() <= 0) {
                $activeSubscription->setIsActive(false);
                $this->entityManager->flush();
                $this->logAccess($client, 'denied');
                return $this->json([
                    'success' => false,
                    'message' => 'No quedan pases disponibles',
                    'client'  => ['id' => $client->getId(), 'name' => $client->getName()],
                ], 403);
            }
            $activeSubscription->setRemainingPasses($activeSubscription->getRemainingPasses() - 1);
            if ($activeSubscription->getRemainingPasses() <= 0) {
                $activeSubscription->setIsActive(false);
            }
            $this->entityManager->flush();
        } else {
            $now = new \DateTime();
            if ($activeSubscription->getEndDate() !== null && $now > $activeSubscription->getEndDate()) {
                $activeSubscription->setIsActive(false);
                $this->entityManager->flush();
                $this->logAccess($client, 'denied');
                return $this->json([
                    'success' => false,
                    'message' => 'Suscripción expirada',
                    'client'  => ['id' => $client->getId(), 'name' => $client->getName()],
                ], 403);
            }
        }

        // ─── Validación de Límite Diario de Accesos ───
        $dailyLimit = $client->getDailyAccessLimit();
        if ($dailyLimit > 0) {
            $todayAccessCount = $accessLogRepo->countTodaySuccessByClient($client);
            if ($todayAccessCount >= $dailyLimit) {
                $this->logAccess($client, 'denied');
                return $this->json([
                    'success' => false,
                    'message' => sprintf('Límite de accesos diarios alcanzado (%d/%d hoy). Consulta en recepción.', $todayAccessCount, $dailyLimit),
                    'code'    => 'DAILY_LIMIT_EXCEEDED',
                    'client'  => [
                        'id'             => $client->getEffectiveClientNumber(),
                        'rawId'          => $client->getId(),
                        'clientNumber'   => $client->getEffectiveClientNumber(),
                        'name'           => $client->getName(),
                        'surname'        => $client->getSurname(),
                    ],
                ], 403);
            }
        }

        $this->logAccess($client, 'success');

        $warning = false;
        $warningMessage = null;
        $daysRemaining = null;

        if ($activeSubscription->getSubscriptionType()->isDailyPass()) {
            $remPasses = $activeSubscription->getRemainingPasses();
            if ($remPasses <= 2) {
                $warning = true;
                $warningMessage = ($remPasses === 1)
                    ? 'AVISO: ¡Te queda solo 1 pase disponible!'
                    : sprintf('AVISO: Te quedan %d pases disponibles', $remPasses);
            }
        } else {
            $endDate = $activeSubscription->getEndDate();
            if ($endDate) {
                $today = new \DateTime('today');
                $endDay = (clone $endDate)->setTime(0, 0, 0);
                $diff = (int) $today->diff($endDay)->format('%r%a');
                $daysRemaining = max(0, $diff);

                if ($diff <= 3 && $diff >= 0) {
                    $warning = true;
                    if ($diff === 0) {
                        $warningMessage = 'AVISO: Tu cuota vence HOY. Recuerda renovarla.';
                    } elseif ($diff === 1) {
                        $warningMessage = 'AVISO: Tu cuota vence MAÑANA. Recuerda renovarla.';
                    } else {
                        $warningMessage = sprintf('AVISO: Tu cuota vence en %d días. Recuerda renovarla.', $diff);
                    }
                }
            }
        }

        $welcomeCustom = $client->getWelcomeMessage();
        if ($welcomeCustom && trim($welcomeCustom) !== '') {
            $message = trim($welcomeCustom);
        } else {
            $message = $activeSubscription->getSubscriptionType()->isDailyPass()
                ? sprintf('Bienvenido/a, %s. Pases restantes: %d', $client->getName(), $activeSubscription->getRemainingPasses())
                : sprintf('Bienvenido/a, %s', $client->getName());
        }

        $photoUrl = $client->getPhoto() ? '/uploads/clients/' . $client->getPhoto() : null;

        return $this->json([
            'success'         => true,
            'message'         => $message,
            'welcome_message' => $welcomeCustom,
            'warning'         => $warning,
            'warning_message' => $warningMessage,
            'days_remaining'  => $daysRemaining,
            'client'          => [
                'id'             => $client->getEffectiveClientNumber(),
                'rawId'          => $client->getId(),
                'clientNumber'   => $client->getEffectiveClientNumber(),
                'name'           => $client->getName(),
                'surname'        => $client->getSurname(),
                'photoUrl'       => $photoUrl,
                'welcomeMessage' => $welcomeCustom,
                'dailyAccessLimit' => $dailyLimit,
                'entriesToday'     => $todayAccessCount + 1,
            ],
            'subscription' => [
                'type'            => $activeSubscription->getSubscriptionType()->getName(),
                'remainingPasses' => $activeSubscription->getRemainingPasses(),
                'isDailyPass'     => $activeSubscription->getSubscriptionType()->isDailyPass(),
                'endDate'         => $activeSubscription->getEndDate()?->format('d/m/Y') ?: 'Ilimitado',
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Verificación de credenciales de socio (Login SantaDoor: DNI + Código)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/client/verify-login', name: 'client_verify_login', methods: ['POST'])]
    public function verifyLogin(
        Request $request,
        ClientRepository $clientRepository,
        AccessLogRepository $accessLogRepo
    ): JsonResponse {
        $data = [];
        if ($request->getContent()) {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        $dni  = strtoupper(trim($data['dni'] ?? $request->request->get('dni') ?? ''));
        $code = strtoupper(trim($data['code'] ?? $request->request->get('code') ?? ''));

        if (!$dni || !$code) {
            return $this->json(['success' => false, 'message' => 'Por favor introduce tu DNI y tu código de socio.'], 400);
        }

        $matchedClient = null;
        foreach ($clientRepository->findAll() as $client) {
            $clientDni = strtoupper(trim((string)$client->getIdNumber()));
            if ($clientDni === $dni) {
                $clientCard   = strtoupper(trim((string)$client->getCardCode()));
                $clientId     = (string)$client->getId();
                $clientNumber = (string)$client->getEffectiveClientNumber();
                if ($code === $clientId || $code === $clientNumber || $code === $clientCard) {
                    $matchedClient = $client;
                    break;
                }
            }
        }

        if (!$matchedClient) {
            return $this->json(['success' => false, 'message' => 'Datos incorrectos. Verifica tu DNI y código de socio.'], 401);
        }

        $activeSub = null;
        foreach ($matchedClient->getSubscriptions() as $sub) {
            if ($sub->isActive()) {
                $activeSub = $sub;
                break;
            }
        }

        $daysRemaining = null;
        $warningMessage = null;
        if ($activeSub) {
            $endDate = $activeSub->getEndDate();
            if ($endDate) {
                $today = new \DateTime('today');
                $endDay = (clone $endDate)->setTime(0, 0, 0);
                $diff = (int) $today->diff($endDay)->format('%r%a');
                $daysRemaining = max(0, $diff);
                if ($diff <= 3 && $diff >= 0) {
                    $warningMessage = ($diff === 0) ? 'Tu cuota vence HOY' : "Tu cuota vence en $diff días";
                }
            }
        }

        return $this->json([
            'success' => true,
            'message' => 'Login correcto',
            'client'  => [
                'id'           => $matchedClient->getEffectiveClientNumber(),
                'rawId'        => $matchedClient->getId(),
                'clientNumber' => $matchedClient->getEffectiveClientNumber(),
                'name'         => $matchedClient->getName(),
                'surname'      => $matchedClient->getSurname(),
                'dni'          => $matchedClient->getIdNumber(),
                'cardCode'     => $matchedClient->getCardCode() ?: (string)$matchedClient->getEffectiveClientNumber(),
                'dailyAccessLimit' => $matchedClient->getDailyAccessLimit(),
                'entriesToday'     => $accessLogRepo->countTodaySuccessByClient($matchedClient),
            ],
            'subscription' => [
                'active'          => $activeSub !== null,
                'type'            => $activeSub?->getSubscriptionType()?->getName() ?: 'Sin cuota activa',
                'endDate'         => $activeSub?->getEndDate()?->format('d/m/Y') ?: ($activeSub ? 'Ilimitado' : null),
                'daysRemaining'   => $daysRemaining,
                'warningMessage'  => $warningMessage,
                'isDailyPass'     => $activeSub?->getSubscriptionType()?->isDailyPass() ?? false,
                'remainingPasses' => $activeSub?->getRemainingPasses(),
            ],
            'occupancy' => $accessLogRepo->getOccupancyDetails(80, 45),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Consulta de Aforo en Tiempo Real para Apps (PWA y Android)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/gym/occupancy', name: 'gym_occupancy', methods: ['GET'])]
    public function getGymOccupancy(AccessLogRepository $accessLogRepo): JsonResponse
    {
        $details = $accessLogRepo->getOccupancyDetails(80, 45);
        return $this->json(array_merge(['success' => true], $details));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Apertura Remota desde App Móvil (SantaDoor)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/door/open', name: 'door_open', methods: ['POST', 'GET'])]
    public function openDoor(
        Request $request,
        ClientRepository $clientRepository,
        AccessLogRepository $accessLogRepo
    ): JsonResponse
    {
        if (!$this->checkApiKey($request)) {
            return $this->json(['success' => false, 'message' => 'Clave de API inválida'], 401);
        }

        $data = [];
        if ($request->getContent()) {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        $cardId = $data['card_id']
            ?? $request->request->get('card_id')
            ?? $request->query->get('card_id');

        if (!$cardId) {
            return $this->json(['success' => false, 'message' => 'No se proporcionó ID de tarjeta o socio'], 400);
        }

        $resolvedCardId = $this->resolveCardCode($cardId);
        if ($resolvedCardId === null) {
            return $this->json(['success' => false, 'message' => 'Código QR dinámico caducado o inválido'], 403);
        }
        $cardId = $resolvedCardId;

        $client = $clientRepository->findOneByCardCode($cardId);
        if (!$client) {
            return $this->json(['success' => false, 'message' => 'Socio no encontrado'], 404);
        }

        $activeSubscription = null;
        foreach ($client->getSubscriptions() as $subscription) {
            if ($subscription->isActive()) {
                $activeSubscription = $subscription;
                break;
            }
        }

        if (!$activeSubscription) {
            $this->logAccess($client, 'denied');
            return $this->json([
                'success' => false,
                'message' => 'Cliente sin suscripción activa',
                'client'  => ['id' => $client->getId(), 'name' => $client->getName()],
            ], 403);
        }

        if ($activeSubscription->isFrozen()) {
            $this->logAccess($client, 'denied');
            return $this->json([
                'success' => false,
                'message' => 'Suscripción congelada temporalmente',
                'client'  => ['id' => $client->getId(), 'name' => $client->getName()],
            ], 403);
        }

        if ($activeSubscription->getEndDate() !== null && new \DateTime() > $activeSubscription->getEndDate()) {
            $activeSubscription->setIsActive(false);
            $this->entityManager->flush();
            $this->logAccess($client, 'denied');
            return $this->json([
                'success' => false,
                'message' => 'Suscripción expirada',
                'client'  => ['id' => $client->getId(), 'name' => $client->getName()],
            ], 403);
        }

        // ─── 1. Validación de Geolocalización (Geofence Santamaría Gym) ───
        $lat = $data['latitude'] ?? $request->request->get('latitude') ?? $request->query->get('latitude');
        $lng = $data['longitude'] ?? $request->request->get('longitude') ?? $request->query->get('longitude');

        if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
            return $this->json([
                'success' => false,
                'message' => 'Es necesario activar y permitir la ubicación GPS para abrir el torno.',
                'code'    => 'LOCATION_REQUIRED',
            ], 403);
        }

        $distanceMeters = $this->calculateDistanceMeters((float)$lat, (float)$lng, self::GYM_LATITUDE, self::GYM_LONGITUDE);

        if ($distanceMeters > self::GYM_MAX_DISTANCE_METERS) {
            return $this->json([
                'success'         => false,
                'message'         => sprintf('Estás demasiado lejos del gimnasio (%d metros). Debes estar en las inmediaciones para abrir el torno.', (int) round($distanceMeters)),
                'code'            => 'OUT_OF_RANGE',
                'distance_meters' => (int) round($distanceMeters),
                'max_radius'      => self::GYM_MAX_DISTANCE_METERS,
            ], 403);
        }

        // ─── 2. Anti-passback por tiempo de apertura remota (Cooldown) ───
        $passbackKey = self::REMOTE_PASSBACK_KEY . $client->getId();
        $passbackItem = $this->cache->getItem($passbackKey);
        if ($passbackItem->isHit()) {
            return $this->json([
                'success' => false,
                'message' => 'Acabas de abrir el torno recientemente. Espera unos momentos antes de volver a solicitarlo.',
                'code'    => 'COOLDOWN_ACTIVE',
            ], 429);
        }

        // ─── 3. Validación de Límite Diario de Accesos ───
        $dailyLimit = $client->getDailyAccessLimit();
        if ($dailyLimit > 0) {
            $todayAccessCount = $accessLogRepo->countTodaySuccessByClient($client);
            if ($todayAccessCount >= $dailyLimit) {
                $this->logAccess($client, 'denied');
                return $this->json([
                    'success' => false,
                    'message' => sprintf('Has alcanzado tu límite de accesos diarios (%d/%d hoy). Consulta en recepción si necesitas más accesos.', $todayAccessCount, $dailyLimit),
                    'code'    => 'DAILY_LIMIT_EXCEEDED',
                    'client'  => [
                        'id'             => $client->getEffectiveClientNumber(),
                        'rawId'          => $client->getId(),
                        'clientNumber'   => $client->getEffectiveClientNumber(),
                        'name'           => $client->getName(),
                        'surname'        => $client->getSurname(),
                    ],
                ], 403);
            }
        }

        // Registrar señal en caché para que Raspberry Pi / ESP32 accione el relé
        $item = $this->cache->getItem('remote_door_unlock_signal');
        $item->set([
            'client_id'   => $client->getId(),
            'client_name' => $client->getName() . ' ' . $client->getSurname(),
            'timestamp'   => time(),
            'source'      => $data['source'] ?? 'santadoor_remote',
            'distance_m'  => round($distanceMeters, 1),
        ]);
        $item->expiresAfter(15);
        $this->cache->save($item);

        // Guardar cooldown de 45 segundos para este socio
        $passbackItem->set(true);
        $passbackItem->expiresAfter(self::REMOTE_PASSBACK_TTL);
        $this->cache->save($passbackItem);

        $this->logAccess($client, 'success');

        $welcomeCustom = $client->getWelcomeMessage();
        $message = ($welcomeCustom && trim($welcomeCustom) !== '')
            ? trim($welcomeCustom)
            : '¡Apertura autorizada! Torno activado.';

        return $this->json([
            'success'         => true,
            'message'         => $message,
            'welcome_message' => $welcomeCustom,
            'client'          => [
                'id'             => $client->getEffectiveClientNumber(),
                'rawId'          => $client->getId(),
                'clientNumber'   => $client->getEffectiveClientNumber(),
                'name'           => $client->getName(),
                'surname'        => $client->getSurname(),
                'welcomeMessage' => $welcomeCustom,
            ],
            'subscription' => [
                'type' => $activeSubscription->getSubscriptionType()?->getName(),
            ],
        ]);
    }

    #[Route('/door/pending-unlock', name: 'door_pending_unlock', methods: ['GET'])]
    public function getPendingUnlock(Request $request): JsonResponse
    {
        if (!$this->checkApiKey($request)) {
            return $this->json(['success' => false, 'message' => 'Clave de API inválida'], 401);
        }

        $item = $this->cache->getItem('remote_door_unlock_signal');
        if ($item->isHit()) {
            $val = $item->get();
            $this->cache->deleteItem('remote_door_unlock_signal');
            return $this->json(['success' => true, 'unlock' => true, 'data' => $val]);
        }

        return $this->json(['success' => true, 'unlock' => false]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SSE – Server-Sent Events para lectura en tiempo real
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/card-stream', name: 'card_stream', methods: ['GET'])]
    public function cardStream(Request $request): StreamedResponse
    {
        if (!$this->checkApiKey($request)) {
            return new StreamedResponse(function () {
                echo "data: " . json_encode(['error' => 'Clave de API inválida']) . "\n\n";
                flush();
            }, 401, ['Content-Type' => 'text/event-stream']);
        }

        $cache    = $this->cache;
        $cacheKey = self::CACHE_KEY;

        $response = new StreamedResponse(function () use ($cache, $cacheKey) {
            $deadline = time() + 30;

            while (time() < $deadline) {
                $item = $cache->getItem($cacheKey);
                if ($item->isHit()) {
                    echo "event: card\n";
                    echo "data: " . json_encode(['card_id' => $item->get()]) . "\n\n";
                    ob_flush();
                    flush();
                    return;
                }
                echo ": ping\n\n";
                ob_flush();
                flush();
                if (connection_aborted()) {
                    return;
                }
                sleep(1);
            }
            echo "event: timeout\ndata: {}\n\n";
            ob_flush();
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sincronización offline: lista de tarjetas autorizadas
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/authorized-cards', name: 'authorized_cards', methods: ['GET'])]
    public function authorizedCards(Request $request, ClientRepository $clientRepository): JsonResponse
    {
        if (!$this->checkApiKey($request)) {
            return $this->json(['success' => false, 'message' => 'Clave de API inválida'], 401);
        }

        $cards = [];
        $today = new \DateTime('today');
        foreach ($clientRepository->findAll() as $client) {
            if (!$client->getCardCode()) {
                continue;
            }
            $activeSub = null;
            foreach ($client->getSubscriptions() as $sub) {
                if ($sub->isActive()) {
                    $activeSub = $sub;
                    break;
                }
            }
            if (!$activeSub || $activeSub->isFrozen()) {
                continue;
            }

            $isDailyPass = $activeSub->getSubscriptionType()->isDailyPass();
            $remPasses = $activeSub->getRemainingPasses();
            $endDate = $activeSub->getEndDate();

            if ($isDailyPass && $remPasses <= 0) {
                continue;
            }
            if (!$isDailyPass && $endDate && (clone $endDate)->setTime(23, 59, 59) < $today) {
                continue;
            }

            $cards[] = [
                'card_code'        => $client->getCardCode(),
                'client_id'        => $client->getId(),
                'name'             => trim($client->getName() . ' ' . $client->getSurname()),
                'end_date'         => $endDate?->format('Y-m-d'),
                'is_daily_pass'    => $isDailyPass,
                'remaining_passes' => $remPasses,
            ];
        }

        return $this->json([
            'success'   => true,
            'generated' => (new \DateTime())->format('c'),
            'total'     => count($cards),
            'cards'     => $cards,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lectura de tarjeta desde el panel admin
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/card-read', name: 'card_read', methods: ['POST'])]
    public function storeCardId(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['card_id'])) {
            $cacheItem = $this->cache->getItem(self::CACHE_KEY);
            $cacheItem->set($data['card_id']);
            $cacheItem->expiresAfter(60);
            $this->cache->save($cacheItem);
            return new JsonResponse(['success' => true, 'message' => 'ID de tarjeta almacenado']);
        }

        return new JsonResponse(['success' => false, 'message' => 'No se recibió ningún ID de tarjeta'], 400);
    }

    #[Route('/card-read', name: 'card_read_get', methods: ['GET'])]
    public function getLastCardId(): JsonResponse
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        if ($cacheItem->isHit()) {
            return new JsonResponse(['success' => true, 'card_id' => $cacheItem->get()]);
        }
        return new JsonResponse(['success' => false, 'card_id' => null, 'message' => 'No hay tarjetas disponibles'], 200);
    }

    #[Route('/card-read/clear', name: 'card_read_clear', methods: ['POST'])]
    public function clearCardId(): JsonResponse
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        if ($cacheItem->isHit()) {
            $cardId   = $cacheItem->get();
            $usedItem = $this->cache->getItem(self::CACHE_USED_KEY . $cardId);
            $usedItem->set(true);
            $usedItem->expiresAfter(300);
            $this->cache->save($usedItem);
            $this->cache->deleteItem(self::CACHE_KEY);
            return new JsonResponse(['success' => true, 'message' => 'ID de tarjeta limpiado correctamente']);
        }
        return new JsonResponse(['success' => false, 'message' => 'No había ID de tarjeta para limpiar']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Legacy: logs procesados
    // ─────────────────────────────────────────────────────────────────────────

    public function getProcessedLogs(Request $request, ProcessedLogRepository $processedLogRepository): JsonResponse
    {
        $date = $request->query->get('date');
        if (!$date) {
            return $this->json(['success' => false, 'message' => 'No se proporcionó una fecha'], 400);
        }
        $processedLog = $processedLogRepository->findOneBy(['date' => new \DateTime($date)]);
        if (!$processedLog) {
            return $this->json(['success' => false, 'message' => 'No hay registros procesados para esa fecha'], 404);
        }
        return $this->json(['success' => true, 'logs' => $processedLog->getLogs()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper privado
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveCardCode(string $rawCardId): ?string
    {
        if (str_starts_with($rawCardId, 'DYN:')) {
            $parts = explode(':', substr($rawCardId, 4));
            if (count($parts) >= 3) {
                [$realCardCode, $step, $hash] = $parts;
                $currentStep = (int) floor(time() / 30);
                $providedStep = (int) $step;
                // Tolerancia de ±1 intervalo de 30s
                if (abs($currentStep - $providedStep) > 1) {
                    return null;
                }
                $expectedHash = substr(hash('sha256', $realCardCode . ':' . $providedStep . ':SantamariaGym2026'), 0, 8);
                if (!hash_equals($expectedHash, $hash)) {
                    return null;
                }
                return $realCardCode;
            }
            return null;
        }
        return $rawCardId;
    }

    private function logAccess(Client $client, string $status): void
    {
        if (!$client->isBillable()) {
            return;
        }
        $log = new AccessLog();
        $log->setClient($client);
        $log->setStatus($status);
        if ($status === 'success') {
            $client->setLastAccess(new \DateTime());
            $client->incrementAccesses(1);
        }
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
