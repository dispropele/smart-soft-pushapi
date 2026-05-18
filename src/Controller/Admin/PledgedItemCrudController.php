<?php

namespace App\Controller\Admin;

use App\Entity\LoanTicket;
use App\Entity\PledgedItem;
use App\Entity\PledgedItemImage;
use App\Entity\SystemLog;
use App\Service\SystemLogger;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;

class PledgedItemCrudController extends AbstractProtectedCrudController
{
    private RequestStack $requestStack;
    private KernelInterface $kernel;
    private SystemLogger $logger;

    public function __construct(RequestStack $requestStack, KernelInterface $kernel, SystemLogger $logger)
    {
        $this->requestStack = $requestStack;
        $this->kernel       = $kernel;
        $this->logger       = $logger;
    }

    public static function getEntityFqcn(): string { return PledgedItem::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Предмет залога')
            ->setEntityLabelInPlural('Предметы (все)')
            ->setPageTitle(Crud::PAGE_INDEX,  'Предметы залога / Витрина')
            ->setPageTitle(Crud::PAGE_NEW,    '➕ Добавить предмет залога')
            ->setPageTitle(Crud::PAGE_EDIT,   '✏️ Редактировать предмет')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Предмет залога')
            ->setDefaultSort(['statusDate' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        if ($this->isEmbeddedInLoanTicketForm()) {
            yield from $this->configureEmbeddedInTicketFields();
            return;
        }

        $isCreate = $pageName === Crud::PAGE_NEW;
        $isEdit   = $pageName === Crud::PAGE_EDIT;
        $instance = $isEdit ? $this->getContext()?->getEntity()?->getInstance() : null;
        $isUnderActiveLoan = $instance instanceof PledgedItem && $instance->getLoanTicket()?->isActive();

        // ── INDEX ───────────────────────────────────────────────────────────────
        yield IdField::new('id', 'ID')->setMaxLength(10)->hideOnForm();

        yield Field::new('images', 'Фото')
            ->setTemplatePath('admin/field/pledged_item_cover.html.twig')
            ->setSortable(false)
            ->onlyOnIndex();

        yield TextField::new('name', 'Название');

        yield TextField::new('status', 'Статус')
            ->formatValue(fn ($v) => match ($v) {
                PledgedItem::STATUS_PLEDGED   => '<span style="color:#1d4e75;font-weight:600">● На хранении</span>',
                PledgedItem::STATUS_REDEEMED  => '<span style="color:#5cb85c;font-weight:600">● Выкуплен</span>',
                PledgedItem::STATUS_FOR_SALE  => '<span style="color:#d4a017;font-weight:600">● На реализации</span>',
                PledgedItem::STATUS_SOLD      => '<span style="color:#d9534f;font-weight:600">● Продан</span>',
                PledgedItem::STATUS_WITHDRAWN => '<span style="color:#f0ad4e;font-weight:600">● Изъят</span>',
                PledgedItem::STATUS_HIDDEN    => '<span style="color:#aaa">● Скрыт</span>',
                default                       => $v ?? '—',
            })
            ->renderAsHtml()
            ->hideOnForm();

        yield TextField::new('itemWeight', 'Вес')
            ->formatValue(fn ($v) => $v ? $v . ' г' : '—')
            ->onlyOnIndex();

        yield TextField::new('metalStandard', 'Проба')
            ->formatValue(fn ($v) => $v ? (string) $v : '—')
            ->onlyOnIndex();

        yield TextField::new('loanTicket', 'Билет')
            ->formatValue(fn ($v) => $v ? $v->getTicketNumber() : '—')
            ->onlyOnIndex();

        yield TextField::new('displayPrice', 'Цена')
            ->formatValue(function ($v, PledgedItem $item) {
                if ($item->getSoldPrice()) {
                    return number_format((float) $item->getSoldPrice(), 0, '.', ' ') . ' ₽';
                }
                if ($item->getEstimatedValue()) {
                    return '<span style="color:#999">' . number_format((float) $item->getEstimatedValue(), 0, '.', ' ') . ' ₽</span>';
                }
                return '—';
            })
            ->renderAsHtml()
            ->onlyOnIndex();

        yield DateTimeField::new('statusDate', 'Дата статуса')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->hideOnForm();

        // ── FORMS ──────────────────────────────────────────────────────────────
        if (!$isCreate) {
            yield ChoiceField::new('status', 'Статус')
                ->onlyOnForms()
                ->setChoices([
                    'На хранении'   => PledgedItem::STATUS_PLEDGED,
                    'Выкуплен'      => PledgedItem::STATUS_REDEEMED,
                    'На реализации' => PledgedItem::STATUS_FOR_SALE,
                    'Продан'        => PledgedItem::STATUS_SOLD,
                    'Изъят'         => PledgedItem::STATUS_WITHDRAWN,
                    'Скрыт'         => PledgedItem::STATUS_HIDDEN,
                ]);
        }

        yield AssociationField::new('loanTicket', 'Залоговый билет')
            ->autocomplete()->setRequired(false)->onlyOnForms()->onlyWhenUpdating();

        yield AssociationField::new('goodType', 'Вид изделия')
            ->autocomplete()->onlyOnForms()
            ->setFormTypeOptions(['disabled' => $isUnderActiveLoan]);

        yield AssociationField::new('metalStandard', 'Металл / проба')
            ->autocomplete()->onlyOnForms()
            ->setFormTypeOptions(['disabled' => $isUnderActiveLoan]);

        yield NumberField::new('itemWeight', 'Вес изделия (г)')
            ->setNumDecimals(2)
            ->setFormTypeOptions([
                'attr'     => ['step' => '0.01', 'min' => 0],
                'disabled' => $isUnderActiveLoan,
            ])
            ->onlyOnForms();

        yield MoneyField::new('estimatedValue', 'Оценочная стоимость')
            ->setCurrency('RUB')->setStoredAsCents(false)->onlyOnForms()
            ->setFormTypeOptions(['disabled' => $isUnderActiveLoan]);

        yield TextareaField::new('description', 'Описание')
            ->setNumOfRows(3)->setRequired(false)->onlyOnForms();

        if (!$isCreate) {
            yield AssociationField::new('metalColor', 'Цвет металла')->autocomplete()->onlyOnForms();
            yield AssociationField::new('insert', 'Вставка')->autocomplete()->onlyOnForms();
            yield NumberField::new('insertWeight', 'Вес вставки (г)')->setNumDecimals(2)->onlyOnForms();
            yield TextField::new('insertDescription', 'Описание вставки')->onlyOnForms();
            yield TextField::new('size', 'Размер (см)')->onlyOnForms();
            yield NumberField::new('scrapWeight', 'Вес лома (г)')
                ->setNumDecimals(2)
                ->setFormTypeOptions(['attr' => ['step' => '0.01', 'min' => 0]])
                ->onlyOnForms();
            yield MoneyField::new('redemptionAmount', 'Сумма выкупа')
                ->setCurrency('RUB')->setStoredAsCents(false)->onlyOnForms()
                ->setFormTypeOptions(['disabled' => true]);
            yield MoneyField::new('soldPrice', 'Цена продажи')
                ->setCurrency('RUB')->setStoredAsCents(false)->onlyOnForms();
            yield TextField::new('condition', 'Состояние')->onlyOnForms();
            yield DateTimeField::new('publishedAt', 'Дата публикации')
                ->setFormat('dd.MM.yyyy HH:mm')->onlyOnForms();
            yield DateTimeField::new('redemptionDate', 'Дата выкупа')
                ->setFormat('dd.MM.yyyy HH:mm')->onlyOnForms()
                ->setFormTypeOptions(['disabled' => true]);

            // Фото всегда доступно для редактирования
            if ($isEdit) {
                yield Field::new('imageFiles', 'Загрузить фото')
                    ->setFormType(FileType::class)
                    ->setFormTypeOptions([
                        'multiple' => true,
                        'required' => false,
                        'mapped'   => false,
                        'attr'     => ['accept' => 'image/*'],
                    ])
                    ->setHelp('Загрузите фото. Для перевода на реализацию требуется минимум одно фото.');
            }
        }

        // ── DETAIL ─────────────────────────────────────────────────────────────
        yield Field::new('detailView', 'Информация о предмете')
            ->setTemplatePath('admin/field/pledged_item_detail.html.twig')
            ->onlyOnDetail();
    }

    private function isEmbeddedInLoanTicketForm(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request && LoanTicketCrudController::class === $request->attributes->get('crudControllerFqcn');
    }

    /** @return iterable */
    private function configureEmbeddedInTicketFields(): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('name', 'Название')->setRequired(true);

        yield AssociationField::new('goodType', 'Вид изделия')
            ->autocomplete()->setRequired(true);

        yield TextField::new('size', 'Размер (см)')->setRequired(false);

        yield Field::new('metal', 'Металл')
            ->setFormType(\Symfony\Bridge\Doctrine\Form\Type\EntityType::class)
            ->setFormTypeOptions([
                'class'    => \App\Entity\Metal::class,
                'mapped'   => false,
                'placeholder' => 'Выберите металл',
                'attr'     => ['class' => 'metal-select form-select', 'data-ea-widget' => 'unset'],
            ]);

        yield AssociationField::new('metalStandard', 'Проба')
            ->setFormTypeOptions([
                'choice_attr' => fn($choice) => ['data-metal-id' => $choice->getMetal()?->getId()],
                'placeholder' => 'Выберите пробу',
                'attr'        => ['class' => 'metal-standard-select form-select', 'data-ea-widget' => 'unset'],
            ])
            ->setRequired(true);

        yield AssociationField::new('metalColor', 'Цвет металла')->autocomplete();

        yield NumberField::new('itemWeight', 'Вес изделия (г)')
            ->setNumDecimals(2)
            ->setFormTypeOptions(['attr' => ['step' => '0.01', 'min' => 0]])
            ->setRequired(true);

        yield AssociationField::new('insert', 'Вставка')->autocomplete();

        yield NumberField::new('insertWeight', 'Вес вставки (г)')
            ->setNumDecimals(2)
            ->setFormTypeOptions(['attr' => ['step' => '0.01', 'min' => 0]]);

        yield NumberField::new('scrapWeight', 'Вес лома (г)')
            ->setNumDecimals(2)
            ->setFormTypeOptions(['attr' => ['step' => '0.01', 'min' => 0]]);

        yield MoneyField::new('estimatedValue', 'Оценочная стоимость')
            ->setCurrency('RUB')->setStoredAsCents(false)->setRequired(true);

        yield Field::new('imageFiles', 'Фото')
            ->setFormType(FileType::class)
            ->setFormTypeOptions([
                'multiple' => true,
                'required' => false,
                'mapped'   => false,
                'attr'     => ['accept' => 'image/*'],
            ]);

        yield TextareaField::new('description', 'Описание')
            ->setNumOfRows(2)->setRequired(false);
    }

    public function configureActions(Actions $actions): Actions
    {
        $sell = Action::new('sell', 'Продать', 'fa fa-money')
            ->linkToCrudAction('sellAction')
            ->addCssClass('btn btn-danger')
            ->displayIf(fn(PledgedItem $p) => $p->isForSale());

        $archive = Action::new('archive', 'Архивировать', 'fa fa-archive')
            ->linkToCrudAction('archiveAction')
            ->addCssClass('btn btn-secondary')
            ->displayIf(fn(PledgedItem $p) => !$p->isSold() && $p->getStatus() !== PledgedItem::STATUS_HIDDEN);

        return $actions
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $sell)
            ->add(Crud::PAGE_DETAIL, $archive);
    }

    public function archiveAction(AdminContext $context, EntityManagerInterface $em): Response
    {
        $item = $em->find(PledgedItem::class, (int) $context->getRequest()->query->get('entityId'));
        if (!$item) throw $this->createNotFoundException();

        $item->setStatus(PledgedItem::STATUS_HIDDEN);
        $item->setStatusDate(new \DateTime());
        $em->flush();

        $this->addFlash('success', sprintf('Предмет «%s» архивирован.', $item->getName()));
        $this->logger->info(SystemLog::CHANNEL_SALE, "Предмет архивирован: {$item->getName()}", [], $item->getId());

        return $this->redirect(
            $this->container->get(AdminUrlGenerator::class)
                ->setController(self::class)->setAction(Action::DETAIL)->setEntityId($item->getId())->generateUrl()
        );
    }

    public function sellAction(AdminContext $context, EntityManagerInterface $em, FormFactoryInterface $formFactory): Response
    {
        $item = $em->find(PledgedItem::class, (int) $context->getRequest()->query->get('entityId'));
        if (!$item) throw $this->createNotFoundException();

        $form = $formFactory->create(\App\Form\SellItemType::class, ['soldPrice' => $item->getSoldPrice()]);
        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $item->setSoldPrice((string) $data['soldPrice']);
            $item->setStatus(PledgedItem::STATUS_SOLD);
            $item->setStatusDate(new \DateTime());
            if ($data['notes']) {
                $item->setDescription(($item->getDescription() ?? '') . "\n[Продажа] " . $data['notes']);
            }
            $em->flush();

            $this->addFlash('success', sprintf('Предмет «%s» продан за %s ₽.', $item->getName(), number_format((float) $data['soldPrice'], 2, '.', ' ')));
            $this->logger->info(SystemLog::CHANNEL_SALE, "Предмет продан: {$item->getName()}", ['soldPrice' => $data['soldPrice']], $item->getId());

            return $this->redirect(
                $this->container->get(AdminUrlGenerator::class)->setController(self::class)->setAction(Action::DETAIL)->setEntityId($item->getId())->generateUrl()
            );
        }

        return $this->render('admin/sell_item_form.html.twig', ['item' => $item, 'form' => $form->createView()]);
    }

    public function persistEntity(EntityManagerInterface $em, $entity): void
    {
        if ($entity instanceof PledgedItem) {
            $this->syncCategoryFromGoodType($entity);
            $this->validatePledgedItem($entity, true, false);
            $entity->setStatusDate(new \DateTime());
            if ($entity->isForSale() && !$entity->getPublishedAt()) {
                $entity->setPublishedAt(new \DateTime());
            }
            $this->handleImageUpload($entity, $em);
        }
        parent::persistEntity($em, $entity);
    }

    public function updateEntity(EntityManagerInterface $em, $entity): void
    {
        if ($entity instanceof PledgedItem) {
            $originalEntity = $em->getUnitOfWork()->getOriginalEntityData($entity);
            $wasForSale = isset($originalEntity['status']) && $originalEntity['status'] === PledgedItem::STATUS_FOR_SALE;
            $isNowForSale = $entity->isForSale();

            $this->syncCategoryFromGoodType($entity);

            // Проверяем, загружаются ли файлы сейчас (до валидации)
            $request = $this->requestStack->getCurrentRequest();
            $files = $request?->files->get('PledgedItem');
            $hasUploadingFiles = !empty($files['imageFiles']);

            // Валидация только при переходе в статус FOR_SALE
            if ($isNowForSale && !$wasForSale) {
                $this->validatePledgedItem($entity, false, $hasUploadingFiles);
            }

            $entity->setStatusDate(new \DateTime());
            if ($entity->isForSale() && !$entity->getPublishedAt()) {
                $entity->setPublishedAt(new \DateTime());
            }

            // Сначала загружаем фото, потом проверяем итоговый статус
            $this->handleImageUpload($entity, $em);
        }
        parent::updateEntity($em, $entity);
    }

    /**
     * Валидация предмета перед сохранением.
     *
     * @param bool $isNew             true — создание, false — редактирование
     * @param bool $hasUploadingFiles true — в запросе есть файлы для загрузки
     */
    private function validatePledgedItem(PledgedItem $item, bool $isNew, bool $hasUploadingFiles): void
    {
        $errors = [];

        $soldPrice = (float) ($item->getSoldPrice() ?? 0);
        $estimated = (float) ($item->getEstimatedValue() ?? 0);
        if ($soldPrice > 0 && $estimated > 0 && $soldPrice < $estimated) {
            $errors[] = sprintf(
                'Цена продажи (%.2f ₽) не может быть ниже оценочной стоимости (%.2f ₽).',
                $soldPrice, $estimated
            );
        }

        $itemWeight  = (float) ($item->getItemWeight() ?? 0);
        $scrapWeight = (float) ($item->getScrapWeight() ?? 0);
        if ($scrapWeight > 0 && $itemWeight > 0 && $scrapWeight > $itemWeight) {
            $errors[] = sprintf(
                'Вес лома (%.2f г) не может превышать общий вес изделия (%.2f г).',
                $scrapWeight, $itemWeight
            );
        }

        if ($item->isForSale()) {
            if (empty($item->getSoldPrice()) || (float) $item->getSoldPrice() <= 0) {
                $errors[] = 'Для перевода на реализацию необходимо установить цену продажи.';
            }
            // Фото: считаем допустимым, если файлы загружаются прямо сейчас
            if ($item->getImages()->isEmpty() && !$hasUploadingFiles) {
                $errors[] = 'Для перевода на реализацию необходимо загрузить хотя бы одно фото.';
            }
            if (empty($item->getCondition())) {
                $errors[] = 'Для перевода на реализацию необходимо указать состояние изделия.';
            }
        }

        if (!empty($errors)) {
            $this->logger->warning(
                SystemLog::CHANNEL_SALE,
                'Ошибка валидации предмета: ' . implode('; ', $errors),
                ['itemId' => $item->getId()]
            );
            throw new \RuntimeException(implode(' ', $errors));
        }
    }

    private function syncCategoryFromGoodType(PledgedItem $item): void
    {
        $category = $item->getGoodType()?->getCategory();
        if ($category !== null) {
            $item->setCategory($category);
        }
    }

    private function handleImageUpload(PledgedItem $entity, EntityManagerInterface $em): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) return;

        $files = $request->files->get('PledgedItem');
        if (!is_array($files) || empty($files['imageFiles'])) return;

        $fs        = new Filesystem();
        $uploadDir = $this->kernel->getProjectDir() . '/public/uploads/sl_images';
        if (!$fs->exists($uploadDir)) {
            $fs->mkdir($uploadDir, 0755);
        }

        foreach ($files['imageFiles'] as $idx => $file) {
            if (!($file instanceof UploadedFile)) continue;

            $ext  = $file->guessExtension() ?: 'jpg';
            $base = sprintf('pledge_%s_%s.%s', time(), bin2hex(random_bytes(4)), $ext);
            $file->move($uploadDir, $base);
            $relPath = '/uploads/sl_images/' . $base;

            $image = new PledgedItemImage();
            $image->setPledgedItem($entity);
            $image->setSrc($relPath);
            $image->setPreview($relPath);
            $image->setIsCover($idx === 0 && $entity->getImages()->isEmpty());
            $em->persist($image);
        }
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'Название'))
            ->add(EntityFilter::new('loanTicket', 'Билет'))
            ->add(EntityFilter::new('goodType', 'Вид изделия'))
            ->add(ChoiceFilter::new('status', 'Статус')->setChoices([
                'На хранении'   => PledgedItem::STATUS_PLEDGED,
                'Выкуплен'      => PledgedItem::STATUS_REDEEMED,
                'На реализации' => PledgedItem::STATUS_FOR_SALE,
                'Продан'        => PledgedItem::STATUS_SOLD,
                'Изъят'         => PledgedItem::STATUS_WITHDRAWN,
                'Скрыт'         => PledgedItem::STATUS_HIDDEN,
            ]));
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof PledgedItem) return null;

        if ($entity->getLoanTicket() !== null) {
            return sprintf(
                'Невозможно удалить предмет «%s»: он связан с залоговым билетом %s. Используйте архивацию.',
                $entity->getName(),
                $entity->getLoanTicket()->getTicketNumber()
            );
        }

        return null;
    }
}
