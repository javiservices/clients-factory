<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Subscription;
use App\Entity\SubscriptionType;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use App\Repository\SubscriptionRepository;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/client')]
final class ClientController extends AbstractController
{
    #[Route(name: 'app_client_index', methods: ['GET', 'POST'])]
    public function index(Request $request, ClientRepository $clientRepository): Response
    {
        $session = $request->getSession();
        $systemMode = $session->get('system_mode');

        // Filtros
        $search = $request->query->get('search', '');
        $paid = $request->query->get('paid', null);
        $perPage = (int) $request->query->get('perPage', $session->get('perPage', 12));
        $page = (int) $request->query->get('page', 1);

        // Guardar perPage en sesión si el usuario lo cambia
        if ($request->query->has('perPage')) {
            $session->set('perPage', $perPage);
        }

        // Construir la consulta base
        $qb = $clientRepository->createQueryBuilder('c');

        // Si estamos en modo oculto, solo mostrar clientes facturables
        if ($systemMode === 'hidden') {
            $qb->andWhere('c.billable = :billable')
               ->setParameter('billable', true);
        }

        // Filtrar los clientes según el término de búsqueda
        if ($search) {
            $qb->andWhere('c.name LIKE :search OR c.surname LIKE :search OR c.id = :id')
               ->setParameter('search', '%' . $search . '%')
               ->setParameter('id', (int) $search);
        }

        // Ordenar por id descendente
        $qb->orderBy('c.id', 'DESC');

        $clients = $qb->getQuery()->getResult();

        // Procesar cada cliente para determinar si ha pagado el mes
        $clientsWithPaymentStatus = [];
        foreach ($clients as $client) {
            $subscription = $client->getSubscriptions()->last(); // Obtener la última suscripción
            $hasPaid = false;
            $subscriptionTypeName = $subscription ? $subscription->getSubscriptionType()->getName() : 'N/A';
            if ($subscription && $subscription->isActive()) {
                $lastPaymentDate = $subscription->getLastPaymentDate();
                $currentDate = new \DateTime();
                if ($lastPaymentDate && $lastPaymentDate->format('Y-m') === $currentDate->format('Y-m')) {
                    $hasPaid = true;
                }
            }
            $clientsWithPaymentStatus[] = [
                'client' => $client,
                'hasPaid' => $hasPaid,
                'subscriptionType' => $subscriptionTypeName,
            ];
        }

        // Filtrar por estado de pago si se selecciona
        if ($paid !== null && $paid !== '') {
            $clientsWithPaymentStatus = array_filter($clientsWithPaymentStatus, function($c) use ($paid) {
                return $paid == '1' ? $c['hasPaid'] : !$c['hasPaid'];
            });
        }

        // Ordenar: primero pagados, luego por id descendente
        usort($clientsWithPaymentStatus, function($a, $b) {
            if ($a['hasPaid'] === $b['hasPaid']) {
                return $b['client']->getId() <=> $a['client']->getId();
            }
            return $a['hasPaid'] ? -1 : 1;
        });

        // Paginación
        $totalClients = count($clientsWithPaymentStatus);
        $totalPages = (int) ceil($totalClients / $perPage);
        $currentPage = max(1, min($page, $totalPages));
        $offset = ($currentPage - 1) * $perPage;
        $clientsWithPaymentStatus = array_slice($clientsWithPaymentStatus, $offset, $perPage);

        return $this->render('client/index.html.twig', [
            'clients' => $clientsWithPaymentStatus,
            'search' => $search,
            'paid' => $paid,
            'perPage' => $perPage,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_client_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Client $client,
        EntityManagerInterface $entityManager,
        InvoiceService $invoiceService
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
                        5
                    );
                } elseif ($subscriptionType->getDays()) {
                    $endDate->modify('+' . $subscriptionType->getDays() . ' days');
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
                // El mensaje de éxito se muestra arriba en la lógica de facturación
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
    public function new(Request $request, EntityManagerInterface $entityManager, InvoiceService $invoiceService): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            try {
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
                        5
                    );
                } elseif ($subscriptionType->getDays()) {
                    $endDate->modify('+' . $subscriptionType->getDays() . ' days');
                } else {
                    // Por defecto, un mes
                    $endDate->modify('first day of +1 month');
                    $endDate->setDate(
                        (int)$endDate->format('Y'),
                        (int)$endDate->format('m'),
                        5
                    );
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

    #[Route('/{id}', name: 'app_client_show', methods: ['GET'])]
    public function show(Client $client): Response
    {
        return $this->render('client/show.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/{id}', name: 'app_client_delete', methods: ['POST'])]
    public function delete(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$client->getId(), $request->request->get('_token'))) {
            // Eliminar las suscripciones asociadas al cliente
            foreach ($client->getSubscriptions() as $subscription) {
                $entityManager->remove($subscription);
            }
    
            // Eliminar el cliente
            $entityManager->remove($client);
            $entityManager->flush();
    
            $this->addFlash('success', 'Cliente eliminado correctamente.');
        }
    
        return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/renew', name: 'app_client_renew', methods: ['POST'])]
    public function renew(
        Request $request,
        Client $client,
        EntityManagerInterface $entityManager,
        InvoiceService $invoiceService
    ): Response {
        if ($this->isCsrfTokenValid('renew'.$client->getId(), $request->request->get('_token'))) {
            $subscriptions = $client->getSubscriptions();
            $lastSubscription = $subscriptions->last();
    
            if (!$lastSubscription) {
                $this->addFlash('error', 'Este cliente no tiene ninguna suscripción que renovar.');
                return $this->redirectToRoute('app_client_index');
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
                    5
                );
            } elseif ($subscriptionType->getDays()) {
                $endDate->modify('+' . $subscriptionType->getDays() . ' days');
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
                $this->addFlash('error', 'Error al renovar la suscripción: ' . $e->getMessage());
                return $this->redirectToRoute('app_client_index');
            }

            // Generar factura inmediatamente para este registro de facturación
            try {
                $invoice = $invoiceService->generateInvoiceForBillingRecord($billingRecord, $this->getUser());
                $this->addFlash('success', 'Suscripción renovada y factura generada correctamente hasta el ' . $endDate->format('d/m/Y') . '. Factura: ' . $invoice->getInvoiceNumber());
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Suscripción renovada correctamente hasta el ' . $endDate->format('d/m/Y') . ', pero no se pudo generar la factura: ' . $e->getMessage());
            }
        }
    
        return $this->redirectToRoute('app_client_index');
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
}
