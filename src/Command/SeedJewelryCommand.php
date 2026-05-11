<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\GoodType;
use App\Entity\StoneType;
use App\Entity\MetalColor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-jewelry', description: 'Заполняет БД справочниками ювелирных типов, камней и цветов металлов')]
class SeedJewelryCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Типы камней
        $stoneTypes = [
            ['code' => 'diamond', 'name' => 'Бриллиант'],
            ['code' => 'cubic_zirconia', 'name' => 'Фианит'],
            ['code' => 'emerald', 'name' => 'Изумруд'],
            ['code' => 'sapphire', 'name' => 'Сапфир'],
            ['code' => 'ruby', 'name' => 'Рубин'],
            ['code' => 'topaz', 'name' => 'Топаз'],
            ['code' => 'amethyst', 'name' => 'Аметист'],
        ];

        foreach ($stoneTypes as $data) {
            $existing = $this->em->getRepository(StoneType::class)->findOneBy(['code' => $data['code']]);
            if (!$existing) {
                $stone = new StoneType();
                $stone->setCode($data['code']);
                $stone->setName($data['name']);
                $this->em->persist($stone);
                $io->writeln('✓ Создан тип камня: ' . $data['name']);
            }
        }

        // Цвета металлов
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

        // Виды изделий (группированы по категориям)
        $categories = $this->em->getRepository(Category::class)->findAll();

        // Универсальные типы для всех категорий
        $universalTypes = [
            ['code' => 'with_stones', 'name' => 'С камнями', 'has_stones' => true],
            ['code' => 'no_stones', 'name' => 'Без вставок', 'has_stones' => false],
        ];

        // Типы специфичные для конкретных категорий
        $categorySpecificTypes = [
            'Кольца' => [
                ['code' => 'wedding_rings', 'name' => 'Обручальные', 'has_stones' => false],
                ['code' => 'engagement_rings', 'name' => 'Помолвочные', 'has_stones' => true],
                ['code' => 'cocktail_rings', 'name' => 'Коктейльные', 'has_stones' => true],
            ],
            'Серьги' => [
                ['code' => 'stud_earrings', 'name' => 'Гвоздики', 'has_stones' => true],
                ['code' => 'drop_earrings', 'name' => 'Висячие', 'has_stones' => true],
                ['code' => 'hoops', 'name' => 'Кольца', 'has_stones' => false],
            ],
            'Цепи' => [
                ['code' => 'thin_chains', 'name' => 'Тонкие', 'has_stones' => false],
                ['code' => 'thick_chains', 'name' => 'Толстые', 'has_stones' => false],
            ],
            'Браслеты' => [
                ['code' => 'tennis_bracelets', 'name' => 'Теннисные', 'has_stones' => true],
                ['code' => 'charm_bracelets', 'name' => 'С подвесками', 'has_stones' => true],
                ['code' => 'bangles', 'name' => 'Браслеты-жёсткие', 'has_stones' => false],
            ],
        ];

        foreach ($categories as $category) {
            // Добавляем универсальные типы
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
                    $io->writeln("✓ Для категории '{$category->getName()}' создан вид: {$data['name']}");
                }
            }

            // Добавляем специфичные типы
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
                        $io->writeln("✓ Для категории '{$category->getName()}' создан вид: {$data['name']}");
                    }
                }
            }
        }

        $this->em->flush();

        $io->success('Справочники ювелирных данных успешно заполнены!');
        return Command::SUCCESS;
    }
}
