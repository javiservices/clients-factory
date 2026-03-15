<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/invoice')]
class InvoiceController extends AbstractController
{
    #[Route('/', name: 'invoice_index', methods: ['GET'])]
    public function index(Request $request, InvoiceRepository $invoiceRepository): Response
    {
        // Facturación oculta - redirigir al dashboard
        return $this->redirectToRoute('dashboard');
    }

    #[Route('/new', name: 'invoice_new', methods: ['GET', 'POST'])]
    public function new(Request $request, InvoiceService $invoiceService): Response
    {
        // Facturación oculta - redirigir al dashboard
        return $this->redirectToRoute('dashboard');
    }

    #[Route('/{id}', name: 'invoice_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Invoice $invoice): Response
    {
        // Facturación oculta - redirigir al dashboard
        return $this->redirectToRoute('dashboard');
    }

    #[Route('/{id}/delete', name: 'invoice_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        // Facturación oculta - redirigir al dashboard
        return $this->redirectToRoute('dashboard');
    }

    #[Route('/{id}/download', name: 'invoice_download', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function download(Invoice $invoice, InvoiceService $invoiceService): Response
    {
        // Facturación oculta - redirigir al dashboard
        return $this->redirectToRoute('dashboard');
    }

    #[Route('/{id}/download-pdf', name: 'invoice_download_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function downloadPdf(Invoice $invoice, InvoiceService $invoiceService): Response
    {
        // Facturación oculta - redirigir al dashboard
        return $this->redirectToRoute('dashboard');
    }

    #[Route('/download-all', name: 'invoice_download_all', methods: ['GET'])]
    public function downloadAll(Request $request, InvoiceRepository $invoiceRepository, InvoiceService $invoiceService): Response
    {
        // Facturación oculta - redirigir al dashboard
        return $this->redirectToRoute('dashboard');
    }
}
