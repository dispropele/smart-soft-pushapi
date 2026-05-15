<?php
namespace App\Controller\Admin;

use App\Entity\LoanTicket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class PrintController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/admin/print/ticket/{id}', name: 'admin_print_ticket')]
    public function printTicket(int $id): Response
    {
        $ticket = $this->em->find(LoanTicket::class, $id);
        if (!$ticket) {
            throw $this->createNotFoundException();
        }

        $response = $this->render('admin/print/loan_ticket.html.twig', ['ticket' => $ticket]);
        // Не кешируем
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }
}
