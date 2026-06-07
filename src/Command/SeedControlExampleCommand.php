<?php
namespace App\Command;

use App\Entity\{Admin, Category, Client, Currency, GoodType, Insert, InsertType,
    LoanTicket, Metal, MetalColor, MetalStandard, PledgedItem, SaleRequest, Tariff};
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
            'metal_colors', 'metal_standards', 'metals',
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
        $inserts = [];
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
            $inserts[$name] = $ins;
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
            ['Стандартный','0.40'],
            ['Льготный','0.25'],
            ['VIP','0.15'],
            ['Срочный','0.60'],
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
            ['admin123','admin@lombard.ru','admin123'],
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
            ['Иванов Иван Иванович','123456','4510','+7 (900) 111-22-33','г. Тольятти, Автозаводский р-н, ул. Мира, д. 1, кв. 42'],
            ['Петров Петр Петрович','654321','4511','+7 (900) 444-55-66','г. Тольятти, Центральный р-н, ул. Победы, д. 15, кв. 102'],
            ['Сидорова Анна Павловна','789012','4612','+7 (911) 777-88-99','г. Тольятти, Комсомольский р-н, ул. Юбилейная, д. 2, кв. 8'],
            ['Кузнецов Олег Игоревич','345678','5014','+7 (920) 000-11-22','г. Тольятти, Автозаводский р-н, ул. Революционная, д. 5, кв. 315'],
            ['Смирнова Елена Викторовна','556677','4510','+7 (950) 333-44-55','г. Тольятти, Автозаводский р-н, ул. 70 лет Октября, д. 10, кв. 88'],
            ['Васильев Сергей Андреевич','112233','4508','+7 (960) 123-45-67','г. Тольятти, Центральный р-н, ул. Дзержинского, д. 3, кв. 12'],
            ['Морозова Ольга Сергеевна','998877','4515','+7 (905) 987-65-43','г. Тольятти, Комсомольский р-н, ул. Баныкина, д. 8, кв. 54'],
            ['Павлов Игорь Владимирович','443322','6010','+7 (916) 555-44-33','г. Тольятти, Автозаводский р-н, ул. Автостроителей, д. 12, кв. 210'],
            ['Соколов Максим Юрьевич','224466','4511','+7 (903) 111-00-22','г. Тольятти, Автозаводский р-н, ул. Тополиная, д. 21, кв. 9'],
            ['Федорова Дарья Алексеевна','775533','4614','+7 (909) 222-33-44','г. Тольятти, Автозаводский р-н, ул. Фрунзе, д. 4, кв. 118'],
            ['Попов Андрей Николаевич','884422','4510','+7 (981) 555-66-77','г. Тольятти, Центральный р-н, ул. Ленина, д. 101, кв. 3'],
            ['Семенова Инна Игоревна','339911','5012','+7 (921) 444-33-22','г. Тольятти, Комсомольский р-н, ул. Лизы Чайкиной, д. 45, кв. 16'],
            ['Волков Артем Дмитриевич','121212','4518','+7 (900) 888-99-00','г. Тольятти, Автозаводский р-н, ул. Ворошилова, д. 11, кв. 77'],
            ['Борисова Яна Олеговна','343434','4509','+7 (911) 000-11-22','г. Тольятти, Комсомольский р-н, ул. Гидротехническая, д. 5, кв. 140'],
            ['Макаров Денис Петрович','565656','4610','+7 (905) 777-88-99','г. Тольятти, Автозаводский р-н, Приморский б-р, д. 19, кв. 5'],
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
        // Заменяем статические даты на относительные смещения от сегодняшнего дня
        $io->section('Залоговые билеты...');
        $ticketData = [
            // [$num, $offsetDays, $clientName, $tariffName, $amount, $status, $isClosed]
            ['ЛБ-001', -65, 'Иванов Иван Иванович', 'Стандартный', '25000.00', 'closed', true],
            ['ЛБ-002', -15, 'Петров Петр Петрович', 'Стандартный', '12000.00', 'open', false],
            ['ЛБ-003', -10, 'Сидорова Анна Павловна', 'Льготный', '45000.00', 'open', false],
            ['ЛБ-004', -75, 'Смирнова Елена Викторовна', 'Стандартный', '8500.00', 'expired', false],
            ['ЛБ-005', -5, 'Васильев Сергей Андреевич', 'VIP', '180000.00', 'open', false],
            ['ЛБ-006', -45, 'Федорова Дарья Алексеевна', 'Стандартный', '32000.00', 'closed', true],
            ['ЛБ-007', -12, 'Семенова Инна Игоревна', 'Льготный', '6000.00', 'open', false],
            ['ЛБ-008', -80, 'Макаров Денис Петрович', 'Стандартный', '19500.00', 'expired', false],
            ['ЛБ-009', -2, 'Кузнецов Олег Игоревич', 'Срочный', '60000.00', 'open', false],
            ['ЛБ-010', -19, 'Павлов Игорь Владимирович', 'VIP', '95000.00', 'open', false],
        ];
        $tickets = [];
        foreach ($ticketData as [$num, $offsetDays, $clientName, $tariffName, $amount, $status, $isClosed]) {
            $tariff = $tariffs[$tariffName];
            $issuedAt = (new \DateTime())->modify("$offsetDays days");
            $returnDate = (clone $issuedAt)->modify('+30 days');

            $t = new LoanTicket();
            $t->setTicketNumber($num)
                ->setClient($clients[$clientName])
                ->setLoanAmount($amount)
                ->setTariff($tariff)
                ->setDailyInterestRate($tariff->getDailyRate())
                ->setIssuedAt($issuedAt)
                ->setReturnDate($returnDate)
                ->setGraceDays(30)
                ->setStatus($status);

            if (method_exists($t, 'setInterestRate')) {
                $t->setInterestRate($tariff->getDailyRate() * 30);
            }

            if ($isClosed) {
                // Закрываем залог спустя 25 дней после открытия
                $t->setClosedAt((clone $issuedAt)->modify('+25 days'));
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
        $items = [];

        // Карта соответствия вставок для предметов
        $insertsForItems = [
            'Кольцо «Помолвочное»' => [$inserts['Бриллиант'] ?? null, $inserts['Фианит'] ?? null],
            'Серьги-пусеты'        => [$inserts['Изумруд'] ?? null],
            'Подвеска «Сердце»'     => [$inserts['Топаз'] ?? null],
            'Браслет жесткий'      => [$inserts['Гранат'] ?? null],
            'Перстень мужской'     => [$inserts['Бриллиант'] ?? null],
            'Кулон «Знак зодиака»'  => [$inserts['Фианит'] ?? null],
            'Серьги с подвесками'  => [$inserts['Жемчуг'] ?? null],
            'Кольцо «Дорожка»'     => [$inserts['Бриллиант'] ?? null, $inserts['Фианит'] ?? null],
            'Часы коллекционные'   => [$inserts['Бриллиант'] ?? null],
        ];

        // Фильтрация пустых вставок
        foreach ($insertsForItems as $key => $list) {
            $insertsForItems[$key] = array_filter($list);
        }

        // Высокодетализированные описания ювелирных изделий ломбарда
        $descriptionMap = [
            'Кольцо обручальное' => 'Обручальное кольцо классической полубочкообразной формы из красного золота. Поверхность зеркально отполирована, без видимых царапин и потертостей. Идеальная сохранность геометрии.',
            'Цепь «Бисмарк»' => 'Полнотелая золотая цепь ручного плетения «Бисмарк». Оснащена сверхнадежным коробчатым замком с предохранителем. Плетение ровное, заломы и дефекты звеньев отсутствуют.',
            'Крестик нательный' => 'Православный нательный крест, классический дизайн с матовым рельефным распятием. Изделие прошло ультразвуковую чистку, ушко целое, без следов износа.',
            'Браслет плетения «Нонна»' => 'Изящный женский браслет двойного панцирного плетения «Нонна». Алмазная огранка придает благородный блеск. Замок карабинного типа работает исправно.',
            'Кольцо «Помолвочное»' => 'Элегантное кольцо для помолвки. Имеется клеймо завода-изготовителя. Закрепка крапанового типа надежно удерживает центральный камень.',
            'Серьги-пусеты' => 'Пара минималистичных серег-гвоздиков (пусет). Винтовой замок обеспечивает жесткую фиксацию. Резьба без повреждений.',
            'Подвеска «Сердце»' => 'Ажурная подвеска в форме сердца. Декоративное родирование придает изделию дополнительную износостойкость и холодный платиновый оттенок.',
            'Браслет жесткий' => 'Цельный жесткий браслет (обруч) геометрической формы с удобным боковым шарнирным замком-защелкой. Без деформаций.',
            'Перстень мужской' => 'Массивный мужской перстень-печатка с прямоугольной гладкой площадкой. Боковые грани украшены легкой алмазной гравировкой.',
            'Кулон «Знак зодиака»' => 'Кулон с лазерной гравировкой символа зодиака. Ушко рассчитано под широкую цепь, деформации отсутствуют.',
            'Часы наручные золотые' => 'Элитные мужские часы с корпусом из розового золота. Механика с автоподзаводом, кожаный ремешок заменен на новый. Стекло без единой микроцарапины.',
            'Часы коллекционные' => 'Редкие швейцарские хронометры в идеальном коллекционном состоянии. Корпус без сколов, оригинальный платиновый безель.',
            'Кольцо «Дорожка»' => 'Ювелирное кольцо с дорожкой из мелких сверкающих камней. Огранка камней круглая, закрепка рельсовая (канал), камни не шатаются.',
        ];

        $specificationMap = [
            'Кольцо обручальное' => 'Ширина: 4.0 мм. Размер: 17.5. Износ минимальный (состояние нового). Проба ГИПН подтверждена.',
            'Цепь «Бисмарк»' => 'Длина: 55 см. Ширина звеньев: 5.5 мм. Замок: коробка. Ручная сборка, клеймо завода.',
            'Крестик нательный' => 'Высота: 35 мм (с ушком). Ширина: 18 мм. Чернение серебра, ручная гравировка.',
            'Браслет плетения «Нонна»' => 'Длина: 18 см. Ширина: 3.2 мм. Замок: карабин. Алмазная грань с двух сторон.',
            'Кольцо «Помолвочное»' => 'Размер: 16.5. Закрепка: 4 крапана. Высота короны: 5 мм.',
            'Серьги-пусеты' => 'Тип замка: винтовой. Длина штифта: 9 мм. Идеальная симметрия.',
            'Подвеска «Сердце»' => 'Размер: 15х15 мм. Покрытие: родирование. Декоративная сквозная резка металла.',
            'Браслет жесткий' => 'Внутренний диаметр: 60 мм. Ширина: 6.0 мм. Замок с двойным предохранителем.',
            'Перстень мужской' => 'Размер: 20.0. Площадка: 12х10 мм. Состояние: полированное, без забоин.',
            'Кулон «Знак зодиака»' => 'Диаметр: 20 мм. Внутренний размер ушка: 5х3 мм. Матовое покрытие фона.',
            'Часы наручные золотые' => 'Золотой корпус 750 пробы. Диаметр: 40 мм. Водозащита 50м. Сапфировое стекло.',
            'Часы коллекционные' => 'Платиновый корпус 950 пробы. Лимитированная серия. Хронограф, калибр оригинальный.',
            'Кольцо «Дорожка»' => 'Размер: 17.0. Вставки уложены в ровный канал, закреплены безупречно.',
        ];

        $itemIndex = 0;
        foreach ($itemsData as [$ticketNum, $gtName, $stdKey, $weight, $estimate, $status]) {
            $size = null;
            if (str_contains($gtName, 'Кольцо')) {
                $size = '17';
            } elseif (str_contains($gtName, 'Браслет')) {
                $size = '18';
            }

            $description = $descriptionMap[$gtName] ?? 'Изысканное изделие из драгоценного металла.';
            $specification = $specificationMap[$gtName] ?? sprintf('Фактический вес %s г. Состояние отличное.', $weight);
            
            // Расчет дат залогов относительно системной даты выдачи билета
            $ticket = $tickets[$ticketNum];
            $issuedAt = $ticket->getIssuedAt();
            $returnDate = $ticket->getReturnDate();

            // Дата публикации на витрину (через 30 дней после окончания срока выкупа)
            $publishedAt = (clone $returnDate)->modify('+30 days');
            // Дата выкупа (в рамках срока договора)
            $redemptionDate = $ticket->getClosedAt() ?? (clone $issuedAt)->modify('+25 days');

            $item = new PledgedItem();
            $item->setName($gtName)
                ->setGoodType($goodTypes[$gtName] ?? null)
                ->setMetalStandard($standards[$stdKey] ?? null)
                ->setCategory($goodTypes[$gtName]?->getCategory())
                ->setItemWeight($weight)
                ->setScrapWeight($weight)
                ->setEstimatedValue($estimate)
                ->setCurrency($rub)
                ->setSize($size)
                ->setCondition($status === PledgedItem::STATUS_REDEEMED ? 'Хорошее' : 'Отличное')
                ->setDescription($description)
                ->setSpecification($specification)
                ->setStatus($status);

            // Если связь "многие-ко-многим" со вставками поддерживается сущностью
            if (method_exists($item, 'addInsert') && isset($insertsForItems[$gtName])) {
                foreach ($insertsForItems[$gtName] as $insEntity) {
                    $item->addInsert($insEntity);
                }
            }

            if ($status === PledgedItem::STATUS_FOR_SALE) {
                $salePrice = sprintf('%.2f', ((float) $estimate) * 1.15);
                $item->setSoldPrice($salePrice)
                    ->setPublishedAt($publishedAt)
                    ->setStatusDate($publishedAt);
            } elseif ($status === PledgedItem::STATUS_REDEEMED) {
                $item->setRedemptionDate($redemptionDate)
                    ->setStatusDate($redemptionDate);
            } else {
                $item->setStatusDate($issuedAt);
            }

            $item->setLoanTicket($ticket);

            $this->em->persist($item);
            $items[$ticketNum][$gtName] = $item;
            $itemIndex++;
        }
        $this->em->flush();

        // Заявки на приобретение невостребованных вещей
        $requestData = [
            ['ЛБ-004', 'Кольцо «Дорожка»', 'Ткаченко Ольга Сергеевна', '+7 (920) 777-88-99', 'o.tkachenko@mail.ru', 'Здравствуйте! Подскажите, пожалуйста, возможна ли примерка кольца в вашем отделении на ул. Победы? Заранее спасибо.'],
            ['ЛБ-004', 'Кулон «Знак зодиака»', 'Смирнов Алексей Викторович', '+7 (960) 123-45-67', 'a.smirnov@mail.ru', 'Добрый день! Интересует данная подвеска. Предоставляете ли вы фирменный футляр при покупке изделия с витрины?'],
            ['ЛБ-008', 'Серьги-пусеты', 'Кузнецова Мария Игоревна', '+7 (905) 123-45-67', 'maria.kuznetsova@mail.ru', 'Готова забронировать и выкупить эти серьги сегодня вечером. Прошу связаться со мной для подтверждения резерва.'],
        ];

        foreach ($requestData as [$ticketNum, $itemName, $fullName, $phone, $email, $message]) {
            $pledgedItem = $items[$ticketNum][$itemName] ?? null;
            if (!$pledgedItem) {
                throw new \RuntimeException(sprintf('Не найден предмет залога %s для билета %s', $itemName, $ticketNum));
            }

            $saleRequest = new SaleRequest();
            $saleRequest->setPledgedItem($pledgedItem)
                ->setFullName($fullName)
                ->setPhone($phone)
                ->setEmail($email)
                ->setMessage($message);
            
            // Если в сущности есть поле даты создания заявки, заполняем её
            if (method_exists($saleRequest, 'setCreatedAt')) {
                $saleRequest->setCreatedAt((clone $pledgedItem->getPublishedAt())->modify('+2 days'));
            }

            $this->em->persist($saleRequest);
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
                ['Администраторы', 4],
                ['Виды изделий', 15],
                ['Клиенты', 15],
                ['Залоговые билеты', 10],
                ['Предметы залога', 40],
                ['Заявки на покупку', 3],
            ]
        );

        return Command::SUCCESS;
    }
}