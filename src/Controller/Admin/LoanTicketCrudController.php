<?php

namespace App\Controller\Admin;

use App\Entity\LoanTicket;
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
            ->setCurrency('RUB')->setStoredAsCents(false)->setRequired(true)
            ->setFormTypeOptions([
                'attr' => [
                    'inputmode' => 'decimal',
                    'min' => '0.01',
                    'step' => '0.01',
                    'placeholder' => '0.00',
                ],
            ]);

        yield NumberField::new('interestRate', 'Процент в месяц (%)')
            ->setNumDecimals(2)
            ->setFormTypeOptions([
                'required' => false,
                'attr' => [
                    'data-admin-mask' => 'decimal2',
                    'inputmode' => 'decimal',
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100',
                ],
            ])
            ->setHelp('Применяется к основному сроку и отдельно к льготному');

        yield IntegerField::new('graceDays', 'Льготный период (дней)')
            ->setFormTypeOptions([
                'attr' => [
                    'min' => 0,
                    'max' => 3650,
                    'inputmode' => 'numeric',
                    'maxlength' => 4,
                ],
            ])
            ->setHelp('По умолчанию 30 дней после окончания основного срока');

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
            ->setFormTypeOptions(['disabled' => true])
            ->formatValue(fn($v) => $v ? number_format((float)$v, 2, '.', ' ') . ' ₽' : '—');

        yield TextField::new('graceReturnAmount', 'Сумма с льготным периодом')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $v ? number_format((float)$v, 2, '.', ' ') . ' ₽' : '—');

        // Статус
        yield TextField::new('status', 'Статус')
            ->formatValue(fn($v) => match($v) {
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
            ->onlyOnForms()
            ->setChoices([
                'Открыт'           => LoanTicket::STATUS_OPEN,
                'Льготный период'  => LoanTicket::STATUS_GRACE,
                'Закрыт'           => LoanTicket::STATUS_CLOSED,
                'Просрочен'        => LoanTicket::STATUS_EXPIRED,
                'Перезалог'        => LoanTicket::STATUS_REPLEDGED,
                'Аннулирован'      => LoanTicket::STATUS_CANCELLED,
            ]);

        // Связи перезалога
        yield AssociationField::new('repledgedFrom', 'Исходный билет')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $v ? (string)$v : '—');

        yield AssociationField::new('repledgedTo', 'Новый билет (перезалог)')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $v ? (string)$v : '—');

        // Оплаты (перезалог)
        yield MoneyField::new('paidInterest', 'Оплачено процентов')
            ->setCurrency('RUB')->setStoredAsCents(false)
            ->onlyOnDetail();

        yield MoneyField::new('paidPrincipal', 'Оплачено по телу займа')
            ->setCurrency('RUB')->setStoredAsCents(false)
            ->onlyOnDetail();

        // Ежедневная ставка и тариф
        yield NumberField::new('dailyInterestRate', 'Ежедневная ставка (%)')
            ->setNumDecimals(4)
            ->onlyOnDetail();

        yield AssociationField::new('tariff', 'Тариф')
            ->onlyOnDetail();

        yield TextField::new('notes', 'Примечания')
            ->setFormTypeOptions(['attr' => ['maxlength' => 10000]]);

        // Предметы залога
        yield CollectionField::new('pledgedItems', 'Предметы залога')
            ->useEntryCrudForm(PledgedItemCrudController::class)
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex(true)
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Создан')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->onlyOnDetail();
    }

    public function configureActions(Actions $actions): Actions
    {
        $repledge = Action::new('repledge', 'Перезалог', 'fa fa-refresh')
            ->linkToCrudAction('repledgeAction')
            ->addCssClass('btn btn-warning')
            ->displayIf(fn(LoanTicket $t) => $t->isActive());

        $redeem = Action::new('redeem', 'Выкуп клиентом', 'fa fa-check-circle')
            ->linkToCrudAction('redeemAction')
            ->addCssClass('btn btn-success')
            ->displayIf(fn(LoanTicket $t) => $t->isActive());

        $moveToSale = Action::new('moveToSale', 'Передать на реализацию', 'fa fa-tag')
            ->linkToCrudAction('moveToSaleAction')
            ->addCssClass('btn btn-danger')
            ->displayIf(fn(LoanTicket $t) => !$t->isClosed() && !$t->isRepledged());

        $print = Action::new('print', 'Распечатать билет', 'fa fa-print')
            ->linkToUrl(fn(LoanTicket $t) => '/admin/print/ticket/' . $t->getId())
            ->setHtmlAttributes(['target' => '_blank'])
            ->addCssClass('btn btn-secondary');

        $annul = Action::new('annul', 'Аннулировать', 'fa fa-ban')
            ->linkToCrudAction('annulAction')
            ->addCssClass('btn btn-outline-danger')
            ->displayIf(fn(LoanTicket $t) => $t->getStatus() !== LoanTicket::STATUS_CANCELLED);

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

    public function persistEntity(EntityManagerInterface $em, $entity): void
    {
        if ($entity instanceof LoanTicket && !$entity->getTicketNumber()) {
            $entity->setTicketNumber($this->generateTicketNumber());
        }
        parent::persistEntity($em, $entity);
    }

    // --- Custom actions ---

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
        $form = $formFactory->create(\App\Form\RepledgeType::class, [
            'paymentAmount' => $accrued,
            'extensionDays' => LoanTicket::DEFAULT_LOAN_DAYS,
        ], ['accrued_interest' => $accrued]);

        $form->handleRequest($context->getRequest());
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $new = $service->createRepledge(
                    $ticket,
                    paymentAmount:   (string)$data['paymentAmount'],
                    extensionDays:   (int)$data['extensionDays'],
                    notes:           $data['notes'] ?? null
                );
                $this->addFlash('success', sprintf(
                    'Перезалог оформлен. Новый билет: %s. Оплачено: %.2f ₽ (проценты) + %.2f ₽ (тело).',
                    $new->getTicketNumber(),
                    (float)$ticket->getPaidInterest(),
                    (float)$ticket->getPaidPrincipal()
                ));
                return $this->redirect(
                    $this->container->get(AdminUrlGenerator::class)
                        ->setController(self::class)->setAction(Action::DETAIL)
                        ->setEntityId($new->getId())->generateUrl()
                );
            } catch (\LogicException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

        return $this->render('admin/repledge_form.html.twig', [
            'ticket'   => $ticket,
            'form'     => $form->createView(),
            'accrued'  => $accrued,
        ]);
    }

    public function redeemAction(AdminContext $context, RepledgeService $service, EntityManagerInterface $em): Response
    {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        /** @var LoanTicket $ticket */
        $ticket = $em->find(LoanTicket::class, $entityId);
        if (!$ticket) { throw $this->createNotFoundException(); }

        $service->redeem($ticket);
        $this->addFlash('success', 'Залог выкуплен. Предметы отмечены как «Выкуплен».');

        return $this->redirect(
            $this->container->get(AdminUrlGenerator::class)
                ->setController(self::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($ticket->getId())
                ->generateUrl()
        );
    }

    public function moveToSaleAction(AdminContext $context, RepledgeService $service, EntityManagerInterface $em): Response
    {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        /** @var LoanTicket $ticket */
        $ticket = $em->find(LoanTicket::class, $entityId);
        if (!$ticket) { throw $this->createNotFoundException(); }

        $service->moveToSale($ticket);
        $this->addFlash('success', 'Предметы переданы на реализацию.');

        return $this->redirect(
            $this->container->get(AdminUrlGenerator::class)
                ->setController(self::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($ticket->getId())
                ->generateUrl()
        );
    }

    public function annulAction(AdminContext $context, EntityManagerInterface $em): Response
    {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        $ticket = $em->find(LoanTicket::class, $entityId);
        if (!$ticket) { throw $this->createNotFoundException(); }

        // Подтверждение через flash + GET (простой способ без отдельной формы)
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

        // Показываем экран подтверждения
        $confirmUrl = $this->container->get(AdminUrlGenerator::class)
            ->setController(self::class)
            ->setAction('annulAction')
            ->setEntityId($entityId)
            ->set('confirmed', '1')
            ->generateUrl();

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