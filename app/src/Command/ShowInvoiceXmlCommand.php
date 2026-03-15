<?php

namespace App\Command;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:show-invoice-xml',
    description: 'Muestra el XML de una factura específica',
)]
class ShowInvoiceXmlCommand extends Command
{
    private InvoiceRepository $invoiceRepository;
    private InvoiceService $invoiceService;

    public function __construct(InvoiceRepository $invoiceRepository, InvoiceService $invoiceService)
    {
        $this->invoiceRepository = $invoiceRepository;
        $this->invoiceService = $invoiceService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('invoice-number', InputArgument::REQUIRED, 'Número de factura')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $invoiceNumber = $input->getArgument('invoice-number');

        $invoice = $this->invoiceRepository->findOneBy(['invoiceNumber' => $invoiceNumber]);

        if (!$invoice) {
            $io->error("Factura {$invoiceNumber} no encontrada");
            return Command::FAILURE;
        }

        $xml = $this->invoiceService->generateXml($invoice);

        $io->title("XML de la factura {$invoiceNumber}");
        $io->writeln($xml);

        return Command::SUCCESS;
    }
}
