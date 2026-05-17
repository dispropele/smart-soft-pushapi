<?php
namespace App\Command;

use App\Entity\{Admin, Category, Client, Currency, GoodType, Insert, InsertType,
    LoanTicket, Metal, MetalColor, MetalStandard, PledgedItem, Tariff};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed:control-example', description: 'Добавление в систему контрольного примера')]
class SeedControlExampleCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Наполнение БД контрольным примером');

        // Очистка таблиц
        $io->section('Очистка таблиц...');
        $conn = $this->em->getConnection();
        $tables = [
            'system_logs', 'pledged_item_images', 'pledged_items',
            'loan_tickets', 'clients', 'tariffs',
            'good_types', 'inserts', 'insert_types',
            'metal_colors', 'metal_standads', 'metals', // В миграциях у тебя была опечатка metal_standads
            'currencies', 'categories', 'admins',
        ];
        foreach ($tables as $t) {
            $conn->executeStatement("TRUNCATE TABLE $t CASCADE");
        }
        $io->success('Таблицы очищены');

        // Категории (Таблица 1.9)
        $io->section('Категории...');
        $cats = [];
        foreach (['Кольца','Серьги','Цепи','Браслеты','Кулоны','Подвески','Часы'] as $name) {
            $c = (new Category())->setName($name);
            $this->em->persist($c);
            $cats[$name] = $c;
        }
        $this->em->flush();

        // Металлы (Таблица 1.10)
        $io->section('Металлы и пробы...');
        $metals = [];
        foreach (['Золото','Серебро','Платина','Палладий'] as $name) {
            $m = (new Metal())->setName($name);
            $this->em->persist($m);
            $metals[$name] = $m;
        }
        $this->em->flush();

        // Пробы (Таблица 1.11)
        $standards = [];
        $stdData = [
            'Золото'   => ['375','583','585','750','999'],
            'Серебро'  => ['875','925','960'],
            'Платина'  => ['950'],
            'Палладий' => ['500','850'],
        ];
        foreach ($stdData as $mName => $probes) {
            foreach ($probes as $p) {
                $ms = (new MetalStandard())->setMetal($metals[$mName])->setName($p);
                $this->em->persist($ms);
                $standards["$mName $p"] = $ms;
            }
        }
        $this->em->flush();

        // Цвета металлов (Таблица 1.12)
        $colorData = [
            ['Золото','Красное','red_gold'],
            ['Золото','Белое','white_gold'],
            ['Золото','Желтое','yellow_gold'],
            ['Серебро','Родированное','rhodium_silver'],
            ['Серебро','Оксидированное','oxidized_silver'],
        ];
        foreach ($colorData as [$mName, $colorName, $code]) {
            $mc = (new MetalColor())->setMetal($metals[$mName])->setName($colorName)->setCode($code);
            $this->em->persist($mc);
        }
        $this->em->flush();

        // Типы вставок (Таблица 1.13)
        $io->section('Вставки...');
        $itypes = [];
        foreach (['Драгоценный','Полудрагоценный','Синтетический','Органическая'] as $name) {
            $it = (new InsertType())->setName($name);
            $this->em->persist($it);
            $itypes[$name] = $it;
        }
        $this->em->flush();

        // Вставки (Таблица 1.14)
        $insertData = [
            ['Бриллиант','Драгоценный'],
            ['Изумруд','Драгоценный'],
            ['Топаз','Полудрагоценный'],
            ['Гранат','Полудрагоценный'],
            ['Фианит','Синтетический'],
            ['Жемчуг','Органическая'],
        ];
        foreach ($insertData as [$name, $type]) {
            $ins = (new Insert())->setName($name)->setInsertType($itypes[$type]);
            $this->em->persist($ins);
        }
        $this->em->flush();

        // Валюты (Таблица 1.15)
        $io->section('Валюты...');
        $currData = [['₽','Рубль'],['$','Доллар США'],['€','Евро']];
        $rub = null;
        foreach ($currData as [$code, $name]) {
            $cur = (new Currency())->setCurrency($code)->setName($name);
            $this->em->persist($cur);
            if ($code === '₽') $rub = $cur;
        }
        $this->em->flush();

        // Тарифы (Таблица 1.16)
        $io->section('Тарифы...');
        $tariffs = [];
        $tariffData = [
            ['Стандартный','0.4000'],
            ['Льготный','0.2500'],
            ['VIP','0.1500'],
            ['Срочный','0.6000'],
        ];
        foreach ($tariffData as [$name, $rate]) {
            $t = (new Tariff())->setName($name)->setDailyRate($rate)->setIsActive(true);
            $this->em->persist($t);
            $tariffs[$name] = $t;
        }
        $this->em->flush();

        // Администраторы (Таблица 1.17)
        $io->section('Администраторы...');
        $adminData = [
            ['admin_main','master@lombard.ru','Admin@12345'],
            ['ivanova_realty','manager1@lombard.ru','Admin@12345'],
            ['petrov_expert','expert@lombard.ru','Admin@12345'],
        ];
        foreach ($adminData as [$login, $email, $pass]) {
            $admin = new Admin();
            $admin->setUsername($login)->setEmail($email)
                ->setPasswordHash($this->hasher->hashPassword($admin, $pass));
            $this->em->persist($admin);
        }
        $this->em->flush();

        // Виды изделий (Таблица 1.18)
        $io->section('Виды изделий...');
        $gtData = [
            ['Кольца','Кольцо обручальное',false,null],
            ['Кольца','Кольцо «Помолвочное»',true,'rhodium'],
            ['Кольца','Перстень мужской',true,'oxidation'],
            ['Кольца','Кольцо «Дорожка»',true,'rhodium'],
            ['Серьги','Серьги-пусеты',true,'rhodium'],
            ['Серьги','Серьги с подвесками',true,'gold_plating'],
            ['Цепи','Цепь «Бисмарк»',false,null],
            ['Цепи','Цепь «Якорная»',false,'gold_plating'],
            ['Браслеты','Браслет плетения «Нонна»',false,null],
            ['Браслеты','Браслет жесткий',false,'rhodium'],
            ['Кулоны','Кулон «Знак зодиака»',false,'oxidation'],
            ['Подвески','Крестик нательный',false,'gold_plating'],
            ['Подвески','Подвеска «Сердце»',true,'enamel'],
            ['Часы','Часы наручные золотые',false,null],
            ['Часы','Часы коллекционные',true,'rhodium'],
        ];
        $goodTypes = [];
        foreach ($gtData as [$catName, $name, $hasStones, $coating]) {
            $gt = new GoodType();
            $gt->setName($name)->setCategory($cats[$catName])
                ->setHasStones($hasStones)->setCoating($coating)
                ->setCode(null);
            $this->em->persist($gt);
            $goodTypes[$name] = $gt;
        }
        $this->em->flush();

        // Клиенты (Таблица 1.19)
        $io->section('Клиенты...');
        $clientData = [
            ['Иванов Иван Иванович','123456','4510','89001112233','г. Москва, ул. Ленина, 1'],
            ['Петров Петр Петрович','654321','4511','89004445566','г. Москва, ул. Мира, 15'],
            ['Сидорова Анна Павловна','789012','4612','89117778899','г. С-Петербург, пр. Славы, 2'],
            ['Кузнецов Олег Игоревич','345678','5014','89200001122','г. Казань, ул. Баумана, 5'],
            ['Смирнова Елена Викторовна','556677','4510','89503334455','г. Москва, ул. Тверская, 10'],
            ['Васильев Сергей Андреевич','112233','4508','89601234567','г. Новосибирск, ул. Горького, 3'],
            ['Морозова Ольга Сергеевна','998877','4515','89059876543','г. Екатеринбург, ул. Малышева, 8'],
            ['Павлов Игорь Владимирович','443322','6010','89165554433','г. Москва, ул. Полевая, 12'],
            ['Соколов Максим Юрьевич','224466','4511','89031110022','г. С-Петербург, ул. Садовая, 21'],
            ['Федорова Дарья Алексеевна','775533','4614','89092223344','г. Казань, ул. Пушкина, 4'],
            ['Попов Андрей Николаевич','884422','4510','89815556677','г. Москва, пр. Мира, 101'],
            ['Семенова Инна Игоревна','339911','5012','89214443322','г. Екатеринбург, ул. Ленина, 45'],
            ['Волков Артем Дмитриевич','121212','4518','89008889900','г. Новосибирск, ул. Кирова, 11'],
            ['Борисова Яна Олеговна','343434','4509','89110001122','г. С-Петербург, ул. Ленсовета, 5'],
            ['Макаров Денис Петрович','565656','4610','89057778899','г. Казань, ул. Декабристов, 19'],
        ];
        $clients = [];
        foreach ($clientData as [$fullName, $passNum, $passSer, $phone, $addr]) {
            $cl = new Client();
            $cl->setFullName($fullName)->setPassportNumber($passNum)
                ->setPassportSeries($passSer)->setPhone($phone)->setAddress($addr);
            $this->em->persist($cl);
            $clients[$fullName] = $cl;
        }
        $this->em->flush();

        // Залоговые билеты (Таблица 1.20)
        $io->section('Залоговые билеты...');
        $ticketData = [
            ['ЛБ-001','2025-04-01','Иванов Иван Иванович','Стандартный','25000.00','closed',true],
            ['ЛБ-002','2025-04-02','Петров Петр Петрович','Стандартный','12000.00','open',false],
            ['ЛБ-003','2025-04-05','Сидорова Анна Павловна','Льготный','45000.00','open',false],
            ['ЛБ-004','2025-04-07','Смирнова Елена Викторовна','Стандартный','8500.00','expired',true],
            ['ЛБ-005','2025-04-10','Васильев Сергей Андреевич','VIP','180000.00','open',false],
            ['ЛБ-006','2025-04-12','Федорова Дарья Алексеевна','Стандартный','32000.00','closed',true],
            ['ЛБ-007','2025-04-15','Семенова Инна Игоревна','Льготный','6000.00','open',false],
            ['ЛБ-008','2025-04-18','Макаров Денис Петрович','Стандартный','19500.00','expired',true],
            ['ЛБ-009','2025-04-20','Кузнецов Олег Игоревич','Срочный','60000.00','open',false],
            ['ЛБ-010','2025-04-22','Павлов Игорь Владимирович','VIP','95000.00','open',false],
        ];
        $tickets = [];
        foreach ($ticketData as [$num, $dateStr, $clientName, $tariffName, $amount, $status, $isClosed]) {
            $tariff = $tariffs[$tariffName];
            $issuedAt = new \DateTime($dateStr);
            $returnDate = (clone $issuedAt)->modify('+30 days');

            $t = new LoanTicket();
            $t->setTicketNumber($num)
                ->setClient($clients[$clientName])
                ->setLoanAmount($amount)
                ->setTariff($tariff)
                ->setDailyInterestRate($tariff->getDailyRate())
                ->setInterestRate($tariff->getMonthlyRate()) // Если метода нет, удали эту строку
                ->setIssuedAt($issuedAt)
                ->setReturnDate($returnDate)
                ->setGraceDays(30)
                ->setStatus($status);

            if ($isClosed) {
                $t->setClosedAt((clone $returnDate)->modify('+5 days'));
            }

            $this->em->persist($t);
            $tickets[$num] = $t;
        }
        $this->em->flush();

        // Предметы залога (Таблица 1.21)
        $io->section('Предметы залога...');
        $itemsData = [
            ['ЛБ-001','Кольцо обручальное','Золото 585','3.50','18500.00','redeemed'],
            ['ЛБ-001','Цепь «Бисмарк»','Золото 585','15.20','72000.00','redeemed'],
            ['ЛБ-001','Крестик нательный','Серебро 925','4.00','2500.00','redeemed'],
            ['ЛБ-001','Браслет плетения «Нонна»','Серебро 875','9.00','4500.00','redeemed'],
            ['ЛБ-002','Кольцо «Помолвочное»','Золото 585','2.10','15000.00','pledged'],
            ['ЛБ-002','Серьги-пусеты','Золото 585','1.50','9000.00','pledged'],
            ['ЛБ-002','Подвеска «Сердце»','Золото 585','1.20','6000.00','pledged'],
            ['ЛБ-003','Браслет жесткий','Золото 750','18.40','95000.00','pledged'],
            ['ЛБ-003','Перстень мужской','Золото 585','8.20','38000.00','pledged'],
            ['ЛБ-003','Кулон «Знак зодиака»','Золото 585','2.50','12000.00','pledged'],
            ['ЛБ-003','Цепь «Якорная»','Серебро 925','14.00','9000.00','pledged'],
            ['ЛБ-003','Серьги с подвесками','Золото 585','5.50','28000.00','pledged'],
            ['ЛБ-004','Кольцо «Дорожка»','Золото 585','2.80','14000.00','for_sale'],
            ['ЛБ-004','Подвеска «Сердце»','Серебро 925','5.50','3500.00','for_sale'],
            ['ЛБ-004','Кулон «Знак зодиака»','Золото 375','2.00','7500.00','for_sale'],
            ['ЛБ-005','Часы наручные золотые','Золото 750','45.00','210000.00','pledged'],
            ['ЛБ-005','Цепь «Якорная»','Золото 585','25.00','115000.00','pledged'],
            ['ЛБ-005','Кольцо «Помолвочное»','Платина 950','4.20','55000.00','pledged'],
            ['ЛБ-005','Серьги с подвесками','Золото 585','6.80','32000.00','pledged'],
            ['ЛБ-005','Браслет плетения «Нонна»','Золото 585','12.00','58000.00','pledged'],
            ['ЛБ-005','Серьги-пусеты','Палладий 850','3.50','18000.00','pledged'],
            ['ЛБ-005','Кулон «Знак зодиака»','Платина 950','3.00','25000.00','pledged'],
            ['ЛБ-005','Кольцо обручальное','Золото 585','4.00','20000.00','pledged'],
            ['ЛБ-006','Цепь «Бисмарк»','Золото 585','20.00','94000.00','redeemed'],
            ['ЛБ-006','Крестик нательный','Золото 585','3.10','14500.00','redeemed'],
            ['ЛБ-006','Кольцо обручальное','Золото 750','5.20','31000.00','redeemed'],
            ['ЛБ-006','Браслет плетения «Нонна»','Золото 585','8.50','41000.00','redeemed'],
            ['ЛБ-007','Серьги-пусеты','Серебро 925','2.20','1500.00','pledged'],
            ['ЛБ-007','Подвеска «Сердце»','Серебро 925','1.80','1200.00','pledged'],
            ['ЛБ-007','Кольцо обручальное','Серебро 925','4.50','3000.00','pledged'],
            ['ЛБ-008','Кольцо «Помолвочное»','Золото 583','3.20','15500.00','for_sale'],
            ['ЛБ-008','Кулон «Знак зодиака»','Золото 375','4.00','12000.00','for_sale'],
            ['ЛБ-008','Серьги-пусеты','Золото 585','1.70','8500.00','for_sale'],
            ['ЛБ-009','Часы наручные золотые','Золото 585','38.50','165000.00','pledged'],
            ['ЛБ-009','Цепь «Якорная»','Золото 750','15.00','82000.00','pledged'],
            ['ЛБ-009','Перстень мужской','Золото 585','10.20','48000.00','pledged'],
            ['ЛБ-009','Браслет жесткий','Серебро 925','12.50','8500.00','pledged'],
            ['ЛБ-010','Часы коллекционные','Платина 950','52.00','320000.00','pledged'],
            ['ЛБ-010','Цепь «Бисмарк»','Золото 585','22.40','105000.00','pledged'],
            ['ЛБ-010','Кольцо «Дорожка»','Золото 750','3.80','28000.00','pledged'],
        ];

        foreach ($itemsData as [$ticketNum, $gtName, $stdKey, $weight, $estimate, $status]) {
            $item = new PledgedItem();
            $item->setName($gtName)
                ->setGoodType($goodTypes[$gtName] ?? null)
                ->setMetalStandard($standards[$stdKey] ?? null)
                ->setCategory($goodTypes[$gtName]?->getCategory())
                ->setItemWeight($weight)
                ->setScrapWeight($weight)
                ->setEstimatedValue($estimate)
                ->setCurrency($rub)
                ->setStatus($status)
                ->setStatusDate(new \DateTime())
                ->setLoanTicket($tickets[$ticketNum]);

            // Даты выставлены актуальные
            if ($status === PledgedItem::STATUS_FOR_SALE) {
                $item->setSoldPrice($estimate)
                    ->setPublishedAt(new \DateTime('2025-05-12'));
            }
            if ($status === PledgedItem::STATUS_REDEEMED) {
                $item->setRedemptionDate(new \DateTime('2025-05-10'));
            }

            $this->em->persist($item);
        }
        $this->em->flush();

        $io->success('Контрольный пример успешно добавлен!');
        $io->table(
            ['Таблица','Кол-во записей'],
            [
                ['Категории', 7],
                ['Металлы', 4],
                ['Пробы', 11],
                ['Цвета металлов', 5],
                ['Типы вставок', 4],
                ['Вставки', 6],
                ['Валюты', 3],
                ['Тарифы', 4],
                ['Администраторы', 3],
                ['Виды изделий', 15],
                ['Клиенты', 15],
                ['Залоговые билеты', 10],
                ['Предметы залога', 40],
            ]
        );

        return Command::SUCCESS;
    }
}