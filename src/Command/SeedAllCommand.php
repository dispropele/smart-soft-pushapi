<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\City;
use App\Entity\Client;
use App\Entity\Currency;
use App\Entity\Good;
use App\Entity\GoodImage;
use App\Entity\GoodType;
use App\Entity\LoanTicket;
use App\Entity\LoanedItem;
use App\Entity\Merchant;
use App\Entity\Metal;
use App\Entity\MetalColor;
use App\Entity\MetalStandard;
use App\Entity\StoneType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed', description: 'Очищает БД и заполняет тестовыми данными ювелирного ломбарда')]
class SeedAllCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('no-clear', null, InputOption::VALUE_NONE, 'Не очищать БД перед заполнением');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Заполнение базы данных ювелирного ломбарда');

        // ─── ОЧИСТКА ─────────────────────────────────────────────────────────
        if (!$input->getOption('no-clear')) {
            $io->section('Очистка базы данных...');
            $conn = $this->em->getConnection();
            $conn->executeStatement('SET session_replication_role = replica');
            foreach ([
                'loaned_items','loan_tickets','clients',
                'good_images','goods',
                'push_api_logs',
                'good_types','stone_types','metal_colors',
                'metal_standards','metals','currencies',
                'categories','merchants','cities',
            ] as $table) {
                $conn->executeStatement("TRUNCATE TABLE $table CASCADE");
            }
            // Сбросить sequence-автоинкременты (кроме goods/merchants — у них нет)
            foreach ([
                'categories_id_seq','cities_id_seq','currencies_id_seq',
                'clients_id_seq','good_images_id_seq',
                'good_types_id_seq','stone_types_id_seq','metal_colors_id_seq',
                'metal_standards_id_seq','metals_id_seq',
                'loan_tickets_id_seq','loaned_items_id_seq',
            ] as $seq) {
                try { $conn->executeStatement("ALTER SEQUENCE $seq RESTART WITH 1"); } catch (\Exception) {}
            }
            $conn->executeStatement('SET session_replication_role = DEFAULT');
            $io->success('База очищена');
        }

        // ─── ГОРОДА ───────────────────────────────────────────────────────────
        $io->section('Создание городов...');
        $moscow   = $this->city('Москва');
        $spb      = $this->city('Санкт-Петербург');
        $this->em->flush();
        $io->writeln('✓ Москва, Санкт-Петербург');

        // ─── ВАЛЮТА ───────────────────────────────────────────────────────────
        $rub = new Currency();
        $rub->setCurrency('RUB')->setName('Российский рубль');
        $this->em->persist($rub);
        $this->em->flush();

        // ─── КАТЕГОРИИ (только ювелирные) ─────────────────────────────────────
        $io->section('Создание ювелирных категорий...');
        $catNames = ['Кольца','Серьги','Цепи','Браслеты','Подвески и кулоны','Броши','Бусы и ожерелья'];
        $categories = [];
        foreach ($catNames as $name) {
            $cat = new Category();
            $cat->setName($name);
            $this->em->persist($cat);
            $categories[$name] = $cat;
            $io->writeln("✓ $name");
        }
        $this->em->flush();

        // ─── ТИПЫ КАМНЕЙ ──────────────────────────────────────────────────────
        $io->section('Создание типов камней...');
        $stones = [
            ['Бриллиант','diamond'],['Фианит','cubic_zirconia'],['Изумруд','emerald'],
            ['Сапфир','sapphire'],['Рубин','ruby'],['Топаз','topaz'],
            ['Аметист','amethyst'],['Жемчуг','pearl'],['Оникс','onyx'],
        ];
        $stoneMap = [];
        foreach ($stones as [$name,$code]) {
            $s = new StoneType(); $s->setName($name)->setCode($code);
            $this->em->persist($s);
            $stoneMap[$code] = $s;
        }
        $this->em->flush();
        $io->writeln('✓ ' . count($stones) . ' типов камней');

        // ─── ЦВЕТА МЕТАЛЛОВ ───────────────────────────────────────────────────
        $io->section('Создание цветов металлов...');
        $colors = [
            ['Жёлтое золото','yellow_gold'],['Белое золото','white_gold'],
            ['Розовое золото','rose_gold'],['Платина','platinum'],
            ['Серебро','silver'],['Комбинированное','combined'],
        ];
        $colorMap = [];
        foreach ($colors as [$name,$code]) {
            $c = new MetalColor(); $c->setName($name)->setCode($code);
            $this->em->persist($c);
            $colorMap[$code] = $c;
        }
        $this->em->flush();
        $io->writeln('✓ ' . count($colors) . ' цветов металлов');

        // ─── МЕТАЛЛЫ И ПРОБЫ ──────────────────────────────────────────────────
        $io->section('Создание металлов и проб...');
        $metalData = [
            'Золото'    => ['375','585','750','999'],
            'Серебро'   => ['925','875','830'],
            'Платина'   => ['950','850'],
            'Палладий'  => ['500','850'],
        ];
        $metalMap = []; $standardMap = [];
        foreach ($metalData as $mName => $standards) {
            $metal = new Metal(); $metal->setName($mName);
            $this->em->persist($metal);
            $metalMap[$mName] = $metal;
            foreach ($standards as $std) {
                $ms = new MetalStandard(); $ms->setMetal($metal)->setName($std);
                $this->em->persist($ms);
                $standardMap["$mName-$std"] = $ms;
            }
        }
        $this->em->flush();
        $io->writeln('✓ ' . count($metalData) . ' металлов');

        // ─── ВИДЫ ИЗДЕЛИЙ ─────────────────────────────────────────────────────
        $io->section('Создание видов изделий...');
        $typeData = [
            'Кольца'            => [['Обручальное','wedding',false],['Помолвочное','engagement',true],['Коктейльное','cocktail',true],['Мужское','mens',false],['Классическое','classic',false]],
            'Серьги'            => [['Гвоздики','studs',true],['Висячие','drop',true],['Кольца','hoops',false],['Пусеты','pusety',true]],
            'Цепи'              => [['Якорное плетение','anchor',false],['Венецианское','venetian',false],['Тонкая цепочка','thin',false],['Плетёная','braided',false]],
            'Браслеты'          => [['Теннисный','tennis',true],['Жёсткий','bangle',false],['С подвесками','charm',true],['Классический','classic_b',false]],
            'Подвески и кулоны' => [['Крест','cross',false],['Сердечко','heart',true],['Животные','animal',true],['Геометрический','geometric',false]],
            'Броши'             => [['Классическая','classic_br',true],['Цветок','flower',true],['Насекомое','insect',true]],
            'Бусы и ожерелья'   => [['Однорядные','single',true],['Многорядные','multi',true],['Жемчуг','pearl_n',true]],
        ];
        foreach ($typeData as $catName => $types) {
            if (!isset($categories[$catName])) continue;
            foreach ($types as [$name,$code,$hasStones]) {
                $gt = new GoodType();
                $gt->setName($name)->setCode($code)->setCategory($categories[$catName])->setHasStones($hasStones);
                $this->em->persist($gt);
            }
        }
        $this->em->flush();
        $io->writeln('✓ Виды изделий созданы');

        // ─── ФИЛИАЛЫ ──────────────────────────────────────────────────────────
        $io->section('Создание филиалов...');
        $m1 = $this->merchant(1, 'Аурум Ломбард — Центральный', $moscow,
            'ул. Арбат, 25', '+7 (495) 555-01-01', 'Главный офис в самом центре Москвы. Принимаем все виды ювелирных украшений.');
        $m2 = $this->merchant(2, 'Аурум Ломбард — Невский', $spb,
            'Невский пр., 88', '+7 (812) 555-02-02', 'Отделение на Невском проспекте. Оценка и скупка ювелирных украшений.');
        $this->em->flush();
        $io->writeln('✓ 2 филиала');

        // ─── ТОВАРЫ ───────────────────────────────────────────────────────────
        $io->section('Создание товаров...');

        $goodsData = [
            [1001, 'Кольцо золотое с бриллиантами 585', $m1, 'Кольца', 'Помолвочное', 'Золото','585', 'Белое золото', 'diamond', true, '17.5', 85000, 'Вес 3.8г, 8 бриллиантов по 0.05 ct. Оригинал с сертификатом.'],
            [1002, 'Серьги золотые гвоздики с сапфирами', $m1, 'Серьги', 'Гвоздики', 'Золото','585', 'Жёлтое золото', 'sapphire', true, null, 42000, 'Сапфиры натуральные. Вес 3.2г.'],
            [1003, 'Золотая цепь якорное плетение', $m1, 'Цепи', 'Якорное плетение', 'Золото','585', 'Жёлтое золото', null, false, '55 см', 36000, 'Вес 7.5г, длина 55 см, ширина 3 мм.'],
            [1004, 'Серебряный браслет с фианитами 925', $m2, 'Браслеты', 'Теннисный', 'Серебро','925', 'Серебро', 'cubic_zirconia', true, '17 см', 8500, 'Вес 12г. Длина 17 см. Застёжка-карабин.'],
            [1005, 'Кольцо серебряное с изумрудом', $m2, 'Кольца', 'Классическое', 'Серебро','925', 'Серебро', 'emerald', true, '16', 12000, 'Вес 4.1г. Изумруд натуральный, огранка «кабошон».'],
            [1006, 'Золотые серьги висячие с рубинами', $m1, 'Серьги', 'Висячие', 'Золото','585', 'Жёлтое золото', 'ruby', true, null, 68000, 'Рубины Burma 0.8 ct. Вес 5.6г.'],
            [1007, 'Платиновое обручальное кольцо 950', $m1, 'Кольца', 'Обручальное', 'Платина','950', 'Платина', null, false, '18', 95000, 'Вес 6.2г. Платина 950 пр. Полированная поверхность.'],
            [1008, 'Кулон золотой «Сердечко» с бриллиантом', $m2, 'Подвески и кулоны', 'Сердечко', 'Золото','585', 'Розовое золото', 'diamond', true, null, 24000, 'Бриллиант 0.12 ct. Вес 1.9г. Цепочка в комплекте.'],
            [1009, 'Золотой браслет классический 750', $m1, 'Браслеты', 'Классический', 'Золото','750', 'Жёлтое золото', null, false, '19 см', 58000, 'Плетение «Бисмарк». Вес 14.3г.'],
            [1010, 'Серьги серебряные с жемчугом', $m2, 'Серьги', 'Пусеты', 'Серебро','925', 'Серебро', 'pearl', true, null, 6800, 'Жемчуг пресноводный. Диаметр 8 мм. Вес 3.1г.'],
            [1011, 'Брошь золотая «Цветок» с топазами', $m1, 'Броши', 'Цветок', 'Золото','585', 'Жёлтое золото', 'topaz', true, null, 31000, '5 топазов голубых. Вес 4.7г.'],
            [1012, 'Колье серебряное с аметистами', $m2, 'Бусы и ожерелья', 'Однорядные', 'Серебро','925', 'Серебро', 'amethyst', true, '45 см', 15500, '15 аметистов натуральных. Длина 45 см.'],
        ];

        foreach ($goodsData as [$id,$name,$merchant,$catName,$typeName,$metalName,$standard,$colorCode,$stoneCode,$hasStone,$size,$price,$desc]) {
            $good = new Good();
            (new \ReflectionProperty(Good::class, 'id'))->setValue($good, $id);
            $good->setName($name)
                ->setMerchant($merchant)
                ->setSoldPrice((string)$price)
                ->setCurrency($rub)
                ->setStatus(Good::STATUS_ACTIVE)
                ->setDescription($desc)
                ->setHasStone($hasStone);

            if ($size) $good->setSize($size);
            if (isset($categories[$catName])) $good->setCategory($categories[$catName]);
            if ($stoneCode && isset($stoneMap[$stoneCode])) $good->setStoneType($stoneMap[$stoneCode]);
            if ($colorCode && isset($colorMap[$colorCode])) $good->setMetalColor($colorMap[$colorCode]);
            if (isset($standardMap["$metalName-$standard"])) $good->setMetalStandard($standardMap["$metalName-$standard"]);

            // Найти вид изделия
            foreach ($typeData[$catName] ?? [] as [$tName]) {
                if ($tName === $typeName) {
                    // Найдём в репозитории после flush
                    break;
                }
            }

            $this->em->persist($good);
        }
        $this->em->flush();

        // Привязка goodType по имени (после flush)
        foreach ($goodsData as $row) {
            $id      = $row[0];
            $catName = $row[3];
            $typeName = $row[4];
            $good = $this->em->find(Good::class, $id);
            if (!$good || !isset($categories[$catName])) continue;
            $gt = $this->em->getRepository(GoodType::class)->findOneBy([
                'name' => $typeName, 'category' => $categories[$catName]
            ]);
            if ($gt) { $good->setGoodType($gt); }
        }
        $this->em->flush();
        $io->writeln('✓ ' . count($goodsData) . ' товаров');

        // ─── КЛИЕНТЫ И БИЛЕТЫ ─────────────────────────────────────────────────
        $io->section('Создание клиентов и залоговых билетов...');

        // Клиент 1
        $client1 = new Client();
        $client1->setFullName('Петрова Наталья Игоревна')
            ->setPassportNumber('4512345678')
            ->setPassportSeries('6709')
            ->setAddress('г. Москва, ул. Тверская, д. 12, кв. 34')
            ->setPhone('79991234567')
            ->setEmail('petrova@example.com');
        $this->em->persist($client1);

        // Клиент 2
        $client2 = new Client();
        $client2->setFullName('Соколов Андрей Викторович')
            ->setPassportNumber('7823456789')
            ->setPassportSeries('7811')
            ->setAddress('г. Санкт-Петербург, Невский пр., д. 45, кв. 10')
            ->setPhone('78122345678');
        $this->em->persist($client2);
        $this->em->flush();

        // Билет 1 — Петрова (открытый, срок ок)
        $ticket1 = new LoanTicket();
        $ticket1->setClient($client1)
            ->setTicketNumber('ЛБ-2026-0001')
            ->setLoanAmount('45000')
            ->setInterestRate('2.50')
            ->setIssuedAt(new \DateTime('-20 days'))
            ->setReturnDate(new \DateTime('+40 days'))
            ->setStatus(LoanTicket::STATUS_OPEN)
            ->setNotes('Клиент постоянный. Предметы в хорошем состоянии.');
        $this->em->persist($ticket1);

        // Предметы билета 1
        $items1 = [
            ['Кольцо золотое с бриллиантом', 'Кольцо', $metalMap['Золото'], $standardMap['Золото-585'], $colorMap['yellow_gold'], $stoneMap['diamond'], true, '3.50', '35000', 'Отличное'],
            ['Серьги золотые с изумрудами', 'Серьги', $metalMap['Золото'], $standardMap['Золото-750'], $colorMap['white_gold'], $stoneMap['emerald'], true, '5.20', '52000', 'Хорошее'],
        ];
        foreach ($items1 as [$name,$type,$metal,$std,$color,$stone,$hasS,$weight,$val,$cond]) {
            $item = new LoanedItem();
            $item->setLoanTicket($ticket1)->setName($name)->setJewelryType($type)
                ->setMetal($metal)->setMetalStandard($std)->setMetalColor($color)
                ->setStoneType($stone)->setHasStone($hasS)
                ->setWeight($weight)->setEstimatedValue($val)->setCondition($cond);
            $this->em->persist($item);
        }

        // Билет 2 — Петрова (скоро истекает)
        $ticket2 = new LoanTicket();
        $ticket2->setClient($client1)
            ->setTicketNumber('ЛБ-2026-0002')
            ->setLoanAmount('18000')
            ->setInterestRate('2.50')
            ->setIssuedAt(new \DateTime('-85 days'))
            ->setReturnDate(new \DateTime('+5 days'))
            ->setStatus(LoanTicket::STATUS_OPEN)
            ->setNotes('Срок скоро истекает, клиенту направлено уведомление.');
        $this->em->persist($ticket2);

        $item2 = new LoanedItem();
        $item2->setLoanTicket($ticket2)->setName('Браслет серебряный с жемчугом')
            ->setJewelryType('Браслет')->setMetal($metalMap['Серебро'])->setMetalStandard($standardMap['Серебро-925'])
            ->setMetalColor($colorMap['silver'])->setStoneType($stoneMap['pearl'])
            ->setHasStone(true)->setWeight('14.00')->setEstimatedValue('18000')->setCondition('Хорошее');
        $this->em->persist($item2);

        // Билет 3 — Соколов (открытый)
        $ticket3 = new LoanTicket();
        $ticket3->setClient($client2)
            ->setTicketNumber('ЛБ-2026-0003')
            ->setLoanAmount('120000')
            ->setInterestRate('1.80')
            ->setIssuedAt(new \DateTime('-10 days'))
            ->setReturnDate(new \DateTime('+80 days'))
            ->setStatus(LoanTicket::STATUS_OPEN)
            ->setNotes('VIP клиент. Пониженная ставка.');
        $this->em->persist($ticket3);

        $items3 = [
            ['Цепь платиновая 950 пр.', 'Цепочка', $metalMap['Платина'], $standardMap['Платина-950'], $colorMap['platinum'], null, false, '18.50', '75000', 'Отличное'],
            ['Кольцо платиновое с бриллиантами', 'Кольцо', $metalMap['Платина'], $standardMap['Платина-950'], $colorMap['platinum'], $stoneMap['diamond'], true, '6.20', '95000', 'Отличное'],
        ];
        foreach ($items3 as [$name,$type,$metal,$std,$color,$stone,$hasS,$weight,$val,$cond]) {
            $item = new LoanedItem();
            $item->setLoanTicket($ticket3)->setName($name)->setJewelryType($type)
                ->setMetal($metal)->setMetalStandard($std)->setMetalColor($color)
                ->setHasStone($hasS)->setWeight($weight)->setEstimatedValue($val)->setCondition($cond);
            if ($stone) $item->setStoneType($stone);
            $this->em->persist($item);
        }

        // Закрытый билет — история
        $ticket4 = new LoanTicket();
        $ticket4->setClient($client2)
            ->setTicketNumber('ЛБ-2026-0000')
            ->setLoanAmount('25000')
            ->setInterestRate('2.50')
            ->setIssuedAt(new \DateTime('-120 days'))
            ->setReturnDate(new \DateTime('-30 days'))
            ->setStatus(LoanTicket::STATUS_CLOSED)
            ->setNotes('Выкуплено клиентом досрочно.');
        $this->em->persist($ticket4);

        $item4 = new LoanedItem();
        $item4->setLoanTicket($ticket4)->setName('Серьги золотые с сапфирами')
            ->setJewelryType('Серьги')->setMetal($metalMap['Золото'])->setMetalStandard($standardMap['Золото-585'])
            ->setMetalColor($colorMap['yellow_gold'])->setStoneType($stoneMap['sapphire'])
            ->setHasStone(true)->setWeight('4.80')->setEstimatedValue('25000')->setCondition('Отличное');
        $this->em->persist($item4);

        $this->em->flush();
        $io->writeln('✓ 2 клиента, 4 залоговых билета, заложенное имущество');

        // ─── ИТОГ ─────────────────────────────────────────────────────────────
        $io->success([
            'База данных успешно заполнена!',
            '',
            'Данные для входа в кабинет клиента:',
            '  ФИО:    Петрова Наталья Игоревна',
            '  Билет:  ЛБ-2026-0001',
            '',
            '  ФИО:    Соколов Андрей Викторович',
            '  Билет:  ЛБ-2026-0003',
        ]);

        return Command::SUCCESS;
    }

    private function city(string $name): City
    {
        $city = new City(); $city->setName($name);
        $this->em->persist($city);
        return $city;
    }

    private function merchant(int $id, string $name, City $city, string $address, string $phone, string $desc): Merchant
    {
        $m = new Merchant();
        (new \ReflectionProperty(Merchant::class, 'id'))->setValue($m, $id);
        $m->setName($name)->setCity($city)->setAddress($address)->setPhone(preg_replace('/\D/','',$phone))->setDescription($desc);
        $this->em->persist($m);
        return $m;
    }
}