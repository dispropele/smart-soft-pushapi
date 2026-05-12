<?php

namespace App\Controller\Admin;

use App\Entity\PledgedItem;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
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

/**
 * Unified CRUD for PledgedItem (заложенные предметы + товары на реализации).
 */
class PledgedItemCrudController extends AbstractCrudController
{
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
        yield IdField::new('id', 'ID')->setMaxLength(10)->hideOnForm();

        yield Field::new('images', 'Фото')
            ->setTemplatePath('admin/field/pledged_item_cover.html.twig')
            ->setSortable(false)
            ->onlyOnIndex();

        yield TextField::new('name', 'Название');

        // --- Статус ---
        yield TextField::new('status', 'Статус')
            ->formatValue(fn($v) => match($v) {
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

        // --- Привязки ---
        yield AssociationField::new('loanTicket', 'Залоговый билет')
            ->autocomplete()
            ->setRequired(false);

        yield AssociationField::new('category', 'Категория')->onlyOnForms()->autocomplete();
        yield AssociationField::new('goodType', 'Вид изделия')->onlyOnForms()->autocomplete();

        // --- Металл ---
        yield AssociationField::new('metalStandard', 'Металл / проба')->onlyOnForms()->autocomplete();
        yield AssociationField::new('metalColor', 'Цвет металла')->onlyOnForms()->autocomplete();

        // --- Вставка ---
        yield AssociationField::new('insert', 'Вставка')->onlyOnForms()->autocomplete();
        yield NumberField::new('insertWeight', 'Вес вставки (кт/г)')
            ->setNumDecimals(2)->onlyOnForms();
        yield TextField::new('insertDescription', 'Описание вставки')->onlyOnForms();

        // --- Параметры ---
        yield TextField::new('size', 'Размер')->onlyOnForms();

        yield NumberField::new('itemWeight', 'Вес изделия (г)')
            ->setNumDecimals(2)->onlyOnForms()
            ->setFormTypeOptions(['attr' => ['step' => '0.01', 'min' => 0]]);

        yield NumberField::new('scrapWeight', 'Вес лома (г)')
            ->setNumDecimals(2)->onlyOnForms()
            ->setFormTypeOptions(['attr' => ['step' => '0.01', 'min' => 0]]);

        // --- Стоимости ---
        yield MoneyField::new('estimatedValue', 'Оценочная стоимость')
            ->setCurrency('RUB')->setStoredAsCents(false)->onlyOnForms();

        yield MoneyField::new('redemptionAmount', 'Сумма выкупа')
            ->setCurrency('RUB')->setStoredAsCents(false)->onlyOnForms();

        yield MoneyField::new('soldPrice', 'Цена продажи')
            ->setCurrency('RUB')->setStoredAsCents(false)->onlyOnForms();

        yield TextField::new('soldPrice', 'Цена')
            ->formatValue(fn($v, $e) => $v
                ? number_format((float)$v, 0, '.', ' ') . ' ₽'
                : '—')
            ->onlyOnIndex();

        yield TextField::new('condition', 'Состояние')->onlyOnForms();

        yield TextareaField::new('description', 'Описание')->setNumOfRows(3)->onlyOnForms();
        yield TextareaField::new('specification', 'Спецификация')->setNumOfRows(3)->onlyOnForms();

        // --- Даты ---
        yield DateTimeField::new('statusDate', 'Дата статуса')
            ->setFormat('dd.MM.yyyy HH:mm')->hideOnForm();

        yield DateTimeField::new('publishedAt', 'Дата публикации')
            ->setFormat('dd.MM.yyyy HH:mm')->onlyOnForms();

        yield DateTimeField::new('redemptionDate', 'Дата выкупа')
            ->setFormat('dd.MM.yyyy HH:mm')->onlyOnForms();

        // --- Detail ---
        yield AssociationField::new('loanTicket', 'Залоговый билет')->onlyOnDetail();
        yield AssociationField::new('category', 'Категория')->onlyOnDetail();
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
            ->setCurrency('RUB')->setStoredAsCents(false)->onlyOnDetail();
        yield MoneyField::new('redemptionAmount', 'Сумма выкупа')
            ->setCurrency('RUB')->setStoredAsCents(false)->onlyOnDetail();
        yield MoneyField::new('soldPrice', 'Цена продажи')
            ->setCurrency('RUB')->setStoredAsCents(false)->onlyOnDetail();
        yield AssociationField::new('currency', 'Валюта')->onlyOnDetail();
        yield TextField::new('condition', 'Состояние')->onlyOnDetail();
        yield TextareaField::new('description', 'Описание')->onlyOnDetail();
        yield TextareaField::new('specification', 'Спецификация')->onlyOnDetail();
        yield DateTimeField::new('publishedAt', 'Дата публикации')
            ->setFormat('dd.MM.yyyy HH:mm')->onlyOnDetail();
        yield DateTimeField::new('redemptionDate', 'Дата выкупа')
            ->setFormat('dd.MM.yyyy HH:mm')->onlyOnDetail();
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

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function persistEntity(EntityManagerInterface $em, $entity): void
    {
        if ($entity instanceof PledgedItem) {
            $entity->setStatusDate(new \DateTime());
            if ($entity->isForSale() && !$entity->getPublishedAt()) {
                $entity->setPublishedAt(new \DateTime());
            }
        }
        parent::persistEntity($em, $entity);
    }

    public function updateEntity(EntityManagerInterface $em, $entity): void
    {
        if ($entity instanceof PledgedItem) {
            $entity->setStatusDate(new \DateTime());
            if ($entity->isForSale() && !$entity->getPublishedAt()) {
                $entity->setPublishedAt(new \DateTime());
            }
        }
        parent::updateEntity($em, $entity);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'Название'))
            ->add(EntityFilter::new('loanTicket', 'Билет'))
            ->add(EntityFilter::new('category', 'Категория'))
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