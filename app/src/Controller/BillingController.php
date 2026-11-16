<?php

namespace App\Controller;

use App\Entity\BillingRecord;
use App\Entity\Client;
use App\Repository\BillingRecordRepository;
use App\Service\EncryptionService;
use App\Service\SepaXmlService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/billing')]
class BillingController extends AbstractController
{
    /**
     * Listado y filtrado de registros de facturación.
     * Solo muestra clientes con el check de 'Gestión externa' (billable = true).
     */
    #[Route('/', name: 'app_billing_index', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        EncryptionService $encryptionService
    ): Response {
        // Fechas por defecto: mes en curso (primer y último día)
        $defaultStart = (new \DateTime('first day of this month 00:00:00'))->format('Y-m-d');
        $defaultEnd   = (new \DateTime('last day of this month 23:59:59'))->format('Y-m-d');

        $startDateStr = $request->query->get('start_date', $defaultStart);
        $endDateStr   = $request->query->get('end_date', $defaultEnd);
        $search       = trim((string) $request->query->get('search', ''));
        $page         = max(1, (int) $request->query->get('page', 1));
        $perPage      = (int) $request->query->get('perPage', 25);
        if ($perPage < 10) {
            $perPage = 25;
        }

        try {
            $startDate = new \DateTime($startDateStr . ' 00:00:00');
        } catch (\Exception) {
            $startDate = new \DateTime($defaultStart . ' 00:00:00');
            $startDateStr = $defaultStart;
        }

        try {
            $endDate = new \DateTime($endDateStr . ' 23:59:59');
        } catch (\Exception) {
            $endDate = new \DateTime($defaultEnd . ' 23:59:59');
            $endDateStr = $defaultEnd;
        }

        // 1. Obtener registros de facturación del período filtrando por Gestión Externa (billable = 1)
        // Usamos DQL con join a Client, Subscription y SubscriptionType
        $qb = $em->createQueryBuilder();
        $qb->select('b', 'c', 's', 'st')
           ->from(BillingRecord::class, 'b')
           ->join('b.client', 'c')
           ->leftJoin('b.subscription', 's')
           ->leftJoin('s.subscriptionType', 'st')
           ->where('b.billingDate >= :start')
           ->andWhere('b.billingDate <= :end')
           ->andWhere('c.billable = :billable')
           ->setParameter('start', $startDate)
           ->setParameter('end', $endDate)
           ->setParameter('billable', true)
           ->orderBy('b.billingDate', 'DESC')
           ->addOrderBy('b.id', 'DESC');

        /** @var BillingRecord[] $allRecords */
        $allRecords = $qb->getQuery()->getResult();

        // 2. Descifrar datos personales y aplicar búsqueda de texto en memoria si se solicita
        $filteredItems = [];
        $totalAmount = 0.0;
        $uniqueClients = [];
        $recordsWithIban = 0;

        $searchNormalized = $this->normalizeSearchText($search);
        $searchTokens = preg_split('/[\s,]+/', $searchNormalized, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($allRecords as $record) {
            $client = $record->getClient();
            $clientName = (string) $client->getName();
            $clientSurname = (string) $client->getSurname();
            $clientIdNumber = (string) $client->getIdNumber();
            $clientPhone = (string) $client->getPhone();

            // Si hay búsqueda por texto, verificar que coincidan todos los tokens
            if (!empty($searchTokens)) {
                $cleanDni = str_replace(['.', '-', ' '], '', $clientIdNumber);
                $cleanPhone = str_replace(['.', '-', ' ', '+34'], '', $clientPhone);
                $haystack = $this->normalizeSearchText(
                    $client->getId() . ' ' .
                    $clientName . ' ' .
                    $clientSurname . ' ' .
                    $clientIdNumber . ' ' . $cleanDni . ' ' .
                    $clientPhone . ' ' . $cleanPhone . ' ' .
                    $record->getDescription()
                );

                $matched = true;
                foreach ($searchTokens as $token) {
                    $cleanToken = str_replace(['.', '-', '#'], '', $token);
                    if (!str_contains($haystack, $token) && ($cleanToken === '' || !str_contains($haystack, $cleanToken))) {
                        $matched = false;
                        break;
                    }
                }

                if (!$matched) {
                    continue;
                }
            }

            $amount = (float) $record->getAmount();
            $totalAmount += $amount;
            $uniqueClients[$client->getId()] = true;
            if (!empty($client->getIban())) {
                $recordsWithIban++;
            }

            $filteredItems[] = [
                'record'         => $record,
                'client'         => $client,
                'clientName'     => $clientName,
                'clientSurname'  => $clientSurname,
                'clientIdNumber' => $clientIdNumber,
                'clientPhone'    => $clientPhone,
                'clientIban'     => $client->getIban(),
                'amount'         => $amount,
            ];
        }

        // 3. Paginación de los resultados
        $totalItems = count($filteredItems);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = min($page, $totalPages);
        $offset = ($currentPage - 1) * $perPage;
        $pagedItems = array_slice($filteredItems, $offset, $perPage);

        $distinctClientsCount = count($uniqueClients);
        $averageAmount = $totalItems > 0 ? ($totalAmount / $totalItems) : 0.0;

        return $this->render('billing/index.html.twig', [
            'items'                => $pagedItems,
            'totalItems'           => $totalItems,
            'totalAmount'          => $totalAmount,
            'distinctClientsCount' => $distinctClientsCount,
            'recordsWithIban'      => $recordsWithIban,
            'averageAmount'        => $averageAmount,
            'startDate'            => $startDateStr,
            'endDate'              => $endDateStr,
            'search'               => $search,
            'currentPage'          => $currentPage,
            'totalPages'           => $totalPages,
            'perPage'              => $perPage,
        ]);
    }

    /**
     * Exportación de la facturación a archivo CSV compatible con Excel.
     * Solo exporta clientes con 'Gestión externa' activa (billable = true).
     */
    #[Route('/export/csv', name: 'app_billing_export_csv', methods: ['GET'])]
    public function exportCsv(
        Request $request,
        EntityManagerInterface $em,
        EncryptionService $encryptionService
    ): StreamedResponse {
        $defaultStart = (new \DateTime('first day of this month 00:00:00'))->format('Y-m-d');
        $defaultEnd   = (new \DateTime('last day of this month 23:59:59'))->format('Y-m-d');

        $startDateStr = $request->query->get('start_date', $defaultStart);
        $endDateStr   = $request->query->get('end_date', $defaultEnd);
        $search       = trim((string) $request->query->get('search', ''));

        try {
            $startDate = new \DateTime($startDateStr . ' 00:00:00');
        } catch (\Exception) {
            $startDate = new \DateTime($defaultStart . ' 00:00:00');
            $startDateStr = $defaultStart;
        }

        try {
            $endDate = new \DateTime($endDateStr . ' 23:59:59');
        } catch (\Exception) {
            $endDate = new \DateTime($defaultEnd . ' 23:59:59');
            $endDateStr = $defaultEnd;
        }

        // Consulta filtrando estrictamente billable = true
        $qb = $em->createQueryBuilder();
        $qb->select('b', 'c', 's', 'st')
           ->from(BillingRecord::class, 'b')
           ->join('b.client', 'c')
           ->leftJoin('b.subscription', 's')
           ->leftJoin('s.subscriptionType', 'st')
           ->where('b.billingDate >= :start')
           ->andWhere('b.billingDate <= :end')
           ->andWhere('c.billable = :billable')
           ->setParameter('start', $startDate)
           ->setParameter('end', $endDate)
           ->setParameter('billable', true)
           ->orderBy('b.billingDate', 'ASC')
           ->addOrderBy('c.id', 'ASC');

        /** @var BillingRecord[] $records */
        $records = $qb->getQuery()->getResult();

        $searchNormalized = $this->normalizeSearchText($search);
        $searchTokens = preg_split('/[\s,]+/', $searchNormalized, -1, PREG_SPLIT_NO_EMPTY);

        $filename = sprintf('facturacion_gestion_externa_%s_a_%s.csv', $startDateStr, $endDateStr);

        $response = new StreamedResponse(function () use ($records, $searchTokens) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 para apertura directa e impecable en Microsoft Excel
            fputs($handle, "\xEF\xBB\xBF");

            // Cabeceras del archivo CSV (delimitador punto y coma ';')
            fputcsv($handle, [
                'ID Cliente',
                'Nombre',
                'Apellidos',
                'DNI / NIF',
                'Teléfono',
                'Localidad',
                'IBAN',
                'Fecha de Cobro',
                'Concepto / Plan',
                'Periodo Desde',
                'Periodo Hasta',
                'Importe (€)',
                'Gestión Externa',
            ], ';');

            foreach ($records as $record) {
                $client = $record->getClient();

                // Si por alguna razón client no es billable (garantía doble de seguridad), ignorar
                if (!$client->isBillable()) {
                    continue;
                }

                $name = (string) $client->getName();
                $surname = (string) $client->getSurname();
                $dni = (string) $client->getIdNumber();
                $phone = (string) $client->getPhone();
                $locality = (string) $client->getLocality();
                $iban = (string) $client->getIban();

                // Filtrado por búsqueda de texto si se especificó
                if (!empty($searchTokens)) {
                    $cleanDni = str_replace(['.', '-', ' '], '', $dni);
                    $cleanPhone = str_replace(['.', '-', ' ', '+34'], '', $phone);
                    $cleanIban = str_replace(['.', '-', ' '], '', $iban);
                    $haystack = $this->normalizeSearchText(
                        $client->getId() . ' ' .
                        $name . ' ' .
                        $surname . ' ' .
                        $dni . ' ' . $cleanDni . ' ' .
                        $phone . ' ' . $cleanPhone . ' ' .
                        $iban . ' ' . $cleanIban . ' ' .
                        $record->getDescription()
                    );

                    $matched = true;
                    foreach ($searchTokens as $token) {
                        $cleanToken = str_replace(['.', '-', '#'], '', $token);
                        if (!str_contains($haystack, $token) && ($cleanToken === '' || !str_contains($haystack, $cleanToken))) {
                            $matched = false;
                            break;
                        }
                    }

                    if (!$matched) {
                        continue;
                    }
                }

                $amountFormatted = number_format((float) $record->getAmount(), 2, ',', '');
                $billingDateFormatted = $record->getBillingDate() ? $record->getBillingDate()->format('d/m/Y') : '';
                $periodStartFormatted = $record->getPeriodStart() ? $record->getPeriodStart()->format('d/m/Y') : '';
                $periodEndFormatted   = $record->getPeriodEnd() ? $record->getPeriodEnd()->format('d/m/Y') : '';
                $concept = $record->getDescription() ?: ($record->getSubscription()?->getSubscriptionType()?->getName() ?? 'Cuota gimnasio');

                fputcsv($handle, [
                    $client->getId(),
                    $name,
                    $surname,
                    $dni,
                    $phone,
                    $locality,
                    $iban,
                    $billingDateFormatted,
                    $concept,
                    $periodStartFormatted,
                    $periodEndFormatted,
                    $amountFormatted,
                    'Sí',
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    /**
     * Exportación de remesa bancaria en formato XML SEPA ISO 20022 pain.008.001.02 (Norma 19 / Adeudo Directo CORE).
     * Solo incluye clientes con 'Gestión externa' activa (billable = true) que dispongan de cuenta IBAN válida.
     */
    #[Route('/export/sepa', name: 'app_billing_export_sepa', methods: ['GET'])]
    public function exportSepa(
        Request $request,
        EntityManagerInterface $em,
        SepaXmlService $sepaXmlService
    ): Response {
        try {
            $defaultStart = (new \DateTime('first day of this month 00:00:00'))->format('Y-m-d');
            $defaultEnd   = (new \DateTime('last day of this month 23:59:59'))->format('Y-m-d');

            $startDateStr = $request->query->get('start_date', $defaultStart);
            $endDateStr   = $request->query->get('end_date', $defaultEnd);
            $search       = trim((string) $request->query->get('search', ''));

            try {
                $startDate = new \DateTime($startDateStr . ' 00:00:00');
            } catch (\Exception) {
                $startDate = new \DateTime($defaultStart . ' 00:00:00');
                $startDateStr = $defaultStart;
            }

            try {
                $endDate = new \DateTime($endDateStr . ' 23:59:59');
            } catch (\Exception) {
                $endDate = new \DateTime($defaultEnd . ' 23:59:59');
                $endDateStr = $defaultEnd;
            }

            $qb = $em->createQueryBuilder();
            $qb->select('b', 'c', 's', 'st')
               ->from(BillingRecord::class, 'b')
               ->join('b.client', 'c')
               ->leftJoin('b.subscription', 's')
               ->leftJoin('s.subscriptionType', 'st')
               ->where('b.billingDate >= :start')
               ->andWhere('b.billingDate <= :end')
               ->andWhere('c.billable = :billable')
               ->setParameter('start', $startDate)
               ->setParameter('end', $endDate)
               ->setParameter('billable', true)
               ->orderBy('b.billingDate', 'ASC')
               ->addOrderBy('c.id', 'ASC');

            /** @var BillingRecord[] $records */
            $records = $qb->getQuery()->getResult();

            $searchNormalized = $this->normalizeSearchText($search);
            $searchTokens = preg_split('/[\s,]+/', $searchNormalized, -1, PREG_SPLIT_NO_EMPTY);

            /** @var \App\Entity\User|null $currentUser */
            $userRepo = $em->getRepository(\App\Entity\User::class);
            $currentUser = $userRepo->findOneBy([]);

            $creditor = [
                'name' => $currentUser?->getCompanyName() ?: 'GIMNASIO SANTA MARIA',
                'cif'  => $currentUser?->getTaxId() ?: 'B00000000',
                'iban' => $currentUser?->getCompanyIban() ?: '',
                'bic'  => $currentUser?->getCompanyBic() ?: 'NOTPROVIDED',
            ];

            $transactions = [];
            foreach ($records as $record) {
                $client = $record->getClient();
                if (!$client->isBillable()) {
                    continue;
                }

                $iban = $client->getIban();
                if (empty($iban)) {
                    continue; // Solo emitir para clientes con IBAN registrado
                }

                $name = (string) $client->getName();
                $surname = (string) $client->getSurname();
                $dni = (string) $client->getIdNumber();
                $phone = (string) $client->getPhone();

                if (!empty($searchTokens)) {
                    $cleanDni = str_replace(['.', '-', ' '], '', $dni);
                    $cleanPhone = str_replace(['.', '-', ' ', '+34'], '', $phone);
                    $cleanIban = str_replace(['.', '-', ' '], '', $iban);
                    $haystack = $this->normalizeSearchText(
                        $client->getId() . ' ' . $name . ' ' . $surname . ' ' .
                        $dni . ' ' . $cleanDni . ' ' . $phone . ' ' . $cleanPhone . ' ' .
                        $iban . ' ' . $cleanIban . ' ' .
                        $record->getDescription()
                    );

                    $matched = true;
                    foreach ($searchTokens as $token) {
                        $cleanToken = str_replace(['.', '-', '#'], '', $token);
                        if (!str_contains($haystack, $token) && ($cleanToken === '' || !str_contains($haystack, $cleanToken))) {
                            $matched = false;
                            break;
                        }
                    }
                    if (!$matched) {
                        continue;
                    }
                }

                $amount = (float) $record->getAmount();
                if ($amount <= 0.001) {
                    continue;
                }

                $concept = $record->getDescription() ?: ($record->getSubscription()?->getSubscriptionType()?->getName() ?? 'Cuota gimnasio');

                $transactions[] = [
                    'id' => $record->getId(),
                    'amount' => $amount,
                    'client_id' => $client->getId(),
                    'client_name' => trim($name . ' ' . $surname),
                    'client_iban' => $iban,
                    'mandate_date' => $client->getStartDate() ?? $record->getBillingDate(),
                    'concept' => $concept,
                ];
            }

            $xml = $sepaXmlService->generateDirectDebitXml($creditor, $transactions);

            $filename = sprintf('remesa_sepa_pain_008_%s_a_%s.xml', $startDateStr, $endDateStr);

            $response = new Response($xml);
            $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

            return $response;
        } catch (\Throwable $e) {
            return new Response('ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 500, ['Content-Type' => 'text/plain']);
        }
    }

    /**
     * Normaliza texto para búsqueda insensible a acentos, diacríticos y mayúsculas.
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

