<?php

namespace App\Command;

use App\Entity\{Category, Currency, GoodType, Insert, InsertType, MetalColor, MetalStandard, Metal, PledgedItem, PledgedItemInsert};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed',
    description: 'Заполнение БД демонстрационными данными (русские справочники и витрина)',
    hidden: false,
)]
class SeedAllCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('no-clear', null, InputOption::VALUE_NONE, 'Не очищать таблицы перед заполнением');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Заполнение базы данных…');

        if (!$input->getOption('no-clear')) {
            $io->section('Очистка таблиц…');
            $conn = $this->em->getConnection();
            foreach ([
                'pledged_item_images', 'pledged_items',
                'loan_tickets', 'clients',
                'push_api_logs',
                'good_types', 'inserts', 'insert_types',
                'metal_colors', 'metal_standards', 'metals',
                'currencies', 'categories',
            ] as $table) {
                $conn->executeStatement("TRUNCATE TABLE $table CASCADE");
            }
            foreach ([
                'categories_id_seq', 'currencies_id_seq',
                'clients_id_seq', 'pledged_item_images_id_seq',
                'good_types_id_seq', 'insert_types_id_seq', 'inserts_id_seq',
                'metal_colors_id_seq', 'metal_standards_id_seq', 'metals_id_seq',
                'loan_tickets_id_seq', 'pledged_items_id_seq',
            ] as $seq) {
                try {
                    $conn->executeStatement("ALTER SEQUENCE $seq RESTART WITH 1");
                } catch (\Exception) {}
            }
            $io->success('Таблицы очищены');
        }

        $io->section('Валюта…');
        $rub = new Currency();
        $rub->setCurrency('RUB')->setName('Российский рубль');
        $this->em->persist($rub);
        $this->em->flush();
        $io->writeln('✓ RUB');

        $io->section('Категории…');
        $catNames = ['Кольца', 'Серьги', 'Цепи', 'Браслеты', 'Подвески', 'Броши', 'Колье'];
        $categories = [];
        foreach ($catNames as $name) {
            $cat = new Category();
            $cat->setName($name);
            $this->em->persist($cat);
            $categories[$name] = $cat;
            $io->writeln("✓ {$name}");
        }
        $this->em->flush();

        $io->section('Типы и наименования вставок…');
        $insertTypes = [
            'Драгоценные камни'     => 'precious',
            'Полудрагоценные камни' => 'semi',
            'Синтетические вставки' => 'synthetic',
        ];
        $insertTypeMap = [];
        foreach ($insertTypes as $name => $code) {
            $t = new InsertType();
            $t->setName($name);
            $this->em->persist($t);
            $insertTypeMap[$name] = $t;
        }
        $this->em->flush();

        $inserts = [
            'Драгоценные камни'     => ['Бриллиант', 'Изумруд', 'Сапфир', 'Рубин'],
            'Полудрагоценные камни' => ['Топаз', 'Аметист', 'Жемчуг', 'Оникс'],
            'Синтетические вставки' => ['Фианит', 'Кубический цирконий'],
        ];
        $insertMap = [];
        foreach ($inserts as $typeName => $names) {
            foreach ($names as $name) {
                $i = new Insert();
                $i->setInsertType($insertTypeMap[$typeName]);
                $i->setName($name);
                $this->em->persist($i);
                $insertMap[$name] = $i;
            }
        }
        $this->em->flush();
        $io->writeln('✓ Типы вставок и вставки');

        $io->section('Цвета металла…');
        $colors = [
            ['Жёлтое золото',  'yellow_gold'],
            ['Белое золото',   'white_gold'],
            ['Розовое золото', 'rose_gold'],
            ['Платина (цвет)', 'platinum'],
            ['Серебро (цвет)', 'silver'],
        ];
        $colorMap = [];
        foreach ($colors as [$name, $code]) {
            $c = new MetalColor();
            $c->setName($name)->setCode($code);
            $this->em->persist($c);
            $colorMap[$code] = $c;
        }
        $this->em->flush();
        $io->writeln('✓ ' . count($colors) . ' цветов');

        $io->section('Металлы и пробы…');
        $metalData = [
            'Золото'   => ['375', '585', '750', '999'],
            'Серебро'  => ['925', '875', '830'],
            'Платина'  => ['950', '850'],
            'Палладий' => ['500', '850'],
        ];
        $metalMap    = [];
        $standardMap = [];
        foreach ($metalData as $mName => $standards) {
            $metal = new Metal();
            $metal->setName($mName);
            $this->em->persist($metal);
            $metalMap[$mName] = $metal;
            foreach ($standards as $std) {
                $ms = new MetalStandard();
                $ms->setMetal($metal)->setName($std);
                $this->em->persist($ms);
                $standardMap["$mName-$std"] = $ms;
            }
        }
        $this->em->flush();
        $io->writeln('✓ Металлы и пробы');

        $io->section('Виды изделий…');
        $typeData = [
            'Кольца'    => [['Помолвочные', 'engagement'], ['Классические', 'classic_ring'], ['Обручальные', 'wedding_band']],
            'Серьги'    => [['Пусеты', 'studs'], ['Конго', 'hoops'], ['Длинные', 'dangles']],
            'Цепи'      => [['Якорное плетение', 'anchor'], ['Фигаро', 'figaro'], ['Бисмарк', 'box']],
            'Браслеты'  => [['Теннисные', 'tennis'], ['Классические', 'classic_bracelet'], ['Жёсткие', 'cuff']],
            'Подвески'  => [['Сердечко', 'heart'], ['Крест', 'cross'], ['Геометрия', 'geometric_pendant']],
            'Броши'     => [['Цветы', 'flower'], ['Животные', 'animal'], ['Абстракция', 'geometric_brooch']],
            'Колье'     => [['В один ряд', 'single_row'], ['Многоярусные', 'multi_row'], ['Каскадные', 'layered']],
        ];
        $goodTypeByCatAndName = [];
        foreach ($typeData as $catName => $types) {
            if (!isset($categories[$catName])) {
                continue;
            }
            foreach ($types as [$name, $code]) {
                $gt = new GoodType();
                $gt->setName($name)->setCode($code)->setCategory($categories[$catName]);
                $this->em->persist($gt);
                $goodTypeByCatAndName[$catName . '|' . $name] = $gt;
            }
        }
        $this->em->flush();
        $io->writeln('✓ Виды изделий');

        $io->section('Позиции на витрине…');

        $itemsData = [
            [
                1001,
                'Золотое кольцо с бриллиантами, 585 проба',
                'Кольца', 'Помолвочные',
                'Золото', '585', 'white_gold', 'Бриллиант',
                '17.5', 85000,
                'Вес изделия 3,8 г. Восемь бриллиантов огранки круг по 0,05 ct.',
                '3.80', '3.20', '0.40', 'Бриллианты круглой огранки, чистота VS–SI', 'Отличное',
            ],
            [
                1002,
                'Золотые серьги с сапфирами',
                'Серьги', 'Пусеты',
                'Золото', '585', 'yellow_gold', 'Сапфир',
                null, 42000,
                'Природные сапфиры. Вес пары 3,2 г.',
                '3.20', '2.90', '0.30', 'Сапфиры овальной огранки', 'Хорошее',
            ],
            [
                1003,
                'Золотая цепь якорного плетения',
                'Цепи', 'Якорное плетение',
                'Золото', '585', 'yellow_gold', null,
                '55 см', 36000,
                'Длина 55 см, ширина звена 3 мм. Вес 7,5 г.',
                '7.50', '7.50', null, null, 'Отличное',
            ],
        ];

        foreach ($itemsData as $row) {
            [
                $id, $name, $catName, $typeName,
                $metalName, $standard, $colorCode, $insertName,
                $size, $price, $desc,
                $itemWeight, $scrapWeight, $insertWeight, $insertDescription, $condition,
            ] = $row;

            $item = new PledgedItem();
            (new \ReflectionProperty(PledgedItem::class, 'id'))->setValue($item, $id);
            $item->setName($name)
                ->setSoldPrice((string) $price)
                ->setCurrency($rub)
                ->setStatus(PledgedItem::STATUS_FOR_SALE)
                ->setDescription($desc)
                ->setPublishedAt(new \DateTime());

            if ($size !== null)             { $item->setSize($size); }
            if (isset($categories[$catName])) { $item->setCategory($categories[$catName]); }

            $gtKey = $catName . '|' . $typeName;
            if (isset($goodTypeByCatAndName[$gtKey])) {
                $item->setGoodType($goodTypeByCatAndName[$gtKey]);
            }
            if ($colorCode !== null && isset($colorMap[$colorCode])) {
                $item->setMetalColor($colorMap[$colorCode]);
            }
            if (isset($standardMap["$metalName-$standard"])) {
                $item->setMetalStandard($standardMap["$metalName-$standard"]);
            }
            if ($itemWeight !== null)        { $item->setItemWeight($itemWeight); }
            if ($scrapWeight !== null)       { $item->setScrapWeight($scrapWeight); }
            if ($condition !== null)         { $item->setCondition($condition); }

            if ($insertName !== null && isset($insertMap[$insertName])) {
                $itemInsert = new PledgedItemInsert();
                $itemInsert->setInsert($insertMap[$insertName]);
                $itemInsert->setPledgedItem($item);
                if ($insertWeight !== null)      { $itemInsert->setWeight($insertWeight); }
                if ($insertDescription !== null) { $itemInsert->setDescription($insertDescription); }
                $item->addItemInsert($itemInsert);
            }

            $this->em->persist($item);
        }
        $this->em->flush();
        $io->writeln('✓ ' . count($itemsData) . ' позиций на витрине');

        $io->success('База успешно заполнена.');

        return Command::SUCCESS;
    }
}
