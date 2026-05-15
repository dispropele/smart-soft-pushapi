<?php

namespace App\Controller\Admin;

use App\Entity\LoanTicket;
use App\Entity\Tariff;
use App\Service\RepledgeService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;

class LoanTicketCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return LoanTicket::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Залоговый билет')
            ->setEntityLabelInPlural('Залоговые билеты')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('ticketNumber', 'Номер билета')
            ->setFormTypeOptions(['disabled' => true])
            ->hideWhenCreating();

        yield AssociationField::new('client', 'Клиент')->autocomplete();

        yield MoneyField::new('loanAmount', 'Сумма займа')
            ->setCurrency('RUB')
            ->setStoredAsCents(false)
            ->setRequired(true)
            ->setFormTypeOptions([
                'attr' => [
                    'inputmode' => 'decimal',
                    'min'       => '0.01',
                    'step'      => '0.01',
                ],
            ]);

        // ── Тариф: выбирается в форме, dailyInterestRate заполняется автоматически ──
        yield AssociationField::new('tariff', 'Тариф')
            ->autocomplete()
            ->setRequired(false)
            ->setHelp('Ставки в БД заполняются автоматически из выбранного тарифа')
            ->setQueryBuilder(fn ($qb) => $qb->andWhere('entity.isActive = true'))
            ->formatValue(fn (?Tariff $t) => $t ? (string) $t : '—');

        yield IntegerField::new('graceDays', 'Льготный период (дней)')
            ->setFormTypeOptions([
                'attr' => ['min' => 0, 'max' => 3650, 'inputmode' => 'numeric'],
            ])
            ->setHelp('По умолчанию 30 дней');

        yield DateTimeField::new('issuedAt', 'Дата выдачи')
            ->setFormat('dd.MM.yyyy HH:mm');

        yield DateTimeField::new('returnDate', 'Срок возврата')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->setHelp('По умолчанию: дата выдачи + 30 дней');

        yield DateTimeField::new('closedAt', 'Дата закрытия')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->onlyOnDetail();

        // Суммы к возврату — только на детальной странице
        yield TextField::new('returnAmount', 'Сумма к возврату')
            ->onlyOnDetail()
            ->formatValue(fn ($v) => $v ? number_format((float) $v, 2, '.', ' ') . ' ₽' : '—');

        yield TextField::new('graceReturnAmount', 'Сумма с льготным периодом')
            ->onlyOnDetail()
            ->formatValue(fn ($v) => $v ? number_format((float) $v, 2, '.', ' ') . ' ₽' : '—');

        // Статус
        yield TextField::new('status', 'Статус')
            ->formatValue(fn ($v) => match ($v) {
                LoanTicket::STATUS_OPEN      => '<span style="color:#5cb85c;font-weight:600">● Открыт</span>',
                LoanTicket::STATUS_GRACE     => '<span style="color:#f0ad4e;font-weight:600">● Льготный период</span>',
                LoanTicket::STATUS_CLOSED    => '<span style="color:#777">● Закрыт</span>',
                LoanTicket::STATUS_EXPIRED   => '<span style="color:#d9534f;font-weight:600">● Просрочен</span>',
                LoanTicket::STATUS_REPLEDGED => '<span style="color:#1d4e75;font-weight:600">● Перезалог</span>',
                LoanTicket::STATUS_CANCELLED => '<span style="color:#6c757d;font-weight:600">✕ Аннулирован</span>',
                default                      => $v ?? '—',
            })
            ->renderAsHtml()
            ->hideOnForm();

        yield ChoiceField::new('status', 'Статус')
            ->onlyWhenCreating()
            ->setChoices([
                'Открыт'          => LoanTicket::STATUS_OPEN,
                'Льготный период' => LoanTicket::STATUS_GRACE,
                'Закрыт'          => LoanTicket::STATUS_CLOSED,
                'Просрочен'       => LoanTicket::STATUS_EXPIRED,
                'Перезалог'       => LoanTicket::STATUS_REPLEDGED,
                'Аннулирован'     => LoanTicket::STATUS_CANCELLED,
            ]);

        // Связи перезалога
        yield AssociationField::new('repledgedFrom', 'Исходный билет')
            ->onlyOnDetail()
            ->formatValue(fn ($v) => $v ? (string) $v : '—');

        yield AssociationField::new('repledgedTo', 'Новый билет (перезалог)')
            ->onlyOnDetail()
            ->formatValue(fn ($v) => $v ? (string) $v : '—');

        // Оплаты
        yield MoneyField::new('paidInterest', 'Оплачено процентов')
            ->setCurrency('RUB')->setStoredAsCents(false)
            ->onlyOnDetail();

        yield MoneyField::new('paidPrincipal', 'Оплачено по телу займа')
            ->setCurrency('RUB')->setStoredAsCents(false)
            ->onlyOnDetail();

        yield AssociationField::new('tariff', 'Тариф')
            ->onlyOnDetail()
            ->formatValue(fn (?Tariff $t) => $t ? (string) $t : '—');

        yield NumberField::new('dailyInterestRate', 'Ежедневная ставка (%/день)')
            ->setNumDecimals(2)
            ->formatValue(fn ($v) => $v !== null && $v !== '' ? Tariff::formatPercent((float) $v) : '—')
            ->onlyOnDetail();

        yield NumberField::new('interestRate', 'Процент в месяц (%)')
            ->setNumDecimals(2)
            ->formatValue(fn ($v) => $v !== null && $v !== '' ? Tariff::formatPercent((float) $v) : '—')
            ->onlyOnDetail();

        yield CollectionField::new('pledgedItems', 'Предметы залога')
            ->useEntryCrudForm(PledgedItemCrudController::class)
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex(true)
            ->onlyOnForms();

        yield TextField::new('notes', 'Примечания')
            ->setFormTypeOptions(['attr' => ['maxlength' => 10000]]);

        yield DateTimeField::new('createdAt', 'Создан')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->onlyOnDetail();
    }

    public function configureActions(Actions $actions): Actions
    {
        $repledge = Action::new('repledge', 'Перезалог', 'fa fa-refresh')
            ->linkToCrudAction('repledgeAction')
            ->addCssClass('btn btn-warning')
            ->displayIf(fn (LoanTicket $t) => $t->isActive());

        $redeem = Action::new('redeem', 'Выкуп клиентом', 'fa fa-check-circle')
            ->linkToCrudAction('redeemAction')
            ->addCssClass('btn btn-success')
            ->displayIf(fn (LoanTicket $t) => $t->isActive());

        $moveToSale = Action::new('moveToSale', 'Передать на реализацию', 'fa fa-tag')
            ->linkToCrudAction('moveToSaleAction')
            ->addCssClass('btn btn-danger')
            ->displayIf(fn (LoanTicket $t) => !$t->isClosed() && !$t->isRepledged());

        $print = Action::new('print', 'Распечатать билет', 'fa fa-print')
            ->linkToUrl(fn (LoanTicket $t) => '/admin/print/ticket/' . $t->getId())
            ->setHtmlAttributes(['target' => '_blank'])
            ->addCssClass('btn btn-secondary');

        $annul = Action::new('annul', 'Аннулировать', 'fa fa-ban')
            ->linkToCrudAction('annulAction')
            ->addCssClass('btn btn-outline-danger')
            ->displayIf(fn (LoanTicket $t) => $t->getStatus() !== LoanTicket::STATUS_CANCELLED);

        return $actions
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $repledge)
            ->add(Crud::PAGE_DETAIL, $redeem)
            ->add(Crud::PAGE_DETAIL, $moveToSale)
            ->add(Crud::PAGE_DETAIL, $print)
            ->add(Crud::PAGE_DETAIL, $annul);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('client', 'Клиент'))
            ->add(ChoiceFilter::new('status', 'Статус')->setChoices([
                'Открыт'          => LoanTicket::STATUS_OPEN,
                'Льготный период' => LoanTicket::STATUS_GRACE,
                'Закрыт'          => LoanTicket::STATUS_CLOSED,
                'Просрочен'       => LoanTicket::STATUS_EXPIRED,
                'Перезалог'       => LoanTicket::STATUS_REPLEDGED,
                'Аннулирован'     => LoanTicket::STATUS_CANCELLED,
            ]));
    }

    // ── persist / update ──────────────────────────────────────────────────────

    public function persistEntity(EntityManagerInterface $em, $entity): void
    {
        if ($entity instanceof LoanTicket) {
            if (!$entity->getTicketNumber()) {
                $entity->setTicketNumber($this->generateTicketNumber());
            }
            $this->applyTariff($entity);
        }
        parent::persistEntity($em, $entity);
    }

    public function updateEntity(EntityManagerInterface $em, $entity): void
    {
        if ($entity instanceof LoanTicket) {
            $this->applyTariff($entity);
        }
        parent::updateEntity($em, $entity);
    }

    /**
     * Копирует dailyInterestRate из тарифа, если задан тариф и ставка не переопределена.
     * Если interestRate тоже пуст — конвертирует ежедневную ставку в месячную (×30).
     */
    private function applyTariff(LoanTicket $ticket): void
    {
        $tariff = $ticket->getTariff();
        if ($tariff === null) {
            return;
        }

        $ticket->setDailyInterestRate($tariff->getDailyRate());
        $ticket->setInterestRate($tariff->getMonthlyRate());
    }

    // ── Custom actions ────────────────────────────────────────────────────────

    public function repledgeAction(
        AdminContext $context,
        RepledgeService $service,
        EntityManagerInterface $em,
        FormFactoryInterface $formFactory
    ): Response {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        /** @var LoanTicket $ticket */
        $ticket = $em->find(LoanTicket::class, $entityId);
        if (!$ticket) { throw $this->createNotFoundException(); }

        $accrued = $ticket->getAccruedInterest();
        $totalDebt = $ticket->getTotalDebt();
        $originalId = $ticket->getId();

        $form = $formFactory->create(\App\Form\RepledgeType::class, [
            'paymentAmount' => $accrued,
            'extensionDays' => LoanTicket::DEFAULT_LOAN_DAYS,
        ], [
            'loan_ticket' => $ticket,
            'accrued_interest' => $accrued,
        ]);

        $form->handleRequest($context->getRequest());
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $result = $service->createRepledge(
                    $ticket,
                    paymentAmount: (string) $data['paymentAmount'],
                    extensionDays: (int) $data['extensionDays'],
                    notes: $data['notes'] ?? null
                );

                if ($result->isClosed() && !$result->isRepledged()) {
                    $this->addFlash('success', sprintf(
                        'Билет выкуплен. Оплачено: %.2f ₽ (проценты) + %.2f ₽ (тело). Предметы отмечены как «Выкуплен».',
                        (float) $result->getPaidInterest(),
                        (float) $result->getPaidPrincipal()
                    ));
                } else {
                    $this->addFlash('success', sprintf(
                        'Перезалог оформлен. Новый билет: %s. На закрытом билете %s оплачено: %.2f ₽ (проценты) + %.2f ₽ (тело).',
                        $result->getTicketNumber(),
                        $ticket->getTicketNumber(),
                        (float) $ticket->getPaidInterest(),
                        (float) $ticket->getPaidPrincipal()
                    ));
                }

                return $this->redirect(
                    $this->container->get(AdminUrlGenerator::class)
                        ->setController(self::class)->setAction(Action::DETAIL)
                        ->setEntityId($originalId)->generateUrl()
                );
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/repledge_form.html.twig', [
            'ticket' => $ticket,
            'form' => $form->createView(),
            'accrued' => $accrued,
            'totalDebt' => $totalDebt,
        ]);
    }

    public function redeemAction(
        AdminContext $context,
        RepledgeService $service,
        EntityManagerInterface $em
    ): Response {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        /** @var LoanTicket $ticket */
        $ticket = $em->find(LoanTicket::class, $entityId);
        if (!$ticket) { throw $this->createNotFoundException(); }

        $service->redeem($ticket);
        $this->addFlash('success', 'Залог выкуплен. Предметы отмечены как «Выкуплен».');

        return $this->redirect(
            $this->container->get(AdminUrlGenerator::class)
                ->setController(self::class)->setAction(Action::DETAIL)
                ->setEntityId($ticket->getId())->generateUrl()
        );
    }

    public function moveToSaleAction(
        AdminContext $context,
        RepledgeService $service,
        EntityManagerInterface $em
    ): Response {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        /** @var LoanTicket $ticket */
        $ticket = $em->find(LoanTicket::class, $entityId);
        if (!$ticket) { throw $this->createNotFoundException(); }

        $service->moveToSale($ticket);
        $this->addFlash('success', 'Предметы переданы на реализацию.');

        return $this->redirect(
            $this->container->get(AdminUrlGenerator::class)
                ->setController(self::class)->setAction(Action::DETAIL)
                ->setEntityId($ticket->getId())->generateUrl()
        );
    }

    public function annulAction(AdminContext $context, EntityManagerInterface $em): Response
    {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        $ticket   = $em->find(LoanTicket::class, $entityId);
        if (!$ticket) { throw $this->createNotFoundException(); }

        if ($context->getRequest()->query->get('confirmed') === '1') {
            $ticket->setStatus(LoanTicket::STATUS_CANCELLED);
            $ticket->setClosedAt(new \DateTime());
            $em->flush();

            $this->addFlash('warning', "Билет {$ticket->getTicketNumber()} аннулирован.");

            return $this->redirect(
                $this->container->get(AdminUrlGenerator::class)
                    ->setController(self::class)->setAction(Action::INDEX)->generateUrl()
            );
        }

        $confirmUrl = $this->container->get(AdminUrlGenerator::class)
            ->setController(self::class)->setAction('annulAction')
            ->setEntityId($entityId)->set('confirmed', '1')->generateUrl();

        $backUrl = $this->container->get(AdminUrlGenerator::class)
            ->setController(self::class)->setAction(Action::DETAIL)
            ->setEntityId($entityId)->generateUrl();

        return $this->render('admin/confirm_annul.html.twig', [
            'ticket'     => $ticket,
            'confirmUrl' => $confirmUrl,
            'backUrl'    => $backUrl,
        ]);
    }

    private function generateTicketNumber(): string
    {
        return 'ЛБ-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
