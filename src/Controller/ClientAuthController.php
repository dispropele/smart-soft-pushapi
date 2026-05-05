<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\LoanTicket;
use App\Repository\LoanTicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class ClientAuthController extends AbstractController
{
    #[Route('/login', name: 'app_client_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        SessionInterface $session,
        LoanTicketRepository $loanTicketRepository
    ): Response
    {
        $error = null;

        if ($request->isMethod('POST')) {
            $fullName = trim($request->request->get('fullName', ''));
            $ticketNumber = trim($request->request->get('ticketNumber', ''));

            if (!$fullName || !$ticketNumber) {
                $error = 'Пожалуйста, заполните все поля';
            } else {
                $ticket = $loanTicketRepository->findByTicketAndClient($ticketNumber, $fullName);

                if ($ticket instanceof LoanTicket) {
                    $client = $ticket->getClient();
                    $session->set('client_id', $client->getId());
                    $session->set('client_name', $client->getFullName());

                    return $this->redirectToRoute('app_client_cabinet');
                } else {
                    $error = 'Залоговый билет не найден или ФИО не совпадает';
                }
            }
        }

        return $this->render('client/login.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_client_logout')]
    public function logout(SessionInterface $session): Response
    {
        $session->remove('client_id');
        $session->remove('client_name');

        return $this->redirectToRoute('app_catalog');
    }

    private function getClientFromSession(SessionInterface $session): ?Client
    {
        $clientId = $session->get('client_id');
        if ($clientId) {
            // В реальном приложении нужно получить из репозитория
            return null;
        }

        return null;
    }
}
