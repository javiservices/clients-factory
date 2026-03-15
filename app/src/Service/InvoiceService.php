<?php

namespace App\Service;

use App\Entity\BillingRecord;
use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\User;
use App\Repository\BillingRecordRepository;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

class InvoiceService
{
    private EntityManagerInterface $entityManager;
    private InvoiceRepository $invoiceRepository;
    private BillingRecordRepository $billingRecordRepository;
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $entityManager,
        InvoiceRepository $invoiceRepository,
        BillingRecordRepository $billingRecordRepository,
        Environment $twig
    ) {
        $this->entityManager = $entityManager;
        $this->invoiceRepository = $invoiceRepository;
        $this->billingRecordRepository = $billingRecordRepository;
        $this->twig = $twig;
    }

    public function generateInvoiceForClient(Client $client, User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): ?Invoice
    {
        if (!$client->isBillable()) {
            return null;
        }

        $billingRecords = $this->billingRecordRepository->findByClientAndPeriod($client, $startDate, $endDate);

        if (empty($billingRecords)) {
            return null;
        }

        // Verificar que el usuario tenga datos de empresa configurados
        if (!$user->getCompanyName() || !$user->getTaxId()) {
            throw new \Exception('Company data not configured in user profile');
        }

        $totalAmount = 0;
        foreach ($billingRecords as $record) {
            $totalAmount += $record->getAmount();
        }

        // Calcular IVA (21% por defecto)
        $ivaRate = 0.21;
        $netAmount = $totalAmount / (1 + $ivaRate);
        $taxAmount = $netAmount * $ivaRate;

        $invoice = new Invoice();
        $invoice->setInvoiceNumber($this->generateInvoiceNumber());
        $invoice->setClient($client);
        $invoice->setIssueDate(new \DateTime());
        $invoice->setPeriodStart($startDate);
        $invoice->setPeriodEnd($endDate);
        $invoice->setTotalAmount($totalAmount);
        $invoice->setNetAmount($netAmount);
        $invoice->setTaxAmount($taxAmount);
        $invoice->setStatus('paid');

        foreach ($billingRecords as $record) {
            $record->setInvoice($invoice);
            $this->entityManager->persist($record);
        }

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    public function generateInvoiceForBillingRecord(BillingRecord $billingRecord, User $user): Invoice
    {
        // Verificar que el usuario tenga datos de empresa configurados
        if (!$user->getCompanyName() || !$user->getTaxId()) {
            throw new \Exception('Company data not configured in user profile');
        }

        $totalAmount = $billingRecord->getAmount();

        // Calcular IVA (21% por defecto)
        $ivaRate = 0.21;
        $netAmount = $totalAmount / (1 + $ivaRate);
        $taxAmount = $netAmount * $ivaRate;

        $invoice = new Invoice();
        $invoice->setInvoiceNumber($this->generateInvoiceNumber());
        $invoice->setClient($billingRecord->getClient());
        $invoice->setIssueDate(new \DateTime());
        $invoice->setPeriodStart($billingRecord->getPeriodStart());
        $invoice->setPeriodEnd($billingRecord->getPeriodEnd());
        $invoice->setTotalAmount($totalAmount);
        $invoice->setNetAmount($netAmount);
        $invoice->setTaxAmount($taxAmount);
        $invoice->setStatus('paid');

        $billingRecord->setInvoice($invoice);
        $billingRecord->setIsBilled(true);

        $this->entityManager->persist($invoice);
        $this->entityManager->persist($billingRecord);
        $this->entityManager->flush();

        return $invoice;
    }

    private function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $lastInvoice = $this->invoiceRepository->findLastInvoiceOfYear($year);
        $number = $lastInvoice ? intval(substr($lastInvoice->getInvoiceNumber(), -4)) + 1 : 1;
        return sprintf('INV-%s-%04d', $year, $number);
    }

    public function generateXml(Invoice $invoice, User $user): string
    {
        // Generación completa de XML según VERI*FACTU
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<FacturaE xmlns="http://www.agenciatributaria.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/VFactura.xsd">' . "\n";

        // Cabecera
        $xml .= '  <Cabecera>' . "\n";
        $xml .= '    <Serie>' . htmlspecialchars($invoice->getInvoiceNumber()) . '</Serie>' . "\n";
        $xml .= '    <NumFactura>' . htmlspecialchars($invoice->getInvoiceNumber()) . '</NumFactura>' . "\n";
        $xml .= '    <FechaExpedicionFactura>' . ($invoice->getIssueDate() ? $invoice->getIssueDate()->format('d-m-Y') : date('d-m-Y')) . '</FechaExpedicionFactura>' . "\n";
        $xml .= '    <TipoFactura>F1</TipoFactura>' . "\n"; // Factura completa
        $xml .= '  </Cabecera>' . "\n";

        // Datos del Emisor (Empresa)
        $xml .= '  <DatosEmisor>' . "\n";
        $xml .= '    <NombreRazon>' . htmlspecialchars($user->getCompanyName()) . '</NombreRazon>' . "\n";
        $xml .= '    <NIF>' . htmlspecialchars($user->getTaxId()) . '</NIF>' . "\n";
        if ($user->getCompanyAddress()) {
            $xml .= '    <DomicilioEmisor>' . htmlspecialchars($user->getCompanyAddress()) . '</DomicilioEmisor>' . "\n";
        }
        $xml .= '  </DatosEmisor>' . "\n";

        // Datos del Receptor (Cliente)
        $client = $invoice->getClient();
        if ($client) {
            $xml .= '  <DatosReceptor>' . "\n";
            $xml .= '    <NombreRazon>' . htmlspecialchars($client->getName() . ' ' . $client->getSurname()) . '</NombreRazon>' . "\n";
            $xml .= '    <NIF>' . htmlspecialchars($client->getIdNumber()) . '</NIF>' . "\n";
            if ($client->getAddress()) {
                $xml .= '    <DomicilioReceptor>' . htmlspecialchars($client->getAddress()) . '</DomicilioReceptor>' . "\n";
            }
            $xml .= '  </DatosReceptor>' . "\n";
        }

        // Detalles de la factura
        $xml .= '  <TipoDesglose>' . "\n";
        $xml .= '    <DesgloseFactura>' . "\n";
        $xml .= '      <Sujeta>' . "\n";

        // Líneas de IVA (simplificado - asumiendo un solo tipo de IVA)
        $xml .= '        <NoExenta>' . "\n";
        $xml .= '          <TipoNoExenta>S1</TipoNoExenta>' . "\n"; // Sujeta
        $xml .= '          <DesgloseIVA>' . "\n";
        $xml .= '            <DetalleIVA>' . "\n";
        $xml .= '              <TipoImpositivo>21.0</TipoImpositivo>' . "\n"; // IVA 21%
        $xml .= '              <BaseImponible>' . number_format($invoice->getNetAmount(), 2, '.', '') . '</BaseImponible>' . "\n";
        $xml .= '              <CuotaIVA>' . number_format($invoice->getTaxAmount(), 2, '.', '') . '</CuotaIVA>' . "\n";
        $xml .= '              <TipoRecargoEquivalencia>0.0</TipoRecargoEquivalencia>' . "\n";
        $xml .= '              <CuotaRecargoEquivalencia>0.00</CuotaRecargoEquivalencia>' . "\n";
        $xml .= '            </DetalleIVA>' . "\n";
        $xml .= '          </DesgloseIVA>' . "\n";
        $xml .= '        </NoExenta>' . "\n";

        $xml .= '      </Sujeta>' . "\n";
        $xml .= '    </DesgloseFactura>' . "\n";
        $xml .= '  </TipoDesglose>' . "\n";

        // Importes totales
        $xml .= '  <ImporteTotalFactura>' . number_format($invoice->getTotalAmount(), 2, '.', '') . '</ImporteTotalFactura>' . "\n";
        $xml .= '  <CuotaTotalIVA>' . number_format($invoice->getTaxAmount(), 2, '.', '') . '</CuotaTotalIVA>' . "\n";
        $xml .= '  <BaseImponibleTotal>' . number_format($invoice->getNetAmount(), 2, '.', '') . '</BaseImponibleTotal>' . "\n";

        // Descripción de las operaciones
        $xml .= '  <DescripcionOperacion>Servicios de suscripción</DescripcionOperacion>' . "\n";

        // Fecha de operación (igual a fecha de expedición)
        $xml .= '  <FechaOperacion>' . ($invoice->getIssueDate() ? $invoice->getIssueDate()->format('d-m-Y') : date('d-m-Y')) . '</FechaOperacion>' . "\n";

        $xml .= '</FacturaE>' . "\n";

        return $xml;
    }

    public function updateInvoiceTotal(Invoice $invoice): void
    {
        $billingRecords = $this->billingRecordRepository->findByInvoice($invoice);

        $totalAmount = 0;
        foreach ($billingRecords as $record) {
            $totalAmount += $record->getAmount();
        }

        // Recalcular IVA
        $ivaRate = 0.21;
        $netAmount = $totalAmount / (1 + $ivaRate);
        $taxAmount = $netAmount * $ivaRate;

        $invoice->setTotalAmount($totalAmount);
        $invoice->setNetAmount($netAmount);
        $invoice->setTaxAmount($taxAmount);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();
    }

    public function generatePdf(Invoice $invoice, User $user): string
    {
        $html = $this->twig->render('invoice/pdf.html.twig', [
            'invoice' => $invoice,
            'user' => $user,
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
