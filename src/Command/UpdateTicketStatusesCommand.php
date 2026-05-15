<?php
namespace App\Command;

use App\Entity\LoanTicket;
use App\Service\RepledgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:tickets:update-statuses',
    description: 'Переводит билеты в grace/expired и запускает реализацию'
)]
class UpdateTicketStatusesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private RepledgeService $repledgeService
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTime();

        // 1. open → grace (основной срок истёк)
        $openExpired = $this->em->createQuery(
            'SELECT t FROM App\Entity\LoanTicket t
             WHERE t.status = :open AND t.returnDate < :now'
        )->setParameter('open', LoanTicket::STATUS_OPEN)
         ->setParameter('now', $now)
         ->getResult();

        foreach ($openExpired as $ticket) {
            $ticket->setStatus(LoanTicket::STATUS_GRACE);
            $output->writeln("[grace]   {$ticket->getTicketNumber()}");
        }
        $this->em->flush();

        // 2. grace → expired + moveToSale (grace period тоже истёк)
        $graceTickets = $this->em->createQuery(
            'SELECT t FROM App\Entity\LoanTicket t WHERE t.status = :grace'
        )->setParameter('grace', LoanTicket::STATUS_GRACE)->getResult();

        foreach ($graceTickets as $ticket) {
            if ($ticket->getGraceDaysLeft() <= 0) {
                $output->writeln("[sale]    {$ticket->getTicketNumber()} → передаётся на реализацию");
                $this->repledgeService->moveToSale($ticket);
            }
        }

        $output->writeln('Готово.');
        return Command::SUCCESS;
    }
}