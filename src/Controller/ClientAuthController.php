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
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 300;

    public function login(
        Request $request,
        SessionInterface $session,
        LoanTicketRepository $loanTicketRepository
    ): Response
    {
        $error = null;
        $attempts = (int) $session->get('client_login_attempts', 0);
        $lockedUntil = (int) $session->get('client_login_locked_until', 0);

        if ($lockedUntil && time() < $lockedUntil) {
            $wait = $lockedUntil - time();
            $minutes = (int) ceil($wait / 60);
            $error = sprintf('Слишком много попыток. Попробуйте через %d минут.', $minutes);
        }

        if ($request->isMethod('POST') && !$error) {
            $fullName = trim(preg_replace('/\s+/u', ' ', (string) $request->request->get('fullName', '')));
            $ticketNumber = trim((string) $request->request->get('ticketNumber', ''));

            if (!$fullName || !$ticketNumber) {
                $error = 'Пожалуйста, заполните все поля';
            } else {
                $ticket = $loanTicketRepository->findByTicketAndClient($ticketNumber, $fullName);

                if ($ticket instanceof LoanTicket) {
                    $session->remove('client_login_attempts');
                    $session->remove('client_login_locked_until');
                    $client = $ticket->getClient();
                    $session->set('client_id', (int) $client->getId());
                    $session->set('client_name', $client->getFullName());

                    return $this->redirectToRoute('app_client_cabinet');
                }

                $attempts++;
                $session->set('client_login_attempts', $attempts);
                if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
                    $lockUntil = time() + self::LOCKOUT_SECONDS;
                    $session->set('client_login_locked_until', $lockUntil);
                    $error = 'Слишком много неверных попыток. Повторите через 5 минут.';
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
