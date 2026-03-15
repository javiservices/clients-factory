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
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class ApiCardController extends AbstractController
{
    private $cache;
    private const CACHE_KEY = 'last_card_id';
    private const CACHE_USED_KEY = 'card_id_used';
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->cache = new FilesystemAdapter();
        $this->entityManager = $entityManager;
    }

    #[Route('/check-card', name: 'check_card', methods: ['GET'])]
    public function checkCard(Request $request, ClientRepository $clientRepository): JsonResponse
    {
        $cardId = $request->query->get('card_id');
    
        if (!$cardId) {
            return $this->json([
                'success' => false,
                'message' => 'No se proporcionó ID de tarjeta'
            ], 400);
        }
    
        $client = $clientRepository->findOneBy(['card_code' => $cardId]);
    
        if (!$client) {
            return $this->json([
                'success' => false,
                'message' => 'Tarjeta no autorizada'
            ], 404);
        }
    
        $subscriptions = $client->getSubscriptions();
        $activeSubscription = null;
        
        // Buscamos la suscripción activa más reciente
        foreach ($subscriptions as $subscription) {
            if ($subscription->isActive()) {
                $activeSubscription = $subscription;
                break;
            }
        }
    
        if (!$activeSubscription) {
            // Registrar acceso denegado
            $this->logAccess($client, 'denied');
            
            return $this->json([
                'success' => false,
                'message' => 'Cliente sin suscripción activa',
                'client' => [
                    'id' => $client->getId(),
                    'name' => $client->getName()
                ]
            ], 403);
        }
    
        // Verificar si es un pase diario
        if ($activeSubscription->getSubscriptionType()->isDailyPass()) {
            // Verificar si quedan pases
            if ($activeSubscription->getRemainingPasses() <= 0) {
                // Si no quedan pases, desactivar la suscripción
                $activeSubscription->setIsActive(false);
                $this->entityManager->flush();
    
                // Registrar acceso denegado
                $this->logAccess($client, 'denied');
                
                return $this->json([
                    'success' => false,
                    'message' => 'No quedan pases disponibles',
                    'client' => [
                        'id' => $client->getId(),
                        'name' => $client->getName()
                    ]
                ], 403);
            }
    
            // Restar un pase
            $activeSubscription->setRemainingPasses(
                $activeSubscription->getRemainingPasses() - 1
            );
    
            // Si era el último pase, desactivar la suscripción
            if ($activeSubscription->getRemainingPasses() <= 0) {
                $activeSubscription->setIsActive(false);
            }
    
            $this->entityManager->flush();
        } else {
            // Para suscripciones normales, verificar si está dentro del período válido
            $now = new \DateTime();
            if ($now > $activeSubscription->getEndDate()) {
                $activeSubscription->setIsActive(false);
                $this->entityManager->flush();
    
                // Registrar acceso denegado
                $this->logAccess($client, 'denied');
                
                return $this->json([
                    'success' => false,
                    'message' => 'Suscripción expirada',
                    'client' => [
                        'id' => $client->getId(),
                        'name' => $client->getName()
                    ]
                ], 403);
            }
        }
    
        // Registrar acceso exitoso
        $this->logAccess($client, 'success');
    
        // Preparar mensaje personalizado
        $message = $activeSubscription->getSubscriptionType()->isDailyPass() 
            ? sprintf('Tarjeta autorizada. Pases restantes: %d', $activeSubscription->getRemainingPasses())
            : 'Tarjeta autorizada';
    
        return $this->json([
            'success' => true,
            'message' => $message,
            'client' => [
                'id' => $client->getId(),
                'name' => $client->getName()
            ],
            'subscription' => [
                'type' => $activeSubscription->getSubscriptionType()->getName(),
                'remainingPasses' => $activeSubscription->getRemainingPasses(),
                'isDailyPass' => $activeSubscription->getSubscriptionType()->isDailyPass()
            ]
        ]);
    }

    private function logAccess(Client $client, string $status): void
    {
        // Solo registrar log si el cliente es facturable
        if (!$client->isBillable()) {
            return;
        }

        $log = new AccessLog();
        $log->setClient($client);
        $log->setStatus($status);
        
        // Actualizar lastAccess cuando el acceso es exitoso
        if ($status === 'success') {
            $client->setLastAccess(new \DateTime());
        }
        
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function getProcessedLogs(Request $request, ProcessedLogRepository $processedLogRepository): JsonResponse
    {
        $date = $request->query->get('date');

        if (!$date) {
            return $this->json([
                'success' => false,
                'message' => 'No se proporcionó una fecha'
            ], 400);
        }

        $processedLog = $processedLogRepository->findOneBy(['date' => new \DateTime($date)]);

        if (!$processedLog) {
            return $this->json([
                'success' => false,
                'message' => 'No hay registros procesados para la fecha proporcionada'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'logs' => $processedLog->getLogs()
        ]);
    }
    #[Route('/card-read', name: 'card_read', methods: ['POST'])]
    public function storeCardId(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['card_id'])) {
            $cacheItem = $this->cache->getItem(self::CACHE_KEY);
            $cacheItem->set($data['card_id']);
            $this->cache->save($cacheItem);

            return new JsonResponse([
                'success' => true,
                'message' => 'ID de tarjeta almacenado'
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'No se recibió ningún ID de tarjeta'
        ], 400);
    }

    #[Route('/card-read', name: 'card_read_get', methods: ['GET'])]
    public function getLastCardId(): JsonResponse
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);

        if ($cacheItem->isHit()) {
            return new JsonResponse([
                'success' => true,
                'card_id' => $cacheItem->get()
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'No hay tarjetas disponibles'
        ], 404);
    }

    #[Route('/card-read/clear', name: 'card_read_clear', methods: ['POST'])]
    public function clearCardId(): JsonResponse
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        
        if ($cacheItem->isHit()) {
            $cardId = $cacheItem->get();
            
            // Marcar esta tarjeta como usada
            $usedItem = $this->cache->getItem(self::CACHE_USED_KEY . $cardId);
            $usedItem->set(true);
            $usedItem->expiresAfter(300); // 5 minutos para evitar reutilización
            $this->cache->save($usedItem);
            
            // Limpiar el ID actual
            $this->cache->deleteItem(self::CACHE_KEY);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'ID de tarjeta limpiado correctamente'
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'No había ID de tarjeta para limpiar'
        ]);
    }
}