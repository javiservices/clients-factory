<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Filesystem\Filesystem;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/backup')]
final class BackupController extends AbstractController
{
    private Filesystem $filesystem;
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->filesystem = new Filesystem();
        $this->entityManager = $entityManager;
    }
    #[Route('/', name: 'app_backup_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Verificar que estamos en modo hidden
        $session = $request->getSession();
        if ($session->get('system_mode') !== 'hidden') {
            $this->addFlash('error', 'Esta sección solo está disponible en modo oculto.');
            return $this->redirectToRoute('app_client_index');
        }

        $backupDir = $this->getParameter('kernel.project_dir') . '/var/backups';
        $filesystem = new Filesystem();

        $backups = [];
        if ($this->filesystem->exists($backupDir)) {
            $files = glob($backupDir . '/non_billable_clients_backup_*.sql');
            foreach ($files as $file) {
                $backups[] = [
                    'filename' => basename($file),
                    'filepath' => $file,
                    'size' => filesize($file),
                    'created' => filemtime($file),
                ];
            }
            // Ordenar por fecha de creación (más reciente primero)
            usort($backups, function($a, $b) {
                return $b['created'] - $a['created'];
            });
        }

        return $this->render('backup/index.html.twig', [
            'backups' => $backups,
        ]);
    }

    #[Route('/restore/{filename}', name: 'app_backup_restore', methods: ['POST'])]
    public function restore(Request $request, string $filename): Response
    {
        // Verificar que estamos en modo hidden
        $session = $request->getSession();
        if ($session->get('system_mode') !== 'hidden') {
            $this->addFlash('error', 'Esta acción solo está disponible en modo oculto.');
            return $this->redirectToRoute('app_client_index');
        }

        $backupDir = $this->getParameter('kernel.project_dir') . '/var/backups';
        $backupFile = $backupDir . '/' . $filename;

        if (!$this->filesystem->exists($backupFile)) {
            $this->addFlash('error', 'Archivo de backup no encontrado.');
            return $this->redirectToRoute('app_backup_index');
        }

        try {
            // Leer el contenido del archivo SQL
            $sql = file_get_contents($backupFile);

            // Ejecutar el SQL usando Doctrine
            $connection = $this->entityManager->getConnection();
            $connection->executeStatement($sql);

            $this->addFlash('success', 'Backup restaurado exitosamente.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al restaurar el backup: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_backup_index');
    }

    #[Route('/download/{filename}', name: 'app_backup_download', methods: ['GET'])]
    public function download(Request $request, string $filename): Response
    {
        // Verificar que estamos en modo hidden
        $session = $request->getSession();
        if ($session->get('system_mode') !== 'hidden') {
            $this->addFlash('error', 'Esta acción solo está disponible en modo oculto.');
            return $this->redirectToRoute('app_client_index');
        }

        $backupDir = $this->getParameter('kernel.project_dir') . '/var/backups';
        $backupFile = $backupDir . '/' . $filename;

        if (!$this->filesystem->exists($backupFile)) {
            throw $this->createNotFoundException('Archivo no encontrado');
        }

        return $this->file($backupFile);
    }

    #[Route('/delete/{filename}', name: 'app_backup_delete', methods: ['POST'])]
    public function delete(Request $request, string $filename): Response
    {
        // Verificar que estamos en modo hidden
        $session = $request->getSession();
        if ($session->get('system_mode') !== 'hidden') {
            $this->addFlash('error', 'Esta acción solo está disponible en modo oculto.');
            return $this->redirectToRoute('app_client_index');
        }

        $backupDir = $this->getParameter('kernel.project_dir') . '/var/backups';
        $backupFile = $backupDir . '/' . $filename;

        if ($this->filesystem->exists($backupFile)) {
            $this->filesystem->remove($backupFile);
            $this->addFlash('success', 'Backup eliminado exitosamente.');
        } else {
            $this->addFlash('error', 'Archivo de backup no encontrado.');
        }

        return $this->redirectToRoute('app_backup_index');
    }
}
