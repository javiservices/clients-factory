<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Subscription;
use App\Entity\SubscriptionType;
use App\Form\ClientType;
use App\Repository\AccessLogRepository;
use App\Repository\ClientRepository;
use App\Repository\SubscriptionRepository;
use App\Service\EncryptionService;
use App\Service\ImageOptimizerService;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/client')]
final class ClientController extends AbstractController
{
    #[Route(name: 'app_client_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ClientRepository $clientRepository,
        EntityManagerInterface $em,
        EncryptionService $encryptionService
    ): Response {
        $session = $request->getSession();
        $systemMode = $session->get('system_mode');

        // Filtros
        $search = trim((string) $request->query->get('search', ''));
        $paid = $request->query->get('paid', null);
        $perPage = (int) $request->query->get('perPage', $session->get('perPage', 12));
        if ($perPage < 1) {
            $perPage = 12;
        }
        $page = max(1, (int) $request->query->get('page', 1));

        if ($request->query->has('perPage')) {
            $session->set('perPage', $perPage);
        }

        $startOfMonth = new \DateTime('first day of this month 00:00:00');
        $currentDate = new \DateTime();

        // 1. Si hay búsqueda (texto o numérico), filtrar IDs con scan rápido de SQL + descifrado en memoria (~60ms)
        $matchedIds = null;
        $exactId = null;
        if ($search !== '') {
            $cleanNumeric = ltrim($search, '#');
            if (ctype_digit($cleanNumeric)) {
                $exactId = (int) $cleanNumeric;
            }

            // Normalizar texto de búsqueda
            $normalizedSearch = $this->normalizeSearchText($search);
            $tokens = preg_split('/[\s,]+/', $normalizedSearch, -1, PREG_SPLIT_NO_EMPTY);

            $conn = $em->getConnection();
            $rows = $conn->fetchAllAssociative('SELECT id, client_number, name, surname, id_number, phone, email FROM client');
            $matchedIds = [];

            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $clientNumber = isset($row['client_number']) && $row['client_number'] !== null ? (int) $row['client_number'] : $id;

                // Si coincide exactamente con el ID numérico o número de socio buscado, incluirlo prioritariamente
                if ($exactId !== null && ($id === $exactId || $clientNumber === $exactId)) {
                    $matchedIds[] = $id;
                    continue;
                }

                $name = (string) EncryptionService::decryptValue($row['name']);
                $surname = (string) EncryptionService::decryptValue($row['surname']);
                $dni = (string) EncryptionService::decryptValue($row['id_number']);
                $cleanDni = str_replace(['.', '-', ' '], '', $dni);
                $phone = (string) EncryptionService::decryptValue($row['phone']);
                $cleanPhone = str_replace(['.', '-', ' ', '+34'], '', $phone);
                $email = (string) EncryptionService::decryptValue($row['email']);

                $haystack = $this->normalizeSearchText(
                    $id . ' #' . $id . ' ' .
                    $clientNumber . ' #' . $clientNumber . ' ' .
                    $name . ' ' .
                    $surname . ' ' .
                    $dni . ' ' . $cleanDni . ' ' .
                    $phone . ' ' . $cleanPhone . ' ' .
                    $email
                );

                $matched = true;
                foreach ($tokens as $token) {
                    $cleanToken = str_replace(['.', '-', '#'], '', $token);
                    if (!str_contains($haystack, $token) && ($cleanToken === '' || !str_contains($haystack, $cleanToken))) {
                        $matched = false;
                        break;
                    }
                }

                if ($matched) {
                    $matchedIds[] = $id;
                }
            }

            // Si no hay coincidencias por búsqueda, devolver vacío inmediatamente
            if (empty($matchedIds)) {
                return $this->render('client/index.html.twig', [
                    'clients' => [],
                    'search' => $search,
                    'paid' => $paid,
                    'perPage' => $perPage,
                    'currentPage' => 1,
                    'totalPages' => 0,
                ]);
            }
        }

        // 2. Construir la consulta DQL con eager join de suscripciones activas
        $qb = $clientRepository->createQueryBuilder('c');
        $qb->leftJoin('c.subscriptions', 's', 'WITH', 's.isActive = true AND s.lastPaymentDate >= :startOfMonth')
           ->leftJoin('s.subscriptionType', 'st')
           ->setParameter('startOfMonth', $startOfMonth);

        if ($systemMode === 'hidden') {
            $qb->andWhere('c.billable = :billable')
               ->setParameter('billable', true);
        }

        if ($matchedIds !== null) {
            $qb->andWhere('c.id IN (:matchedIds)')
               ->setParameter('matchedIds', $matchedIds);
        }

        if ($paid === '1') {
            $qb->andWhere('s.id IS NOT NULL');
        } elseif ($paid === '0') {
            $qb->andWhere('s.id IS NULL');
        }

        // 3. Conteo total en BD (consulta rápida sin joins innecesarios ni hidratación)
        $countQb = clone $qb;
        $countQb->select('COUNT(DISTINCT c.id)')->resetDQLPart('orderBy');
        $totalClients = (int) $countQb->getQuery()->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalClients / $perPage));
        $currentPage = min($page, $totalPages);
        $offset = ($currentPage - 1) * $perPage;

        // 4. Obtener SOLO la página actual (12 a 96 registros) con carga ansiosa
        $qb->select('c', 's', 'st');

        if ($exactId !== null) {
            $qb->orderBy('CASE WHEN (c.id = :exactId OR c.client_number = :exactId) THEN 1 ELSE 0 END', 'DESC')
               ->addOrderBy('CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END', 'DESC')
               ->addOrderBy('c.client_number', 'DESC')
               ->addOrderBy('c.id', 'DESC')
               ->setParameter('exactId', $exactId);
        } else {
            $qb->orderBy('CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END', 'DESC')
               ->addOrderBy('c.client_number', 'DESC')
               ->addOrderBy('c.id', 'DESC');
        }

        $qb->setFirstResult($offset)
           ->setMaxResults($perPage);

        /** @var Client[] $clients */
        $clients = $qb->getQuery()->getResult();

        $monthNames = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        // 5. Mapear únicamente los clientes de la página actual
        $clientsWithPaymentStatus = [];
        foreach ($clients as $client) {
            $sub = $client->getSubscriptions()->last() ?: null;
            $hasPaid = false;
            $subscriptionTypeName = $sub ? $sub->getSubscriptionType()?->getName() : '—';
            $lastPaidMonth = null;
            $paymentUntil = null;

            if ($sub) {
                $lastPaymentDate = $sub->getLastPaymentDate();
                if ($lastPaymentDate) {
                    $m = (int) $lastPaymentDate->format('n');
                    $y = $lastPaymentDate->format('Y');
                    $lastPaidMonth = ($monthNames[$m] ?? '') . ' ' . $y;
                }
                $paymentUntil = $sub->getEndDate();

                if ($sub->isActive() && $lastPaymentDate && $lastPaymentDate->format('Y-m') === $currentDate->format('Y-m')) {
                    $hasPaid = true;
                }
            }

            $clientsWithPaymentStatus[] = [
                'client' => $client,
                'hasPaid' => $hasPaid,
                'subscriptionType' => $subscriptionTypeName,
                'lastPaidMonth' => $lastPaidMonth,
                'paymentUntil' => $paymentUntil,
            ];
        }

        return $this->render('client/index.html.twig', [
            'clients' => $clientsWithPaymentStatus,
            'search' => $search,
            'paid' => $paid,
            'perPage' => $perPage,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_client_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Client $client,
        EntityManagerInterface $entityManager,
        InvoiceService $invoiceService,
        ImageOptimizerService $imageOptimizer,
        ClientRepository $clientRepository
    ): Response {
        // Obtener la última suscripción del cliente
        $subscriptions = $client->getSubscriptions();
        $lastSubscription = $subscriptions->last();
        // Crear el formulario
        $form = $this->createForm(ClientType::class, $client);
        
        // Si existe una suscripción, establecer los valores iniciales
        if ($lastSubscription) {
            if ($lastSubscription->getRemainingPasses() !== null) {
                $form->get('remainingPasses')->setData($lastSubscription->getRemainingPasses());
            }
            $form->get('isPaid')->setData($lastSubscription->isActive());
            $form->get('paymentUntil')->setData($lastSubscription->getEndDate());
            $form->get('subscriptionType')->setData($lastSubscription->getSubscriptionType());
        }
    
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            // Validar que el client_number no esté asignado a otro socio
            if ($client->getClientNumber() !== null) {
                $existing = $clientRepository->findOneBy(['client_number' => $client->getClientNumber()]);
                if ($existing && $existing->getId() !== $client->getId()) {
                    $this->addFlash('error', sprintf('El Nº de Socio / ID %d ya pertenece a %s %s. Elige un número distinto.', $client->getClientNumber(), $existing->getName(), $existing->getSurname()));
                    return $this->render('client/edit.html.twig', [
                        'client' => $client,
                        'form' => $form->createView(),
                    ]);
                }
            }
            // ── Foto del cliente ──────────────────────────────────────────
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $uploadsDir = $this->getParameter('uploads_directory');
                // Eliminar foto anterior si existe
                if ($client->getPhoto()) {
                    $old = $uploadsDir . '/' . $client->getPhoto();
                    if (file_exists($old)) {
                        @unlink($old);
                    }
                }
                // Comprimir al máximo en formato WebP eliminando el original no comprimido
                $newFilename = $imageOptimizer->optimizeUploadedPhoto($photoFile, $uploadsDir, 600, 82);
                $client->setPhoto($newFilename);
            }
            // ─────────────────────────────────────────────────────────────

            if (!$lastSubscription) {
                $lastSubscription = new Subscription();
                $lastSubscription->setClient($client);
                $lastSubscription->setStartDate(new \DateTime());
                $lastSubscription->setIsActive(true);
                $lastSubscription->setLastPaymentDate(new \DateTime());
                
                $entityManager->persist($lastSubscription);
            }
    
            // Actualizar el tipo de suscripción
            $subscriptionType = $form->get('subscriptionType')->getData();
            $lastSubscription->setSubscriptionType($subscriptionType);
    
            // Actualizar la suscripción existente
            $isPaid = $form->get('isPaid')->getData();
    
            if ($isPaid) {
                $lastSubscription->setLastPaymentDate(new \DateTime());
                $lastSubscription->setIsActive(true);

                $startDate = new \DateTime();
                $endDate = clone $startDate;
                
                // Calcular la fecha de fin basada en meses o días
                if ($subscriptionType->getMonths()) {
                    $endDate->modify('first day of +' . $subscriptionType->getMonths() . ' month');
                    $endDate->setDate(
                        (int)$endDate->format('Y'),
                        (int)$endDate->format('m'),
                        4
                    );
                    $endDate->setTime(23, 59, 59);
                } elseif ($subscriptionType->getDays()) {
                    $endDate->modify('+' . $subscriptionType->getDays() . ' days');
                    $endDate->setTime(23, 59, 59);
                }

                $lastSubscription->setStartDate($startDate);
                $lastSubscription->setEndDate($endDate);

                // Si es pase diario, establecer el número de pases
                if ($subscriptionType->isDailyPass()) {
                    $lastSubscription->setRemainingPasses($subscriptionType->getDays());
                } else {
                    $lastSubscription->setRemainingPasses(null);
                }

                // Crear registro de facturación y generar factura
                $billingRecord = new \App\Entity\BillingRecord();
                $billingRecord->setClient($client);
                $billingRecord->setSubscription($lastSubscription);
                $billingRecord->setBillingDate(new \DateTime());
                $billingRecord->setPeriodStart($startDate);
                $billingRecord->setPeriodEnd($endDate);
                $billingRecord->setAmount($subscriptionType->getPrice());
                $billingRecord->setDescription('Pago de suscripción ' . $subscriptionType->getName());
                $billingRecord->setIsBilled(false);

                $entityManager->persist($billingRecord);

                // Generar factura inmediatamente para este registro de facturación
                try {
                    $invoice = $invoiceService->generateInvoiceForBillingRecord($billingRecord, $this->getUser());
                    $this->addFlash('success', 'Cliente actualizado y factura generada correctamente hasta el ' . $endDate->format('d/m/Y') . '. Factura: ' . $invoice->getInvoiceNumber());
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Cliente actualizado correctamente, pero no se pudo generar la factura: ' . $e->getMessage());
                }
            } else {
                $lastSubscription->setIsActive(false);
                $this->addFlash('success', 'Cliente actualizado correctamente. Suscripción desactivada.');
            }

            try {
                $entityManager->persist($client);
                $entityManager->flush();

                $returnTo = $request->query->get('return_to') ?? $request->request->get('return_to');
                if ($returnTo === 'dashboard') {
                    return $this->redirectToRoute('dashboard');
                }
                $referer = $request->headers->get('referer');
                if ($referer) {
                    $path = parse_url($referer, PHP_URL_PATH) ?? '';
                    if ($path === '/' || str_contains($path, '/dashboard')) {
                        return $this->redirectToRoute('dashboard');
                    }
                }
                return $this->redirectToRoute('app_client_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error al actualizar el cliente: ' . $e->getMessage());
            }
        }
    
        return $this->render('client/edit.html.twig', [
            'client' => $client,
            'form' => $form->createView(),
        ]);
    }
    
    #[Route('/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        InvoiceService $invoiceService,
        ImageOptimizerService $imageOptimizer,
        ClientRepository $clientRepository
    ): Response
    {
        $client = new Client();
        // Prefill con el siguiente número de socio correlativo disponible
        $nextNumber = $clientRepository->getNextClientNumber();
        $client->setClientNumber($nextNumber);

        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Si el usuario dejó el campo vacío, autocalcular con el siguiente correlativo
                if ($client->getClientNumber() === null) {
                    $client->setClientNumber($clientRepository->getNextClientNumber());
                } else {
                    // Validar unicidad del número asignado
                    $existing = $clientRepository->findOneBy(['client_number' => $client->getClientNumber()]);
                    if ($existing) {
                        $this->addFlash('error', sprintf('El Nº de Socio / ID %d ya pertenece a %s %s. Elige un número distinto.', $client->getClientNumber(), $existing->getName(), $existing->getSurname()));
                        return $this->render('client/new.html.twig', [
                            'client' => $client,
                            'form' => $form->createView(),
                        ]);
                    }
                }
                // ── Foto del cliente ──────────────────────────────────────
                $photoFile = $form->get('photo')->getData();
                if ($photoFile) {
                    $uploadsDir = $this->getParameter('uploads_directory');
                    // Comprimir al máximo en formato WebP eliminando el original no comprimido
                    $newFilename = $imageOptimizer->optimizeUploadedPhoto($photoFile, $uploadsDir, 600, 82);
                    $client->setPhoto($newFilename);
                }
                // ─────────────────────────────────────────────────────────

                // Obtener el SubscriptionType seleccionado del formulario
                $subscriptionType = $form->get('subscriptionType')->getData();
                
                // Crear una nueva suscripción asociada al cliente
                $subscription = new Subscription();
                $subscription->setClient($client);
                $subscription->setSubscriptionType($subscriptionType);
                
                $startDate = new \DateTime();
                $endDate = clone $startDate;
    
                // Calcular la fecha de fin basada en meses o días
                if ($subscriptionType->getMonths()) {
                    $endDate->modify('first day of +' . $subscriptionType->getMonths() . ' month');
                    $endDate->setDate(
                        (int)$endDate->format('Y'),
                        (int)$endDate->format('m'),
                        4
                    );
                    $endDate->setTime(23, 59, 59);
                } elseif ($subscriptionType->getDays()) {
                    $endDate->modify('+' . $subscriptionType->getDays() . ' days');
                    $endDate->setTime(23, 59, 59);
                } else {
                    // Por defecto, un mes
                    $endDate->modify('first day of +1 month');
                    $endDate->setDate(
                        (int)$endDate->format('Y'),
                        (int)$endDate->format('m'),
                        4
                    );
                    $endDate->setTime(23, 59, 59);
                }
    
                $subscription->setStartDate($startDate);
                $subscription->setEndDate($endDate);
                $subscription->setIsActive(true);
                $subscription->setLastPaymentDate($startDate);
                $subscription->setRemainingPasses(null);

                // Crear registro de facturación y generar factura
                $billingRecord = new \App\Entity\BillingRecord();
                $billingRecord->setClient($client);
                $billingRecord->setSubscription($subscription);
                $billingRecord->setBillingDate(new \DateTime());
                $billingRecord->setPeriodStart($startDate);
                $billingRecord->setPeriodEnd($endDate);
                $billingRecord->setAmount($subscriptionType->getPrice());
                $billingRecord->setDescription('Pago inicial de suscripción ' . $subscriptionType->getName());
                $billingRecord->setIsBilled(false);

                $entityManager->persist($billingRecord);

                // Persistir el cliente y la suscripción
                $entityManager->persist($client);
                $entityManager->persist($subscription);
                $entityManager->flush();

                // Generar factura inmediatamente para este registro de facturación
                try {
                    $invoice = $invoiceService->generateInvoiceForBillingRecord($billingRecord, $this->getUser());
                    $this->addFlash('success', 'Cliente creado y factura generada correctamente hasta el ' . $endDate->format('d/m/Y') . '. Factura: ' . $invoice->getInvoiceNumber());
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Cliente creado correctamente, pero no se pudo generar la factura: ' . $e->getMessage());
                }

                return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error al crear el cliente: ' . $e->getMessage());
            }
        }
    
        return $this->render('client/new.html.twig', [
            'client' => $client,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_client_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Client $client, AccessLogRepository $accessLogRepo): Response
    {
        $lastSubscription = $client->getSubscriptions()->last() ?: null;
        $hasPaid = false;
        $paymentUntil = null;
        $lastPaidMonth = null;

        $monthNames = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        if ($lastSubscription) {
            $paymentUntil = $lastSubscription->getEndDate();
            $lastPaymentDate = $lastSubscription->getLastPaymentDate();

            if ($lastPaymentDate) {
                $m = (int) $lastPaymentDate->format('n');
                $y = $lastPaymentDate->format('Y');
                $lastPaidMonth = ($monthNames[$m] ?? '') . ' ' . $y;
            }

            if ($lastSubscription->isActive()) {
                $currentDate = new \DateTime();

                if ($lastPaymentDate && $lastPaymentDate->format('Y-m') === $currentDate->format('Y-m')) {
                    $hasPaid = true;
                }
            }
        }

        $recentLogs = $accessLogRepo->findRecentByClient($client, 20);

        return $this->render('client/show.html.twig', [
            'client'        => $client,
            'hasPaid'       => $hasPaid,
            'paymentUntil'  => $paymentUntil,
            'lastPaidMonth' => $lastPaidMonth,
            'subscription'  => $lastSubscription,
            'recentLogs'    => $recentLogs,
        ]);
    }

    #[Route('/{id}', name: 'app_client_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$client->getId(), $request->request->get('_token'))) {
            try {
                // Eliminar foto física si existe
                if ($client->getPhoto()) {
                    $uploadsDir = $this->getParameter('uploads_directory');
                    $photoPath = $uploadsDir . '/' . $client->getPhoto();
                    if (file_exists($photoPath)) {
                        @unlink($photoPath);
                    }
                }

                // Eliminar dependencias respetando el orden de claves foráneas
                foreach ($client->getBillingRecords() as $billingRecord) {
                    $entityManager->remove($billingRecord);
                }
                foreach ($client->getInvoices() as $invoice) {
                    $entityManager->remove($invoice);
                }
                foreach ($client->getAccessLogs() as $accessLog) {
                    $entityManager->remove($accessLog);
                }
                foreach ($client->getSubscriptions() as $subscription) {
                    $entityManager->remove($subscription);
                }

                // Eliminar el cliente
                $entityManager->remove($client);
                $entityManager->flush();

                $this->addFlash('success', 'Cliente eliminado correctamente.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error al eliminar el cliente: ' . $e->getMessage());
            }
        }
    
        return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/renew', name: 'app_client_renew', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function renew(
        Request $request,
        Client $client,
        EntityManagerInterface $entityManager,
        InvoiceService $invoiceService
    ): Response {
        $isAjax = $request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json');

        if (!$this->isCsrfTokenValid('renew'.$client->getId(), $request->request->get('_token'))) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'Token de seguridad inválido.'], 400);
            }
            $this->addFlash('error', 'Token de seguridad inválido.');
            return $this->redirectAfterRenew($request, $client);
        }

        $subscriptions = $client->getSubscriptions();
        $lastSubscription = $subscriptions->last();

        if (!$lastSubscription) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'Este cliente no tiene ninguna suscripción que renovar.'], 400);
            }
            $this->addFlash('error', 'Este cliente no tiene ninguna suscripción que renovar.');
            return $this->redirectAfterRenew($request, $client);
        }

        $subscriptionType = $lastSubscription->getSubscriptionType();
        $startDate = new \DateTime();
        $endDate = clone $startDate;
        // Calcular la fecha de fin basada en meses o días
        if ($subscriptionType->getMonths()) {
            $endDate->modify('first day of +' . $subscriptionType->getMonths() . ' month');
            $endDate->setDate(
                (int)$endDate->format('Y'),
                (int)$endDate->format('m'),
                4
            );
            $endDate->setTime(23, 59, 59);
        } elseif ($subscriptionType->getDays()) {
            $endDate->modify('+' . $subscriptionType->getDays() . ' days');
            $endDate->setTime(23, 59, 59);
        }

        // Si es pase diario, sumar los nuevos pases a los existentes
        if ($subscriptionType->isDailyPass()) {
            $currentPasses = $lastSubscription->getRemainingPasses() ?? 0;
            $newPasses = $subscriptionType->getDays();
            $lastSubscription->setRemainingPasses($currentPasses + $newPasses);
        }

        $lastSubscription->setLastPaymentDate(new \DateTime());
        $lastSubscription->setIsActive(true);
        $lastSubscription->setStartDate($startDate);
        $lastSubscription->setEndDate($endDate);

        // Crear registro de facturación
        $billingRecord = new \App\Entity\BillingRecord();
        $billingRecord->setClient($client);
        $billingRecord->setSubscription($lastSubscription);
        $billingRecord->setBillingDate(new \DateTime());
        $billingRecord->setPeriodStart($startDate);
        $billingRecord->setPeriodEnd($endDate);
        $billingRecord->setAmount($subscriptionType->getPrice());
        $billingRecord->setDescription('Renovación de suscripción ' . $subscriptionType->getName());
        $billingRecord->setIsBilled(false);

        $entityManager->persist($billingRecord);

        try {
            $entityManager->flush();
        } catch (\Exception $e) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'Error al renovar la suscripción: ' . $e->getMessage()], 500);
            }
            $this->addFlash('error', 'Error al renovar la suscripción: ' . $e->getMessage());
            return $this->redirectAfterRenew($request, $client);
        }

        // Generar factura inmediatamente para este registro de facturación
        $invoiceNumber = null;
        try {
            $invoice = $invoiceService->generateInvoiceForBillingRecord($billingRecord, $this->getUser());
            $invoiceNumber = $invoice->getInvoiceNumber();
            $msg = 'Suscripción renovada y factura generada correctamente hasta el ' . $endDate->format('d/m/Y') . '. Factura: ' . $invoiceNumber;
            $this->addFlash('success', $msg);
        } catch (\Exception $e) {
            $msg = 'Suscripción renovada correctamente hasta el ' . $endDate->format('d/m/Y') . ', pero no se pudo generar la factura: ' . $e->getMessage();
            $this->addFlash('warning', $msg);
        }

        if ($isAjax) {
            return $this->json([
                'success'       => true,
                'message'       => $msg,
                'endDate'       => $endDate->format('d/m/Y'),
                'invoiceNumber' => $invoiceNumber,
                'client'        => [
                    'id'   => $client->getId(),
                    'name' => $client->getName() . ' ' . $client->getSurname(),
                ],
            ]);
        }

        return $this->redirectAfterRenew($request, $client);
    }

    private function redirectAfterRenew(Request $request, Client $client): Response
    {
        $returnTo = $request->query->get('return_to') ?? $request->request->get('return_to');
        if ($returnTo === 'dashboard') {
            return $this->redirectToRoute('dashboard');
        }

        $referer = $request->headers->get('referer');
        if ($referer) {
            $path = parse_url($referer, PHP_URL_PATH) ?? '';
            if ($path === '/' || str_contains($path, '/dashboard')) {
                return $this->redirectToRoute('dashboard');
            }
            if (str_contains($referer, '/client/' . $client->getId())) {
                return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
            }
        }

        return $this->redirectToRoute('app_client_index');
    }

    #[Route('/{id}/assign-card', name: 'app_client_assign_card', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function assignCard(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('assign_card' . $client->getId(), $request->request->get('_token'))) {
            $cardCode = trim((string) $request->request->get('card_code', ''));
            $client->setCardCode($cardCode !== '' ? $cardCode : null);
            $em->flush();
            $this->addFlash('success', $cardCode !== '' ? 'Tarjeta asignada correctamente.' : 'Tarjeta desvinculada.');
        } else {
            $this->addFlash('error', 'Token de seguridad inválido.');
        }

        return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
    }

    #[Route('/system/mode/{action}', name: 'app_client_system_mode', methods: ['GET'], requirements: ['action' => 'normal|hidden'])]
    public function systemMode(Request $request, string $action): Response
    {
        $session = $request->getSession();

        if ($action === 'hidden') {
            $session->set('system_mode', 'hidden');
            $this->addFlash('info', 'Modo de sistema activado');
        } elseif ($action === 'normal') {
            $session->remove('system_mode');
            $this->addFlash('info', 'Modo normal restaurado');
        }

        return $this->redirectToRoute('app_client_index');
    }

    /** Exportar listado de clientes como CSV */
    #[Route('/export-csv', name: 'app_client_export_csv', methods: ['GET'])]
    public function exportCsv(ClientRepository $clientRepository): StreamedResponse
    {
        $clients = $clientRepository->findAll();

        $response = new StreamedResponse(function () use ($clients) {
            $handle = fopen('php://output', 'w');
            // BOM para que Excel abra UTF-8 correctamente
            fputs($handle, "\xEF\xBB\xBF");
            // Cabecera
            fputcsv($handle, ['ID', 'Nombre', 'Apellidos', 'DNI', 'Email', 'Teléfono', 'Localidad', 'Fecha alta', 'Código tarjeta', 'Tipo suscripción', 'Activo hasta', 'Último acceso'], ';');

            foreach ($clients as $client) {
                $sub     = $client->getSubscriptions()->last() ?: null;
                $subType = $sub ? $sub->getSubscriptionType()->getName() : '';
                $endDate = $sub ? $sub->getEndDate()?->format('d/m/Y') : '';

                fputcsv($handle, [
                    $client->getId(),
                    $client->getName(),
                    $client->getSurname(),
                    $client->getIdNumber(),
                    $client->getEmail(),
                    $client->getPhone(),
                    $client->getLocality(),
                    $client->getStartDate()?->format('d/m/Y'),
                    $client->getCardCode(),
                    $subType,
                    $endDate,
                    $client->getLastAccess()?->format('d/m/Y H:i'),
                ], ';');
            }
            fclose($handle);
        });

        $filename = 'clientes_' . date('Y-m-d') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/backup/non-billable', name: 'app_client_backup_non_billable', methods: ['POST'])]
    public function backupNonBillable(Request $request, ClientRepository $clientRepository, EntityManagerInterface $entityManager): Response
    {
        // Verificar que estamos en modo hidden
        $session = $request->getSession();
        if ($session->get('system_mode') !== 'hidden') {
            $this->addFlash('error', 'Esta acción solo está disponible en modo oculto.');
            return $this->redirectToRoute('app_client_index');
        }

        try {
            // Obtener clientes no facturables
            $nonBillableClients = $clientRepository->findBy(['billable' => false]);

            if (empty($nonBillableClients)) {
                $this->addFlash('info', 'No hay clientes no facturables para procesar.');
                return $this->redirectToRoute('app_client_index');
            }

            // Generar timestamp para el archivo
            $timestamp = date('Y-m-d_H-i-s');
            $backupDir = $this->getParameter('kernel.project_dir') . '/var/backups';
            $backupFile = $backupDir . '/non_billable_clients_backup_' . $timestamp . '.sql';

            // Crear directorio si no existe
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0777, true);
            }

            // Generar dump SQL
            $sqlDump = $this->generateSqlDump($nonBillableClients);

            // Guardar el dump
            file_put_contents($backupFile, $sqlDump);

            // Eliminar los datos
            $this->deleteNonBillableClients($nonBillableClients, $entityManager);

            // Cambiar automáticamente al modo normal después del backup
            $session = $request->getSession();
            $session->remove('system_mode');

            $this->addFlash('success', 'Backup de clientes no facturables completado exitosamente. Se eliminaron ' . count($nonBillableClients) . ' clientes. El sistema ha vuelto al modo normal.');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al ejecutar el backup: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_client_index');
    }

    private function generateSqlDump(array $clients): string
    {
        $sql = "-- Backup de clientes no facturables generado el " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Este archivo puede ser ejecutado para restaurar los datos\n\n";

        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($clients as $client) {
            // Insertar cliente
            $sql .= sprintf(
                "INSERT INTO client (id, name, surname, address, locality, zip_code, birth_date, phone, email, id_number, start_date, card_code, billable) VALUES (%d, '%s', '%s', %s, %s, %s, %s, %s, '%s', %s, %s, %s, %d);\n",
                $client->getId(),
                $this->escapeString($client->getName()),
                $this->escapeString($client->getSurname()),
                $client->getAddress() ? "'" . $this->escapeString($client->getAddress()) . "'" : 'NULL',
                $client->getLocality() ? "'" . $this->escapeString($client->getLocality()) . "'" : 'NULL',
                $client->getZipCode() ?? 'NULL',
                $client->getBirthDate() ? "'" . $client->getBirthDate()->format('Y-m-d') . "'" : 'NULL',
                $client->getPhone() ?? 'NULL',
                $this->escapeString($client->getEmail()),
                $client->getIdNumber() ? "'" . $this->escapeString($client->getIdNumber()) . "'" : 'NULL',
                $client->getStartDate() ? "'" . $client->getStartDate()->format('Y-m-d') . "'" : 'NULL',
                $client->getCardCode() ? "'" . $this->escapeString($client->getCardCode()) . "'" : 'NULL',
                $client->isBillable() ? 1 : 0
            );

            // Insertar suscripciones relacionadas
            foreach ($client->getSubscriptions() as $subscription) {
                $sql .= sprintf(
                    "INSERT INTO subscription (id, start_date, end_date, client_id, subscription_type_id) VALUES (%d, '%s', %s, %d, %d);\n",
                    $subscription->getId(),
                    $subscription->getStartDate()->format('Y-m-d'),
                    $subscription->getEndDate() ? "'" . $subscription->getEndDate()->format('Y-m-d') . "'" : 'NULL',
                    $client->getId(),
                    $subscription->getSubscriptionType()->getId()
                );
            }

            // Insertar logs de acceso relacionados
            foreach ($client->getAccessLogs() as $accessLog) {
                $sql .= sprintf(
                    "INSERT INTO access_log (id, access_time, client_id) VALUES (%d, '%s', %d);\n",
                    $accessLog->getId(),
                    $accessLog->getAccessTime()->format('Y-m-d H:i:s'),
                    $client->getId()
                );
            }

            // Insertar facturas relacionadas
            foreach ($client->getInvoices() as $invoice) {
                $sql .= sprintf(
                    "INSERT INTO invoice (id, issue_date, total_amount, xml_content, client_id) VALUES (%d, '%s', %.2f, %s, %d);\n",
                    $invoice->getId(),
                    $invoice->getIssueDate()->format('Y-m-d'),
                    $invoice->getTotalAmount(),
                    $invoice->getXmlContent() ? "'" . $this->escapeString($invoice->getXmlContent()) . "'" : 'NULL',
                    $client->getId()
                );
            }

            // Insertar registros de facturación relacionados
            foreach ($client->getBillingRecords() as $billingRecord) {
                $sql .= sprintf(
                    "INSERT INTO billing_record (id, amount, billing_date, description, client_id) VALUES (%d, %.2f, '%s', %s, %d);\n",
                    $billingRecord->getId(),
                    $billingRecord->getAmount(),
                    $billingRecord->getBillingDate()->format('Y-m-d'),
                    $billingRecord->getDescription() ? "'" . $this->escapeString($billingRecord->getDescription()) . "'" : 'NULL',
                    $client->getId()
                );
            }

            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        return $sql;
    }

    private function deleteNonBillableClients(array $clients, EntityManagerInterface $entityManager): void
    {
        foreach ($clients as $client) {
            // Eliminar en orden inverso para respetar las foreign keys
            foreach ($client->getBillingRecords() as $billingRecord) {
                $entityManager->remove($billingRecord);
            }
            foreach ($client->getInvoices() as $invoice) {
                $entityManager->remove($invoice);
            }
            foreach ($client->getAccessLogs() as $accessLog) {
                $entityManager->remove($accessLog);
            }
            foreach ($client->getSubscriptions() as $subscription) {
                $entityManager->remove($subscription);
            }
            $entityManager->remove($client);
        }

        $entityManager->flush();
    }

    private function escapeString(?string $string): string
    {
        if ($string === null) {
            return '';
        }
        return str_replace(['\\', "'", "\n", "\r"], ['\\\\', "\\'", "\\n", "\\r"], $string);
    }

    /**
     * Endpoint para el Buscador Rápido Global del header.
     * Permite localizar clientes en tiempo real por nombre, DNI, teléfono o ID con atajo de teclado Ctrl+K.
     */
    #[Route('/quick-search', name: 'app_client_quick_search', methods: ['GET'])]
    public function quickSearch(
        Request $request,
        EntityManagerInterface $em,
        ClientRepository $clientRepository
    ): JsonResponse {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 2) {
            return $this->json([]);
        }

        $cleanNumeric = ltrim($q, '#');
        $exactId = ctype_digit($cleanNumeric) ? (int) $cleanNumeric : null;

        $normalizedQuery = $this->normalizeSearchText($q);
        $tokens = preg_split('/[\s,]+/', $normalizedQuery, -1, PREG_SPLIT_NO_EMPTY);

        $conn = $em->getConnection();
        $rows = $conn->fetchAllAssociative('SELECT id, client_number, name, surname, id_number, phone, photo FROM client');

        $matchedIds = [];
        $priorityIds = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $clientNumber = isset($row['client_number']) && $row['client_number'] !== null ? (int) $row['client_number'] : $id;
            if ($exactId !== null && ($id === $exactId || $clientNumber === $exactId)) {
                $priorityIds[] = $id;
                continue;
            }

            $name = (string) EncryptionService::decryptValue($row['name']);
            $surname = (string) EncryptionService::decryptValue($row['surname']);
            $dni = (string) EncryptionService::decryptValue($row['id_number']);
            $cleanDni = str_replace(['.', '-', ' '], '', $dni);
            $phone = (string) EncryptionService::decryptValue($row['phone']);
            $cleanPhone = str_replace(['.', '-', ' ', '+34'], '', $phone);

            $haystack = $this->normalizeSearchText(
                $id . ' #' . $id . ' ' .
                $clientNumber . ' #' . $clientNumber . ' ' .
                $name . ' ' . $surname . ' ' .
                $dni . ' ' . $cleanDni . ' ' .
                $phone . ' ' . $cleanPhone
            );

            $matches = true;
            foreach ($tokens as $token) {
                $cleanToken = str_replace(['.', '-', '#'], '', $token);
                if (!str_contains($haystack, $token) && ($cleanToken === '' || !str_contains($haystack, $cleanToken))) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $matchedIds[] = $id;
            }

            if (count($priorityIds) + count($matchedIds) >= 15) {
                break;
            }
        }

        $allIds = array_slice(array_merge($priorityIds, $matchedIds), 0, 10);
        if (empty($allIds)) {
            return $this->json([]);
        }

        $clients = $clientRepository->findBy(['id' => $allIds]);
        $clientsById = [];
        foreach ($clients as $c) {
            $clientsById[$c->getId()] = $c;
        }

        $currentDate = new \DateTime();
        $results = [];
        foreach ($allIds as $cid) {
            if (!isset($clientsById[$cid])) {
                continue;
            }
            /** @var Client $client */
            $client = $clientsById[$cid];

            $activeSubscription = null;
            foreach ($client->getSubscriptions() as $sub) {
                if ($sub->isActive()) {
                    $activeSubscription = $sub;
                    break;
                }
            }

            $hasPaid = false;
            $subType = '—';
            if ($activeSubscription && $activeSubscription->isActive()) {
                $lastPaymentDate = $activeSubscription->getLastPaymentDate();
                if ($lastPaymentDate && $lastPaymentDate->format('Y-m') === $currentDate->format('Y-m')) {
                    $hasPaid = true;
                }
                $subType = $activeSubscription->getSubscriptionType()?->getName() ?? 'Activa';
            }

            $firstInitial = mb_substr((string) $client->getName(), 0, 1, 'UTF-8');
            $secondInitial = mb_substr((string) $client->getSurname(), 0, 1, 'UTF-8');
            $initials = mb_strtoupper($firstInitial . $secondInitial, 'UTF-8');

            $results[] = [
                'id'               => $client->getId(),
                'clientNumber'     => $client->getEffectiveClientNumber(),
                'name'             => trim($client->getName() . ' ' . $client->getSurname()),
                'idNumber'         => $client->getIdNumber(),
                'phone'            => $client->getPhone(),
                'photoUrl'         => $client->getPhoto() ? '/uploads/clients/' . $client->getPhoto() : null,
                'initials'         => $initials ?: 'C',
                'hasPaid'          => $hasPaid,
                'subscriptionType' => $subType,
                'url'              => $this->generateUrl('app_client_show', ['id' => $client->getId()]),
            ];
        }

        return $this->json($results);
    }

    /**
     * Normaliza cadenas para búsqueda insensible a acentos, diacríticos y mayúsculas.
     */
    private function normalizeSearchText(string $text): string
    {
        $lower = mb_strtolower($text, 'UTF-8');
        return strtr($lower, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);
    }
}
