<?php

namespace App\Controller\Admin;

use App\Entity\Good;
use App\Entity\Merchant;
use App\Entity\PushApiLog;
use App\Entity\Client;
use App\Entity\LoanTicket;
use App\Entity\LoanedItem;
use App\Controller\Admin\GoodCrudController;
use App\Controller\Admin\HiddenGoodCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
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
            ->setTitle('СмартЛомбард · Админ')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Сводка', 'fa fa-home');

        yield MenuItem::section('Ломбард');
        yield MenuItem::linkToCrud('Клиенты', 'fa fa-users', Client::class);
        yield MenuItem::linkToCrud('Залоговые билеты', 'fa fa-file-text', LoanTicket::class);
        yield MenuItem::linkToCrud('Заложенное имущество', 'fa fa-box', LoanedItem::class);

        yield MenuItem::section('Витрина');
        yield MenuItem::linkToCrud('Товары', 'fa fa-shopping-bag', Good::class)
            ->setController(GoodCrudController::class);
        yield MenuItem::linkToCrud('Товары (скрытые)', 'fa fa-eye-slash', Good::class)
            ->setController(HiddenGoodCrudController::class);

        yield MenuItem::section('Структура');
        yield MenuItem::linkToCrud('Филиалы', 'fa fa-building', Merchant::class);

        yield MenuItem::section('Система');
        yield MenuItem::linkToCrud('Логи Push API', 'fa fa-list-alt', PushApiLog::class);

        yield MenuItem::section();
        yield MenuItem::linkToUrl('← На витрину', 'fa fa-arrow-left', '/');
    }
}
