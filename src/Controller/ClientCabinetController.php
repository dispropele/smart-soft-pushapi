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
        ClientRepository $clientRepo,
        LoanTicketRepository $ticketRepo
    ): Response {
        $clientId = $session->get('client_id');
        if ($clientId === null || $clientId === '') {
            return $this->redirectToRoute('app_client_login');
        }

        $client = $clientRepo->find((int) $clientId);
        if (!$client) {
            $session->invalidate();
            return $this->redirectToRoute('app_client_login');
        }

        // Активные: open + grace
        $activeTickets = $ticketRepo->findActiveByClient($client);
        // Историческими — closed, expired, repledged
        $historyTickets = $ticketRepo->findHistoryByClient($client);

        return $this->render('client/cabinet.html.twig', [
            'client'         => $client,
            'activeTickets'  => $activeTickets,
            'historyTickets' => $historyTickets,
        ]);
    }

    #[Route('/ticket/{ticketNumber}', name: 'app_client_ticket_detail')]
    public function ticketDetail(
        string $ticketNumber,
        SessionInterface $session,
        ClientRepository $clientRepo,
        LoanTicketRepository $ticketRepo
    ): Response {
        $clientId = $session->get('client_id');
        if ($clientId === null || $clientId === '') {
            return $this->redirectToRoute('app_client_login');
        }

        $client = $clientRepo->find((int) $clientId);
        if (!$client) {
            $session->invalidate();
            return $this->redirectToRoute('app_client_login');
        }

        $ticket = $ticketRepo->findByNumber($ticketNumber);
        $ticketClientId = $ticket?->getClient()?->getId();
        if (!$ticket || $ticketClientId === null || $ticketClientId !== $client->getId()) {
            throw $this->createNotFoundException('Залоговый билет не найден');
        }

        return $this->render('client/ticket_detail.html.twig', [
            'client' => $client,
            'ticket' => $ticket,
        ]);
    }
}