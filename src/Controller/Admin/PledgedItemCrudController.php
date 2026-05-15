<?php

namespace App\Controller\Admin;

use App\Entity\PledgedItem;
use App\Entity\PledgedItemImage;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
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

class PledgedItemCrudController extends AbstractCrudController
{
    private RequestStack $requestStack;
    private KernelInterface $kernel;

    public function __construct(RequestStack $requestStack, KernelInterface $kernel)
    {
        $this->requestStack = $requestStack;
        $this->kernel = $kernel;
    }

    public static function getEntityFqcn(): string { return PledgedItem::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Предмет залога')
            ->setEntityLabelInPlural('Предметы (все)')
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

        yield IdField::new('id', 'ID')->setMaxLength(10)->hideOnForm();

        yield Field::new('images', 'Фото')
            ->setTemplatePath('admin/field/pledged_item_cover.html.twig')
            ->setSortable(false)
            ->onlyOnIndex();

        yield Field::new('images', 'Фото')
            ->setTemplatePath('admin/field/pledged_item_cover.html.twig')
            ->onlyOnDetail();

        yield TextField::new('name', 'Название');

        if ($this->shouldShowPhotoUpload($pageName)) {
            yield Field::new('imageFiles', 'Загрузить фото')
                ->setFormType(FileType::class)
                ->setFormTypeOptions([
                    'multiple' => true,
                    'required' => false,
                    'mapped' => false,
                    'attr' => ['accept' => 'image/*'],
                ])
                ->setHelp('Доступно только для предметов на реализации');
        }

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
            ->autocomplete()
            ->setRequired(false)
            ->onlyOnForms()
            ->onlyWhenUpdating();

        yield AssociationField::new('goodType', 'Вид изделия')->autocomplete()->onlyOnForms();
        yield AssociationField::new('metalStandard', 'Металл / проба')->autocomplete()->onlyOnForms();

        yield NumberField::new('itemWeight', 'Вес изделия (г)')
            ->setNumDecimals(2)
            ->setFormTypeOptions(['attr' => ['step' => '0.01', 'min' => 0]])
            ->onlyOnForms();

        yield MoneyField::new('estimatedValue', 'Оценочная стоимость')
            ->setCurrency('RUB')
            ->setStoredAsCents(false)
            ->onlyOnForms();

        yield TextareaField::new('description', 'Описание')
            ->setNumOfRows(3)
            ->setRequired(false)
            ->onlyOnForms();

        if (!$isCreate) {
            yield AssociationField::new('metalColor', 'Цвет металла')->autocomplete()->onlyOnForms();
            yield AssociationField::new('insert', 'Вставка')->autocomplete()->onlyOnForms();
            yield NumberField::new('insertWeight', 'Вес вставки (кт/г)')->setNumDecimals(2)->onlyOnForms();
            yield TextField::new('insertDescription', 'Описание вставки')->onlyOnForms();
            yield TextField::new('size', 'Размер')->onlyOnForms();
            yield NumberField::new('scrapWeight', 'Вес лома (г)')
                ->setNumDecimals(2)
                ->setFormTypeOptions(['attr' => ['step' => '0.01', 'min' => 0]])
                ->onlyOnForms();
            yield MoneyField::new('redemptionAmount', 'Сумма выкупа')
                ->setCurrency('RUB')
                ->setStoredAsCents(false)
                ->onlyOnForms();
            yield MoneyField::new('soldPrice', 'Цена продажи')
                ->setCurrency('RUB')
                ->setStoredAsCents(false)
                ->onlyOnForms();
            yield TextField::new('condition', 'Состояние')->onlyOnForms();
            yield DateTimeField::new('publishedAt', 'Дата публикации')
                ->setFormat('dd.MM.yyyy HH:mm')
                ->onlyOnForms();
            yield DateTimeField::new('redemptionDate', 'Дата выкупа')
                ->setFormat('dd.MM.yyyy HH:mm')
                ->onlyOnForms();
        }

        yield TextField::new('soldPrice', 'Цена')
            ->formatValue(fn ($v) => $v
                ? number_format((float) $v, 0, '.', ' ') . ' ₽'
                : '—')
            ->onlyOnIndex();

        yield DateTimeField::new('statusDate', 'Дата статуса')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->hideOnForm();

        yield AssociationField::new('loanTicket', 'Залоговый билет')->onlyOnDetail();
        yield AssociationField::new('goodType', 'Вид изделия')->onlyOnDetail();
        yield AssociationField::new('metalStandard', 'Металл / проба')->onlyOnDetail();
        yield AssociationField::new('metalColor', 'Цвет металла')->onlyOnDetail();
        yield AssociationField::new('insert', 'Вставка')->onlyOnDetail();
        yield TextField::new('insertWeight', 'Вес вставок (ct)')->onlyOnDetail();
        yield TextField::new('insertDescription', 'Описание вставки')->onlyOnDetail();
        yield TextField::new('size', 'Размер / длина')->onlyOnDetail();
        yield TextField::new('itemWeight', 'Вес изделия (г)')->onlyOnDetail();
        yield TextField::new('scrapWeight', 'Вес лома (г)')->onlyOnDetail();
        yield MoneyField::new('estimatedValue', 'Оценочная стоимость')
            ->setCurrency('RUB')
            ->setStoredAsCents(false)
            ->onlyOnDetail();
        yield MoneyField::new('redemptionAmount', 'Сумма выкупа')
            ->setCurrency('RUB')
            ->setStoredAsCents(false)
            ->onlyOnDetail();
        yield MoneyField::new('soldPrice', 'Цена продажи')
            ->setCurrency('RUB')
            ->setStoredAsCents(false)
            ->onlyOnDetail();
        yield TextField::new('condition', 'Состояние')->onlyOnDetail();
        yield TextareaField::new('description', 'Описание')->onlyOnDetail();
        yield DateTimeField::new('publishedAt', 'Дата публикации')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->onlyOnDetail();
        yield DateTimeField::new('redemptionDate', 'Дата выкупа')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->onlyOnDetail();
        yield TextField::new('status', 'Статус')
            ->formatValue(fn ($v) => match ($v) {
                PledgedItem::STATUS_PLEDGED   => 'На хранении',
                PledgedItem::STATUS_REDEEMED  => 'Выкуплен',
                PledgedItem::STATUS_FOR_SALE  => 'На реализации',
                PledgedItem::STATUS_SOLD      => 'Продан',
                PledgedItem::STATUS_WITHDRAWN => 'Изъят',
                PledgedItem::STATUS_HIDDEN    => 'Скрыт',
                default                       => $v ?? '—',
            })
            ->onlyOnDetail();
    }

    private function isEmbeddedInLoanTicketForm(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return false;
        }

        return LoanTicketCrudController::class === $request->attributes->get('crudControllerFqcn');
    }

    /**
     * @return iterable<\EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface>
     */
    private function configureEmbeddedInTicketFields(): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('name', 'Название');

        yield AssociationField::new('goodType', 'Вид изделия')->autocomplete();

        yield AssociationField::new('metalStandard', 'Металл / проба')->autocomplete();

        yield NumberField::new('itemWeight', 'Вес изделия (г)')
            ->setNumDecimals(2)
            ->setFormTypeOptions(['attr' => ['step' => '0.01', 'min' => 0]]);

        yield MoneyField::new('estimatedValue', 'Оценочная стоимость')
            ->setCurrency('RUB')
            ->setStoredAsCents(false);

        yield TextareaField::new('description', 'Описание')
            ->setNumOfRows(2)
            ->setRequired(false);
    }

    private function shouldShowPhotoUpload(string $pageName): bool
    {
        if ($pageName !== Crud::PAGE_EDIT) {
            return false;
        }

        $instance = $this->getContext()?->getEntity()?->getInstance();

        return $instance instanceof PledgedItem && $instance->isForSale();
    }

    public function configureActions(Actions $actions): Actions
    {
        $sell = Action::new('sell', 'Продать', 'fa fa-money')
            ->linkToCrudAction('sellAction')
            ->addCssClass('btn btn-danger')
            ->displayIf(fn(PledgedItem $p) => $p->isForSale());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $sell);
    }

    public function sellAction(
        AdminContext $context,
        EntityManagerInterface $em,
        FormFactoryInterface $formFactory
    ): Response {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        $item = $em->find(PledgedItem::class, $entityId);
        if (!$item) { throw $this->createNotFoundException(); }

        $form = $formFactory->create(\App\Form\SellItemType::class, [
            'soldPrice' => $item->getSoldPrice(),
        ]);
        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $item->setSoldPrice((string)$data['soldPrice']);
            $item->setStatus(PledgedItem::STATUS_SOLD);
            $item->setStatusDate(new \DateTime());
            if ($data['notes']) {
                $item->setDescription(($item->getDescription() ?? '') . "\n[Продажа] " . $data['notes']);
            }
            $em->flush();

            $this->addFlash('success', sprintf(
                'Предмет «%s» продан за %s ₽.',
                $item->getName(),
                number_format((float)$data['soldPrice'], 2, '.', ' ')
            ));

            return $this->redirect(
                $this->container->get(AdminUrlGenerator::class)
                    ->setController(self::class)->setAction(Action::DETAIL)
                    ->setEntityId($item->getId())->generateUrl()
            );
        }

        return $this->render('admin/sell_item_form.html.twig', [
            'item' => $item,
            'form' => $form->createView(),
        ]);
    }

    public function persistEntity(EntityManagerInterface $em, $entity): void
    {
        if ($entity instanceof PledgedItem) {
            $this->syncCategoryFromGoodType($entity);
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
            $this->syncCategoryFromGoodType($entity);
            $entity->setStatusDate(new \DateTime());
            if ($entity->isForSale() && !$entity->getPublishedAt()) {
                $entity->setPublishedAt(new \DateTime());
            }
            $this->handleImageUpload($entity, $em);
        }
        parent::updateEntity($em, $entity);
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
        if (!$request) {
            return;
        }

        $files = $request->files->get('PledgedItem');
        if (!is_array($files) || empty($files['imageFiles'])) {
            return;
        }

        $uploaded = $files['imageFiles'];
        if (!$uploaded) {
            return;
        }

        $fs = new Filesystem();
        $uploadDir = $this->kernel->getProjectDir() . '/public/uploads/sl_images';
        if (!$fs->exists($uploadDir)) {
            $fs->mkdir($uploadDir, 0755);
        }

        foreach ($uploaded as $idx => $file) {
            if (!($file instanceof UploadedFile)) {
                continue;
            }

            $ext = $file->guessExtension() ?: 'jpg';
            try {
                $base = sprintf('pledge_%s_%s.%s', time(), bin2hex(random_bytes(4)), $ext);
            } catch (\Exception $e) {
                $base = sprintf('pledge_%s_%s.%s', time(), uniqid(), $ext);
            }

            $file->move($uploadDir, $base);
            $relPath = '/uploads/sl_images/' . $base;

            $image = new PledgedItemImage();
            $image->setPledgedItem($entity);
            $image->setSrc($relPath);
            $image->setPreview($relPath);
            $image->setIsCover($idx === 0);
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
}