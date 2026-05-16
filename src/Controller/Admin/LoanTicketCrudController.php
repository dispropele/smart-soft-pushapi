<?php

namespace App\Controller\Admin;

use App\Entity\LoanTicket;
use App\Entity\PledgedItem;
use App\Entity\Tariff;
use App\Service\RepledgeService;
use App\Entity\SystemLog;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
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

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addJsFile('assets/js/loan_ticket_form.js');
    }

    public function configureFields(string $pageName): iterable
    {
        $isEdit = $pageName === Crud::PAGE_EDIT;

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
                'attr' => ['inputmode' => 'decimal', 'min' => '0.01', 'step' => '0.01'],
                'disabled' => $isEdit,
            ]);

        yield AssociationField::new('tariff', 'Тариф')
            ->autocomplete()
            ->setRequired(false)
            ->setFormTypeOptions(['disabled' => $isEdit])
            ->setQueryBuilder(fn ($qb) => $qb->andWhere('entity.isActive = true'))
            ->formatValue(fn (?Tariff $t) => $t ? (string) $t : '—');

        yield IntegerField::new('graceDays', 'Льготный период (дней)')
            ->setFormTypeOptions(['attr' => ['min' => 0, 'max' => 3650]])
            ->setHelp('По умолчанию 30 дней');

        yield DateTimeField::new('issuedAt', 'Дата выдачи')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->setFormTypeOptions(['disabled' => $isEdit]);

        // Индикатор дней (renderAsHtml удален, заменен на корректный вывод Bootstrap 5)
        yield DateTimeField::new('returnDate', 'Срок возврата / Осталось дней')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->formatValue(function ($value, LoanTicket $ticket) {
                $dateStr = $value instanceof \DateTimeInterface ? $value->format('d.m.Y H:i') : '—';
                if ($ticket->getStatus() === LoanTicket::STATUS_OPEN || $ticket->getStatus() === LoanTicket::STATUS_GRACE) {
                    $daysLeft = $ticket->getExactDaysLeft();
                    $class = match (true) {
                        $daysLeft > 10 => 'text-success',
                        $daysLeft >= 1 => 'text-warning',
                        default => 'text-danger',
                    };
                    $text = $daysLeft >= 0 ? "{$daysLeft} дн." : "Просрочка " . abs($daysLeft) . " дн.";
                    return sprintf('%s <br><span class="fw-bold %s">%s</span>', $dateStr, $class, $text);
                }
                return $dateStr;
            });

        yield DateTimeField::new('closedAt', 'Дата закрытия')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->onlyOnDetail();

        yield TextField::new('returnAmount', 'Сумма к возврату')
            ->onlyOnDetail()
            ->formatValue(fn ($v) => $v ? number_format((float) $v, 2, '.', ' ') . ' ₽' : '—');

        yield TextField::new('graceReturnAmount', 'Сумма с льготным периодом')
            ->onlyOnDetail()
            ->formatValue(fn ($v) => $v ? number_format((float) $v, 2, '.', ' ') . ' ₽' : '—');

        // Поле статуса (ОБЪЕДИНЕНО, без дублей и renderAsHtml)
        yield ChoiceField::new('status', 'Статус')
            ->hideWhenCreating()
            ->setChoices([
                'Открыт'          => LoanTicket::STATUS_OPEN,
                'Льготный период' => LoanTicket::STATUS_GRACE,
                'Закрыт'          => LoanTicket::STATUS_CLOSED,
                'Просрочен'       => LoanTicket::STATUS_EXPIRED,
                'Перезалог'       => LoanTicket::STATUS_REPLEDGED,
                'Аннулирован'     => LoanTicket::STATUS_CANCELLED,
            ])
            ->formatValue(fn ($v) => match ($v) {
                LoanTicket::STATUS_OPEN      => '<span class="badge bg-success">● Открыт</span>',
                LoanTicket::STATUS_GRACE     => '<span class="badge bg-warning text-dark">● Льготный период</span>',
                LoanTicket::STATUS_CLOSED    => '<span class="text-muted small">● Закрыт</span>',
                LoanTicket::STATUS_EXPIRED   => '<span class="badge bg-danger">● Просрочен</span>',
                LoanTicket::STATUS_REPLEDGED => '<span class="badge bg-info text-dark">● Перезалог</span>',
                LoanTicket::STATUS_CANCELLED => '<span class="badge bg-secondary">✕ Аннулирован</span>',
                default                      => $v ?? '—',
            });

        yield AssociationField::new('repledgedFrom', 'Исходный билет')->onlyOnDetail();
        yield AssociationField::new('repledgedTo', 'Новый билет (перезалог)')->onlyOnDetail();

        yield MoneyField::new('paidInterest', 'Оплачено процентов')
            ->setCurrency('RUB')->setStoredAsCents(false)->onlyOnDetail();

        yield MoneyField::new('paidPrincipal', 'Оплачено по телу займа')
            ->setCurrency('RUB')->setStoredAsCents(false)->onlyOnDetail();

        yield NumberField::new('dailyInterestRate', 'Ежедневная ставка (%/день)')
            ->setNumDecimals(2)->onlyOnDetail();

        yield NumberField::new('interestRate', 'Процент в месяц (%)')
            ->setNumDecimals(2)->onlyOnDetail();

        if ($pageName === Crud::PAGE_DETAIL) {
            yield Field::new('pledgedItems', 'Предметы залога')
                ->setTemplatePath('admin/field/pledged_items_detail.html.twig')
                ->onlyOnDetail();
        } else {
            yield CollectionField::new('pledgedItems', 'Предметы залога')
                ->useEntryCrudForm(PledgedItemCrudController::class)
                ->allowAdd()->allowDelete()->setEntryIsComplex(true)->onlyOnForms();
        }

        yield TextField::new('notes', 'Примечания');

        yield DateTimeField::new('createdAt', 'Создан')
            ->setFormat('dd.MM.yyyy HH:mm')->onlyOnDetail();
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
            ->displayIf(function (LoanTicket $t) {
                $graceEnd = $t->getGraceEndDate();
                return !$t->isClosed() && !$t->isRepledged()
                    && $graceEnd && (new \DateTime()) > $graceEnd;
            });

        $print = Action::new('print', 'Распечатать билет', 'fa fa-print')
            ->linkToUrl(fn (LoanTicket $t) => '/admin/print/ticket/' . $t->getId())
            ->setHtmlAttributes(['target' => '_blank'])
            ->addCssClass('btn btn-secondary');

        $annul = Action::new('annul', 'Аннулировать', 'fa fa-ban')
            ->linkToCrudAction('annulAction')
            ->addCssClass('btn btn-outline-danger')
            ->displayIf(function (LoanTicket $t) {
                if ($t->getStatus() === LoanTicket::STATUS_CANCELLED) {
                    return false;
                }
                // Аннулирование доступно только в течение 15 минут после создания
                $createdAt = $t->getCreatedAt();
                if (!$createdAt) {
                    return false;
                }
                $minutesSince = ((new \DateTime())->getTimestamp() - $createdAt->getTimestamp()) / 60;
                if ($minutesSince > 15) {
                    return false;
                }
                // И только если не было оплат
                $hasPayments = (float) $t->getPaidInterest() > 0 || (float) $t->getPaidPrincipal() > 0;
                return !$hasPayments;
            });

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
        if ($entity instanceof LoanTicket) {
            if (!$entity->getTicketNumber()) {
                $entity->setTicketNumber($this->generateTicketNumber());
            }
            $entity->setStatus(LoanTicket::STATUS_OPEN);
            $this->applyTariff($entity);
            
            foreach ($entity->getPledgedItems() as $item) {
                $category = $item->getGoodType()?->getCategory();
                if ($category !== null) {
                    $item->setCategory($category);
                }
            }
            $this->handleEmbeddedImageUploads($entity, $em);
        }
        parent::persistEntity($em, $entity);    
        
        $this->logger->info(SystemLog::CHANNEL_TICKET,
            "Создан залоговый билет: {$entity->getTicketNumber()}",
            ['loanAmount' => $entity->getLoanAmount(), 'client' => $entity->getClient()->getFullName()],
            $entity->getId()
        );
    }

    public function updateEntity(EntityManagerInterface $em, $entity): void
    {
        if ($entity instanceof LoanTicket) {
            $this->applyTariff($entity);
            
            foreach ($entity->getPledgedItems() as $item) {
                $category = $item->getGoodType()?->getCategory();
                if ($category !== null) {
                    $item->setCategory($category);
                }
            }
            $this->handleEmbeddedImageUploads($entity, $em);
        }
        parent::updateEntity($em, $entity);
    }

    private function handleEmbeddedImageUploads(LoanTicket $ticket, EntityManagerInterface $em): void
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        if (!$request) return;

        $files = $request->files->get('LoanTicket');
        if (!is_array($files) || empty($files['pledgedItems'])) return;

        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/sl_images';
        if (!$fs->exists($uploadDir)) {
            $fs->mkdir($uploadDir, 0755);
        }

        $items = array_values($ticket->getPledgedItems()->toArray());

        foreach ($files['pledgedItems'] as $index => $itemData) {
            $itemFiles = $itemData['imageFiles'] ?? null;
            if (!$itemFiles) continue;

            $item = $items[$index] ?? null;
            if (!$item) continue;

            foreach ($itemFiles as $idx => $file) {
                if (!($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile)) continue;

                $ext = $file->guessExtension() ?: 'jpg';
                $base = sprintf('pledge_%s_%s.%s', time(), bin2hex(random_bytes(4)), $ext);
                $file->move($uploadDir, $base);
                $relPath = '/uploads/sl_images/' . $base;

                $image = new \App\Entity\PledgedItemImage();
                $image->setPledgedItem($item);
                $image->setSrc($relPath);
                $image->setPreview($relPath);
                $image->setIsCover($item->getImages()->isEmpty() && $idx === 0);
                
                $em->persist($image);
                $item->addImage($image);
            }
        }
    }

    private function applyTariff(LoanTicket $ticket): void
    {
        $tariff = $ticket->getTariff();
        if ($tariff) {
            $ticket->setDailyInterestRate($tariff->getDailyRate());
            $ticket->setInterestRate($tariff->getMonthlyRate());
        }
    }

    private function generateTicketNumber(): string
    {
        return 'ЛБ-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    // --- Кастомные экшены ---

    public function repledgeAction(AdminContext $context, RepledgeService $service, EntityManagerInterface $em, FormFactoryInterface $formFactory): Response 
    {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        $ticket = $em->find(LoanTicket::class, $entityId);
        if (!$ticket) throw $this->createNotFoundException();

        $accrued = $ticket->getAccruedInterest();
        $allItems = $ticket->getPledgedItems()->toArray();
        $totalLoan = (float) $ticket->getLoanAmount();
        $totalEstimate = array_sum(array_map(fn($i) => (float) $i->getEstimatedValue(), $allItems));
        
        // Расчет пропорционального тела займа для каждого изделия
        $itemLoans = [];
        foreach ($allItems as $item) {
            $itemLoans[$item->getId()] = $totalEstimate > 0
                ? round($totalLoan * ((float) $item->getEstimatedValue() / $totalEstimate), 2)
                : 0.0;
        }

        $form = $formFactory->create(\App\Form\RepledgeType::class, [
            'paymentAmount' => $accrued,
            'extensionDays' => 30,
        ], [
            'loan_ticket' => $ticket,
            'accrued_interest' => $accrued,
        ]);

        $form->handleRequest($context->getRequest());
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            // Собираем ID выкупаемых изделий из чекбоксов
            $redeemedItemIds = [];
            foreach ($allItems as $item) {
                $fieldName = 'redeem_' . $item->getId();
                if ($form->has($fieldName) && $form->get($fieldName)->getData()) {
                    $redeemedItemIds[] = $item->getId();
                }
            }
            
            try {
                if (!empty($redeemedItemIds)) {
                    // Частичный перезалог с выкупом
                    $result = $service->createRepledgePartial(
                        $ticket,
                        $redeemedItemIds,
                        (string)$data['paymentAmount'],
                        (int)$data['extensionDays'],
                        $data['notes'] ?? null
                    );
                } else {
                    // Обычный перезалог без выкупа
                    $result = $service->createRepledge(
                        $ticket,
                        (string)$data['paymentAmount'],
                        null,
                        (int)$data['extensionDays'],
                        $data['notes'] ?? null
                    );
                }
                $this->addFlash('success', 'Перезалог успешно оформлен.');
                return $this->redirect($this->container->get(AdminUrlGenerator::class)->setAction(Action::DETAIL)->setEntityId($result->getId())->generateUrl());
            } catch (\Exception $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/repledge_form.html.twig', [
            'ticket' => $ticket,
            'form' => $form->createView(),
            'accrued' => $accrued,
            'totalDebt' => $ticket->getTotalDebt(),
            'itemLoans' => $itemLoans,
        ]);
    }

    public function redeemAction(AdminContext $context, RepledgeService $service, EntityManagerInterface $em): Response 
    {
        $ticket = $em->find(LoanTicket::class, (int)$context->getRequest()->query->get('entityId'));
        if (!$ticket) throw $this->createNotFoundException();
        $service->redeem($ticket);
        $this->addFlash('success', 'Залог выкуплен.');
        return $this->redirect($this->container->get(AdminUrlGenerator::class)->setAction(Action::DETAIL)->setEntityId($ticket->getId())->generateUrl());
    }

    public function moveToSaleAction(AdminContext $context, RepledgeService $service, EntityManagerInterface $em): Response 
    {
        $ticket = $em->find(LoanTicket::class, (int)$context->getRequest()->query->get('entityId'));
        if (!$ticket) throw $this->createNotFoundException();

        // Проверка: текущая дата должна быть > срок возврата + льготный период
        $graceEnd = $ticket->getGraceEndDate();
        if ($graceEnd && (new \DateTime()) <= $graceEnd) {
            $this->addFlash('danger', 'Передача на реализацию возможна только после окончания льготного периода (' . $graceEnd->format('d.m.Y H:i') . ').');
            return $this->redirect($this->container->get(AdminUrlGenerator::class)->setAction(Action::DETAIL)->setEntityId($ticket->getId())->generateUrl());
        }

        $service->moveToSale($ticket);
        $this->addFlash('success', 'Предметы переданы на реализацию.');
        return $this->redirect($this->container->get(AdminUrlGenerator::class)->setAction(Action::DETAIL)->setEntityId($ticket->getId())->generateUrl());
    }

    public function annulAction(AdminContext $context, EntityManagerInterface $em): Response 
    {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        $ticket = $em->find(LoanTicket::class, $entityId);
        if (!$ticket) throw $this->createNotFoundException();

        // Проверка ограничений на аннулирование
        $createdAt = $ticket->getCreatedAt();
        if ($createdAt) {
            $minutesSince = ((new \DateTime())->getTimestamp() - $createdAt->getTimestamp()) / 60;
            if ($minutesSince > 15) {
                $this->addFlash('danger', 'Аннулирование возможно только в течение 15 минут после создания билета.');
                return $this->redirect($this->container->get(AdminUrlGenerator::class)->setAction(Action::DETAIL)->setEntityId($entityId)->generateUrl());
            }
        }

        $hasPayments = (float) $ticket->getPaidInterest() > 0 || (float) $ticket->getPaidPrincipal() > 0;
        if ($hasPayments) {
            $this->addFlash('danger', 'Аннулирование невозможно: по билету уже есть платежи.');
            return $this->redirect($this->container->get(AdminUrlGenerator::class)->setAction(Action::DETAIL)->setEntityId($entityId)->generateUrl());
        }

        if ($context->getRequest()->query->get('confirmed') === '1') {
            $ticket->setStatus(LoanTicket::STATUS_CANCELLED);
            $ticket->setClosedAt(new \DateTime());
            
            // "Освобождаем" предметы залога
            foreach ($ticket->getPledgedItems() as $item) {
                $item->setLoanTicket(null);
                $item->setStatus(PledgedItem::STATUS_WITHDRAWN);
            }
            
            $em->flush();
            $this->addFlash('warning', 'Билет аннулирован. Предметы залога освобождены.');
            return $this->redirect($this->container->get(AdminUrlGenerator::class)->setAction(Action::INDEX)->generateUrl());
        }

        $this->logger->warning(SystemLog::CHANNEL_TICKET,
            "Билет аннулирован: {$ticket->getTicketNumber()}",
            ['admin' => $this->getUser()->getUserIdentifier()],
            $ticket->getId()
        );

        return $this->render('admin/confirm_annul.html.twig', [
            'ticket' => $ticket,
            'confirmUrl' => $this->container->get(AdminUrlGenerator::class)->setAction('annulAction')->setEntityId($entityId)->set('confirmed', '1')->generateUrl(),
            'backUrl' => $this->container->get(AdminUrlGenerator::class)->setAction(Action::DETAIL)->setEntityId($entityId)->generateUrl(),
        ]);
    }
}