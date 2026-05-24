<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\LoanTicket;
use App\Entity\PledgedItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:loan-data', description: 'Создаёт тестовые данные для ломбарда')]
class SeedLoanDataCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Создаём клиента
        $client = new Client();
        $client->setFullName('Иван Петрович Сидоров');
        $client->setPassportNumber('4512345678');
        $client->setPassportSeries('67 09');
        $client->setAddress('г. Москва, ул. Ленина, д. 1, кв. 10');
        $client->setPhone('+7 (999) 123-45-67');
        $client->setEmail('ivan@example.com');

        $this->em->persist($client);

        // Создаём залоговый билет
        $ticket = new LoanTicket();
        $ticket->setTicketNumber('ЛБ-2026-' . date('md-') . rand(1000, 9999));
        $ticket->setClient($client);
        $ticket->setLoanAmount('50000');
        $ticket->setInterestRate('2.5');
        $ticket->setIssuedAt(new \DateTime('now'));
        $ticket->setReturnDate(new \DateTime('+3 months'));
        $ticket->setStatus(LoanTicket::STATUS_OPEN);
        $ticket->setNotes('Залог принят на хранение. Содержание в сейфе.');

        $this->em->persist($ticket);

        // Создаём заложенные предметы
        $metals = [
            'Золото' => 1,
            'Серебро' => 2,
            'Платина' => 3,
        ];

        for ($i = 0; $i < 3; $i++) {
            $item = new PledgedItem();
            $item->setName(['Кольцо золотое', 'Браслет серебряный', 'Цепочка платиновая'][$i]);
            $item->setDescription('Украшение в хорошем состоянии');
            $item->setEstimatedValue((string)(15000 + $i * 10000));
            $item->setCondition('Хорошее');
            $item->setLoanTicket($ticket);

            $this->em->persist($item);
        }

        $this->em->flush();

        $io->success('Тестовые данные успешно созданы!');
        $io->writeln("Данные клиента:");
        $io->writeln("  ФИО: {$client->getFullName()}");
        $io->writeln("  Паспорт: {$client->getPassportNumber()}");
        $io->writeln("  Залоговый билет: {$ticket->getTicketNumber()}");

        return Command::SUCCESS;
    }
}
