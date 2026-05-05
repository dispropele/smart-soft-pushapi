<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\LoanTicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cabinet')]
class ClientCabinetController extends AbstractController
{
    #[Route('', name: 'app_client_cabinet')]
    public function index(
        SessionInterface $session,
        ClientRepository $clientRepository,
        LoanTicketRepository $loanTicketRepository
    ): Response
    {
        $clientId = $session->get('client_id');

        if (!$clientId) {
            return $this->redirectToRoute('app_client_login');
        }

        $client = $clientRepository->find($clientId);

        if (!$client) {
            $session->invalidate();
            return $this->redirectToRoute('app_client_login');
        }

        $openTickets = $loanTicketRepository->findOpenByClient($client);

        return $this->render('client/cabinet.html.twig', [
            'client' => $client,
            'openTickets' => $openTickets,
        ]);
    }

    #[Route('/ticket/{ticketNumber}', name: 'app_client_ticket_detail')]
    public function ticketDetail(
        string $ticketNumber,
        SessionInterface $session,
        ClientRepository $clientRepository,
        LoanTicketRepository $loanTicketRepository
    ): Response
    {
        $clientId = $session->get('client_id');

        if (!$clientId) {
            return $this->redirectToRoute('app_client_login');
        }

        $client = $clientRepository->find($clientId);

        if (!$client) {
            $session->invalidate();
            return $this->redirectToRoute('app_client_login');
        }

        $ticket = $loanTicketRepository->findByNumber($ticketNumber);

        if (!$ticket || $ticket->getClient() !== $client) {
            throw $this->createNotFoundException('Залоговый билет не найден');
        }

        return $this->render('client/ticket_detail.html.twig', [
            'client' => $client,
            'ticket' => $ticket,
        ]);
    }
}
