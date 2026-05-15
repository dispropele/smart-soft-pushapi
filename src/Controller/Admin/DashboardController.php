<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Client;
use App\Entity\Currency;
use App\Entity\GoodType;
use App\Entity\Insert;
use App\Entity\InsertType;
use App\Entity\LoanTicket;
use App\Entity\Metal;
use App\Entity\MetalColor;
use App\Entity\MetalStandard;
use App\Entity\PledgedItem;
use App\Entity\PushApiLog;
use App\Entity\Tariff;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Аурум Ломбард · Админ')
            ->renderContentMaximized();
    }

    public function configureAssets(): Assets
    {
        return parent::configureAssets()
            ->addAssetMapperEntry('app')
            ->addHtmlContentToBody(
                '<div data-controller="admin-form-mask" id="ea-admin-form-mask" hidden aria-hidden="true"></div>'
            );
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Сводка', 'fa fa-home');

        yield MenuItem::section('Ломбард');
        yield MenuItem::linkToCrud('Клиенты', 'fa fa-users', Client::class);
        yield MenuItem::linkToCrud('Залоговые билеты', 'fa fa-file-text', LoanTicket::class);
        yield MenuItem::linkToCrud('Предметы залога / Витрина', 'fa fa-cubes', PledgedItem::class);

        yield MenuItem::section('Справочники');
        yield MenuItem::linkToCrud('Категории', 'fa fa-tags', Category::class);
        yield MenuItem::linkToCrud('Виды изделий', 'fa fa-ring', GoodType::class);
        yield MenuItem::linkToCrud('Типы вставок', 'fa fa-gem', InsertType::class);
        yield MenuItem::linkToCrud('Вставки', 'fa fa-diamond', Insert::class);
        yield MenuItem::linkToCrud('Цвета металлов', 'fa fa-palette', MetalColor::class);
        yield MenuItem::linkToCrud('Металлы', 'fa fa-cubes', Metal::class);
        yield MenuItem::linkToCrud('Пробы', 'fa fa-certificate', MetalStandard::class);
        yield MenuItem::linkToCrud('Валюты', 'fa fa-money', Currency::class);
        yield MenuItem::linkToCrud('Тарифы', 'fa fa-percent', Tariff::class);

        yield MenuItem::section('Отчёты');
        yield MenuItem::linkToUrl('Продажи за период', 'fa fa-bar-chart', '/admin/reports/sold-items');

        yield MenuItem::section('Система');
        yield MenuItem::linkToCrud('Логи Push API', 'fa fa-list-alt', PushApiLog::class);

        yield MenuItem::section();
        yield MenuItem::linkToUrl('← На витрину', 'fa fa-arrow-left', '/');
    }
}