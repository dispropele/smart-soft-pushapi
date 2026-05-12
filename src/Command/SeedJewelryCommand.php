<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\GoodType;
use App\Entity\Insert;
use App\Entity\InsertType;
use App\Entity\MetalColor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-jewelry', description: 'Дополняет БД справочниками ювелирных типов, камней и цветов металлов (русские названия)')]
class SeedJewelryCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $insertTypeDefs = [
            'Драгоценные камни',
            'Полудрагоценные камни',
            'Синтетические вставки',
        ];

        foreach ($insertTypeDefs as $typeName) {
            $existing = $this->em->getRepository(InsertType::class)->findOneBy(['name' => $typeName]);
            if (!$existing) {
                $type = new InsertType();
                $type->setName($typeName);
                $this->em->persist($type);
                $io->writeln('✓ Создан тип вставки: ' . $typeName);
            }
        }
        $this->em->flush();

        $insertDefs = [
            ['Бриллиант', 'Драгоценные камни'],
            ['Фианит', 'Синтетические вставки'],
            ['Изумруд', 'Драгоценные камни'],
            ['Сапфир', 'Драгоценные камни'],
            ['Рубин', 'Драгоценные камни'],
            ['Топаз', 'Полудрагоценные камни'],
            ['Аметист', 'Полудрагоценные камни'],
        ];

        foreach ($insertDefs as [$insertName, $typeName]) {
            $existing = $this->em->getRepository(Insert::class)->findOneBy(['name' => $insertName]);
            if ($existing) {
                continue;
            }
            $type = $this->em->getRepository(InsertType::class)->findOneBy(['name' => $typeName]);
            if (!$type) {
                continue;
            }
            $insert = new Insert();
            $insert->setName($insertName);
            $insert->setInsertType($type);
            $this->em->persist($insert);
            $io->writeln('✓ Создана вставка: ' . $insertName);
        }
        $this->em->flush();

        $metalColors = [
            ['code' => 'yellow_gold', 'name' => 'Жёлтое золото'],
            ['code' => 'white_gold', 'name' => 'Белое золото'],
            ['code' => 'rose_gold', 'name' => 'Розовое золото'],
            ['code' => 'platinum', 'name' => 'Платина'],
            ['code' => 'silver', 'name' => 'Серебро'],
            ['code' => 'palladium', 'name' => 'Палладий'],
        ];

        foreach ($metalColors as $data) {
            $existing = $this->em->getRepository(MetalColor::class)->findOneBy(['code' => $data['code']]);
            if (!$existing) {
                $color = new MetalColor();
                $color->setCode($data['code']);
                $color->setName($data['name']);
                $this->em->persist($color);
                $io->writeln('✓ Создан цвет металла: ' . $data['name']);
            }
        }

        $categories = $this->em->getRepository(Category::class)->findAll();

        $universalTypes = [
            ['code' => 'with_stones', 'name' => 'С камнями', 'has_stones' => true],
            ['code' => 'no_stones', 'name' => 'Без вставок', 'has_stones' => false],
        ];

        $categorySpecificTypes = [
            'Кольца' => [
                ['code' => 'wedding_rings', 'name' => 'Обручальные', 'has_stones' => false],
                ['code' => 'engagement_rings', 'name' => 'Помолвочные', 'has_stones' => true],
                ['code' => 'cocktail_rings', 'name' => 'Коктейльные', 'has_stones' => true],
            ],
            'Серьги' => [
                ['code' => 'stud_earrings', 'name' => 'Пусеты', 'has_stones' => true],
                ['code' => 'drop_earrings', 'name' => 'Длинные', 'has_stones' => true],
                ['code' => 'hoops', 'name' => 'Конго', 'has_stones' => false],
            ],
            'Цепи' => [
                ['code' => 'thin_chains', 'name' => 'Тонкие', 'has_stones' => false],
                ['code' => 'thick_chains', 'name' => 'Толстые', 'has_stones' => false],
            ],
            'Браслеты' => [
                ['code' => 'tennis_bracelets', 'name' => 'Теннисные', 'has_stones' => true],
                ['code' => 'charm_bracelets', 'name' => 'С подвесками', 'has_stones' => true],
                ['code' => 'bangles', 'name' => 'Жёсткие', 'has_stones' => false],
            ],
        ];

        foreach ($categories as $category) {
            foreach ($universalTypes as $data) {
                $existing = $this->em->getRepository(GoodType::class)->findOneBy([
                    'code' => $data['code'],
                    'category' => $category,
                ]);
                if (!$existing) {
                    $type = new GoodType();
                    $type->setCode($data['code']);
                    $type->setName($data['name']);
                    $type->setCategory($category);
                    $type->setHasStones($data['has_stones']);
                    $this->em->persist($type);
                    $io->writeln("✓ Для категории «{$category->getName()}» создан вид: {$data['name']}");
                }
            }

            if (isset($categorySpecificTypes[$category->getName()])) {
                foreach ($categorySpecificTypes[$category->getName()] as $data) {
                    $existing = $this->em->getRepository(GoodType::class)->findOneBy([
                        'code' => $data['code'],
                        'category' => $category,
                    ]);
                    if (!$existing) {
                        $type = new GoodType();
                        $type->setCode($data['code']);
                        $type->setName($data['name']);
                        $type->setCategory($category);
                        $type->setHasStones($data['has_stones']);
                        $this->em->persist($type);
                        $io->writeln("✓ Для категории «{$category->getName()}» создан вид: {$data['name']}");
                    }
                }
            }
        }

        $this->em->flush();

        $io->success('Справочники ювелирных данных обновлены.');
        return Command::SUCCESS;
    }
}
