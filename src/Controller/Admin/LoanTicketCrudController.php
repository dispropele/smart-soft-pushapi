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
            ]);

        // Связи перезалога
        yield AssociationField::new('repledgedFrom', 'Исходный билет')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $v ? (string)$v : '—');

        yield AssociationField::new('repledgedTo', 'Новый билет (перезалог)')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $v ? (string)$v : '—');

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

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $repledge)
            ->add(Crud::PAGE_DETAIL, $redeem)
            ->add(Crud::PAGE_DETAIL, $moveToSale);
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

    public function repledgeAction(AdminContext $context, RepledgeService $service, EntityManagerInterface $em): Response
    {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        /** @var LoanTicket $ticket */
        $ticket = $em->find(LoanTicket::class, $entityId);
        if (!$ticket) { throw $this->createNotFoundException(); }

        $new = $service->createRepledge(
            $ticket,
            (string) ($ticket->getLoanAmount() ?? '0'),
            $ticket->getInterestRate()
        );
        $this->addFlash('success', "Перезалог создан: {$new->getTicketNumber()}");

        return $this->redirect(
            $this->container->get(AdminUrlGenerator::class)
                ->setController(self::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($new->getId())
                ->generateUrl()
        );
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

    private function generateTicketNumber(): string
    {
        return 'ЛБ-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}