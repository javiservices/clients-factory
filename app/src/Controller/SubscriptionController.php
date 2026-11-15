<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Form\SubscriptionType;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/subscription')]
final class SubscriptionController extends AbstractController
{
    #[Route(name: 'app_subscription_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_subscription_type_index');
    }

    /** Congela una suscripción: guarda la fecha de inicio de la congelación */
    #[Route('/{id}/freeze', name: 'app_subscription_freeze', methods: ['POST'])]
    public function freeze(
        Request $request,
        Subscription $subscription,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('freeze' . $subscription->getId(), $request->request->get('_token'))) {
            if (!$subscription->isFrozen()) {
                $subscription->setFrozenAt(new \DateTime());
                $em->flush();
                $this->addFlash('success', 'Suscripción congelada correctamente.');
            } else {
                $this->addFlash('info', 'La suscripción ya estaba congelada.');
            }
        }

        $clientId = $subscription->getClient()?->getId();
        return $this->redirectToRoute('app_client_show', ['id' => $clientId]);
    }

    /** Descongela una suscripción: extiende endDate por los días congelados */
    #[Route('/{id}/unfreeze', name: 'app_subscription_unfreeze', methods: ['POST'])]
    public function unfreeze(
        Request $request,
        Subscription $subscription,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('unfreeze' . $subscription->getId(), $request->request->get('_token'))) {
            if ($subscription->isFrozen()) {
                $frozenAt = $subscription->getFrozenAt();
                $now      = new \DateTime();
                $daysFrozen = (int) $frozenAt->diff($now)->days;

                // Extender la fecha de fin por los días congelados
                $newEnd = clone $subscription->getEndDate();
                $newEnd->modify('+' . $daysFrozen . ' days');
                $subscription->setEndDate($newEnd);
                $subscription->setFrozenAt(null);

                $em->flush();
                $this->addFlash('success', sprintf(
                    'Suscripción descongelada. Se han añadido %d día(s) al período.',
                    $daysFrozen
                ));
            } else {
                $this->addFlash('info', 'La suscripción no estaba congelada.');
            }
        }

        $clientId = $subscription->getClient()?->getId();
        return $this->redirectToRoute('app_client_show', ['id' => $clientId]);
    }
}

