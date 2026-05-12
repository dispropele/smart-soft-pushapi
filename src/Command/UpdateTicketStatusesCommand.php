<?php
namespace App\Command;

use App\Entity\LoanTicket;
use App\Service\RepledgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:tickets:update-statuses', description: 'Переводит билеты в grace/expired по дате')]
class UpdateTicketStatusesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private RepledgeService $repledgeService
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTime();

        // open → grace (срок прошёл, но grace ещё не истёк)
        $openExpired = $this->em->createQuery(
            'SELECT t FROM App\Entity\LoanTicket t WHERE t.status = :open AND t.returnDate < :now'
        )->setParameter('open', LoanTicket::STATUS_OPEN)->setParameter('now', $now)->getResult();

        foreach ($openExpired as $ticket) {
            $ticket->setStatus(LoanTicket::STATUS_GRACE);
            $output->writeln("→ grace: {$ticket->getTicketNumber()}");
        }

        // grace → expired (grace period также прошёл)
        $graceExpired = $this->em->createQuery(
            'SELECT t FROM App\Entity\LoanTicket t WHERE t.status = :grace'
        )->setParameter('grace', LoanTicket::STATUS_GRACE)->getResult();

        foreach ($graceExpired as $ticket) {
            if ($ticket->getGraceDaysLeft() <= 0) {
                $ticket->setStatus(LoanTicket::STATUS_EXPIRED);
                $output->writeln("→ expired: {$ticket->getTicketNumber()}");
            }
        }

        $this->em->flush();
        return Command::SUCCESS;
    }
}