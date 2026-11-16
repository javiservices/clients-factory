<?php
namespace App\Controller\Api;

use App\Entity\AccessLog;
use App\Entity\Client;
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

    // ─────────────────────────────────────────────────────────────────────────
    // Verificación de tarjeta – endpoint principal Arduino
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/check-card', name: 'check_card', methods: ['GET'])]
    public function checkCard(Request $request, ClientRepository $clientRepository): JsonResponse
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
            if ($now > $activeSubscription->getEndDate()) {
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

        $message = $activeSubscription->getSubscriptionType()->isDailyPass()
            ? sprintf('Bienvenido/a, %s. Pases restantes: %d', $client->getName(), $activeSubscription->getRemainingPasses())
            : sprintf('Bienvenido/a, %s', $client->getName());

        $photoUrl = $client->getPhoto() ? '/uploads/clients/' . $client->getPhoto() : null;

        return $this->json([
            'success'         => true,
            'message'         => $message,
            'warning'         => $warning,
            'warning_message' => $warningMessage,
            'days_remaining'  => $daysRemaining,
            'client'          => [
                'id'       => $client->getId(),
                'name'     => $client->getName(),
                'surname'  => $client->getSurname(),
                'photoUrl' => $photoUrl,
            ],
            'subscription' => [
                'type'            => $activeSubscription->getSubscriptionType()->getName(),
                'remainingPasses' => $activeSubscription->getRemainingPasses(),
                'isDailyPass'     => $activeSubscription->getSubscriptionType()->isDailyPass(),
                'endDate'         => $activeSubscription->getEndDate()?->format('d/m/Y'),
            ],
        ]);
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
        return new JsonResponse(['success' => false, 'message' => 'No hay tarjetas disponibles'], 404);
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
        }
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
