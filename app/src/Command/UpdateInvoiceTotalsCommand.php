<?php

namespace App\Command;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-invoice-totals',
    description: 'Recalcula los totales de las facturas existentes basándose en los registros de facturación',
)]
class UpdateInvoiceTotalsCommand extends Command
{
    private InvoiceRepository $invoiceRepository;
    private InvoiceService $invoiceService;

    public function __construct(InvoiceRepository $invoiceRepository, InvoiceService $invoiceService)
    {
        $this->invoiceRepository = $invoiceRepository;
        $this->invoiceService = $invoiceService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $invoices = $this->invoiceRepository->findAll();
        $updatedCount = 0;

        foreach ($invoices as $invoice) {
            $oldTotal = $invoice->getTotalAmount();
            $this->invoiceService->updateInvoiceTotal($invoice);
            $newTotal = $invoice->getTotalAmount();

            if ($oldTotal != $newTotal) {
                $updatedCount++;
                $io->success("Factura {$invoice->getInvoiceNumber()}: {$oldTotal}€ -> {$newTotal}€");
            }
        }

        $io->info("Proceso completado. Facturas actualizadas: {$updatedCount}");

        return Command::SUCCESS;
    }
}
