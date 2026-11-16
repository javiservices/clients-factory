<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PwaController extends AbstractController
{
    #[Route('/app', name: 'app_pwa')]
    #[Route('/door', name: 'app_door')]
    public function index(): Response
    {
        return $this->render('pwa/index.html.twig');
    }

    #[Route('/manifest.webmanifest', name: 'app_pwa_manifest')]
    public function manifest(): Response
    {
        $manifest = [
            'name'             => 'SantaDoor · Santamaría Gym',
            'short_name'        => 'SantaDoor',
            'description'       => 'Control de Acceso y Apertura de Torno para Socios',
            'start_url'         => '/app',
            'display'           => 'standalone',
            'orientation'       => 'portrait',
            'background_color'  => '#0F172A',
            'theme_color'       => '#D72638',
            'icons'             => [
                [
                    'src'     => '/build/images/icon-192.png',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src'     => '/build/images/icon-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable'
                ]
            ]
        ];

        return new Response(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600'
        ]);
    }
}

