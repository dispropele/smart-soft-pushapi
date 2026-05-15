<?php

namespace App\Controller\Admin;

use App\Entity\Admin;
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
use App\Repository\LoanTicketRepository;
use App\Repository\PledgedItemRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly LoanTicketRepository $loanTicketRepo,
        private readonly PledgedItemRepository $pledgedItemRepo,
        private readonly AdminUrlGenerator $adminUrlGenerator
    ) {
    }

    public function index(): Response
    {
        // 1. Индикаторы
        $activeLoansSum = $this->loanTicketRepo->getSumForStatus(LoanTicket::STATUS_OPEN);
        $graceLoansSum = $this->loanTicketRepo->getSumForStatus(LoanTicket::STATUS_GRACE);
        $onSaleValue = $this->pledgedItemRepo->getSumForStatus(PledgedItem::STATUS_FOR_SALE);

        // 2. Последние 5 операций
        $latestTickets = $this->loanTicketRepo->findBy([], ['issuedAt' => 'DESC'], 5);
        $latestSales = $this->pledgedItemRepo->findLatestSales(5);

        // Объединяем и сортируем по дате
        $latestOps = array_merge($latestTickets, $latestSales);
        usort($latestOps, static function ($a, $b) {
            $dateA = match(true) {
                $a instanceof LoanTicket => $a->getIssuedAt(),
                $a instanceof PledgedItem => $a->getStatusDate(),
                default => null
            };
            $dateB = match(true) {
                $b instanceof LoanTicket => $b->getIssuedAt(),
                $b instanceof PledgedItem => $b->getStatusDate(),
                default => null
            };
            return ($dateB?->getTimestamp() ?? 0) <=> ($dateA?->getTimestamp() ?? 0);
        });
        $latestOps = array_slice($latestOps, 0, 5);

        // 3. Cashflow chart data (за неделю)
        $cashflowData = [];
        try {
            $issuedByDay = $this->loanTicketRepo->getIssuedByDayLastWeek();
            $closedByDay = $this->loanTicketRepo->getClosedByDayLastWeek();
            $salesByDay = $this->pledgedItemRepo->getSalesByDayLastWeek();

            // Объединяем данные по датам
            $dateRange = $this->getLastWeekDates();
            foreach ($dateRange as $date) {
                $dateStr = $date->format('Y-m-d');
                $issued = 0;
                $received = 0;

                foreach ($issuedByDay as $item) {
                    if ((string)$item['date'] === $dateStr) {
                        $issued = $item['amount'];
                        break;
                    }
                }

                foreach ($closedByDay as $item) {
                    if ((string)$item['date'] === $dateStr) {
                        $received += $item['amount'];
                    }
                }

                foreach ($salesByDay as $item) {
                    if ((string)$item['date'] === $dateStr) {
                        $received += $item['amount'];
                    }
                }

                $cashflowData[] = [
                    'date' => $date->format('d.m'),
                    'issued' => $issued,
                    'received' => $received,
                ];
            }
        } catch (\Exception $e) {
            // Если ошибка при получении данных Cashflow, используем пустой набор
            $dateRange = $this->getLastWeekDates();
            foreach ($dateRange as $date) {
                $cashflowData[] = [
                    'date' => $date->format('d.m'),
                    'issued' => 0,
                    'received' => 0,
                ];
            }
        }

        // 4. URL для кнопки "Создать"
        $createTicketUrl = $this->adminUrlGenerator
            ->setController(LoanTicketCrudController::class)
            ->setAction(Action::NEW)
            ->generateUrl();

        return $this->render('admin/dashboard.html.twig', [
            'activeLoansSum' => $activeLoansSum,
            'graceLoansSum' => $graceLoansSum,
            'onSaleValue' => $onSaleValue,
            'latestOperations' => $latestOps,
            'createTicketUrl' => $createTicketUrl,
            'cashflowData' => $cashflowData,
        ]);
    }

    /** Получить даты последних 7 дней */
    private function getLastWeekDates(): array
    {
        $dates = [];
        for ($i = 6; $i >= 0; $i--) {
            $dates[] = (new \DateTime())->modify("-$i days");
        }
        return $dates;
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
        yield MenuItem::linkTo(ReportController::class, 'Продажи за период', 'fa fa-bar-chart');

        yield MenuItem::section('Система');
        yield MenuItem::linkToCrud('Администраторы', 'fa fa-user-shield', Admin::class);
        yield MenuItem::linkToCrud('Логи Push API', 'fa fa-list-alt', PushApiLog::class);

        yield MenuItem::section();
        yield MenuItem::linkToUrl('← На витрину', 'fa fa-arrow-left', '/');
    }
}