<?php
namespace App\Command;

use App\Entity\LoanTicket;
use App\Entity\SystemLog;

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
        $result = $this->repledgeService->updateTicketStatuses();

        foreach ($result['grace'] as $ticket) {
            $output->writeln("[grace]   {$ticket->getTicketNumber()}");
        }

        foreach ($result['sale'] as $ticket) {
            $output->writeln("[sale]    {$ticket->getTicketNumber()} → передаётся на реализацию");
        }

        $output->writeln('Готово.');
        return Command::SUCCESS;
    }
}