<?php

namespace App\Command;

use App\Service\SmartLombardHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-data', description: 'Заполняет БД тестовыми данными через PushAPI Handler')]
class SeedDataCommand extends Command
{
    public function __construct(private SmartLombardHandler $handler) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Имитируем JSON от сервиса
        $payload = [[
            'data' => [
                'merchants' => [
                    [
                        'type' => 'add',
                        'data' => [
                            'workplace' => 1,
                            'name' => 'Центральный СмартЛомбард',
                            'city' => 'Москва',
                            'address' => 'ул. Пушкина, д. Колотушкина',
                            'phone' => '+7 (999) 000-11-22',
                            'image' => [
                                'src' => 'https://placehold.co/800x600.png?text=Merchant+Logo',
                                'preview' => 'https://placehold.co/200x150.png?text=Thumb'
                            ]
                        ]
                    ]
                ],
                'goods' => [
                    [
                        'type' => 'add',
                        'article' => 101,
                        'data' => [
                            'workplace' => 1,
                            'name' => 'iPhone 15 Pro Max 256GB',
                            'price' => '95000',
                            'features' => 'Состояние идеальное, полный комплект, на гарантии.',
                            'images' => [
                                ['src' => 'https://placehold.co/800x800.png?text=iPhone+Front', 'preview' => 'https://placehold.co/300x300.png?text=iPhone+Thumb', 'cover' => 1],
                                ['src' => 'https://placehold.co/800x800.png?text=iPhone+Back', 'preview' => 'https://placehold.co/300x300.png?text=iPhone+Thumb', 'cover' => 0]
                            ],
                            'date' => date('d.m.Y H:i')
                        ]
                    ],
                    [
                        'type' => 'add',
                        'article' => 102,
                        'data' => [
                            'workplace' => 1,
                            'name' => 'Золотое кольцо 585 проба',
                            'price' => '15400',
                            'features' => 'Вес 3.5г, размер 17.5. Камень: фианит.',
                            'images' => [
                                ['src' => 'https://placehold.co/800x800.png?text=iPhone+Front', 'preview' => 'https://placehold.co/300x300.png?text=iPhone+Thumb', 'cover' => 1],
                            ],
                            'date' => date('d.m.Y H:i')
                        ]
                    ],
                    [
                        'type' => 'add',
                        'article' => 103,
                        'data' => [
                            'workplace' => 1,
                            'name' => 'Игровая приставка PS5 Slim',
                            'price' => '48000',
                            'features' => 'Вторая ревизия, один геймпад, без дисковода.',
                            'images' => [
                                ['src' => 'https://placehold.co/800x800.png?text=iPhone+Front', 'preview' => 'https://placehold.co/300x300.png?text=iPhone+Thumb', 'cover' => 1],
                            ],
                            'date' => date('d.m.Y H:i')
                        ]
                    ]
                ]
            ]
        ]];

        $io->info('Начинаю импорт данных...');
        $this->handler->handleWebhook($payload);
        $this->handler->flush();

        $io->success('Данные успешно загружены! Картинки скачаны в public/uploads/sl_images/');

        return Command::SUCCESS;
    }

}
