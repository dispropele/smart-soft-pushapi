<?php

namespace App\Controller\Admin;

use App\Entity\Good;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class GoodCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Good::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Товар')
            ->setEntityLabelInPlural('Товары (активные)')
            ->setDefaultSort(['statusDate' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->setMaxLength(10)->hideOnForm();

        yield Field::new('images', 'Фото')
            ->setTemplatePath('admin/field/good_cover.html.twig')
            ->setSortable(false)
            ->onlyOnIndex();

        yield TextField::new('name', 'Название');

        // --- Цена ---
        yield TextField::new('soldPrice', 'Цена')
            ->formatValue(fn($v, $e) => $v
                ? number_format((float)$v, 0, '.', ' ') . ' ' . ($e->getCurrency()?->getCurrency() ?? '₽')
                : '—')
            ->hideOnForm();

        yield MoneyField::new('soldPrice', 'Цена')
            ->setCurrency('RUB')
            ->setStoredAsCents(false)
            ->onlyOnForms();

        // --- Статус ---
        yield TextField::new('status', 'Статус')
            ->formatValue(fn($v) => match ($v) {
                Good::STATUS_SOLD      => '<span style="color:#d9534f;font-weight:600">● Продано</span>',
                Good::STATUS_WITHDRAWN => '<span style="color:#f0ad4e;font-weight:600">● Изъято</span>',
                Good::STATUS_HIDDEN    => '<span style="color:#999">● Скрыто</span>',
                Good::STATUS_ACTIVE    => '<span style="color:#5cb85c;font-weight:600">● Активно</span>',
                default                => $v ?? '—',
            })
            ->renderAsHtml()
            ->hideOnForm();

        yield ChoiceField::new('status', 'Статус')
            ->onlyOnForms()
            ->setChoices([
                'Активно' => Good::STATUS_ACTIVE,
                'Продано' => Good::STATUS_SOLD,
                'Изъято'  => Good::STATUS_WITHDRAWN,
                'Скрыто'  => Good::STATUS_HIDDEN,
            ]);

        yield DateTimeField::new('statusDate', 'Дата обновления')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->hideOnForm();

        // --- Филиал ---
        yield TextField::new('merchant.name', 'Филиал')->onlyOnIndex();
        yield AssociationField::new('merchant', 'Филиал')->onlyOnForms()->autocomplete();

        // --- Категория и вид изделия ---
        yield AssociationField::new('category', 'Категория')
            ->onlyOnForms()
            ->autocomplete();

        yield AssociationField::new('goodType', 'Вид изделия')
            ->onlyOnForms()
            ->autocomplete();

        // --- Металл ---
        yield AssociationField::new('metalStandard', 'Металл / проба')
            ->onlyOnForms()
            ->autocomplete();

        yield AssociationField::new('metalColor', 'Цвет металла')
            ->onlyOnForms()
            ->autocomplete();

        // --- Камни ---
        yield BooleanField::new('hasStone', 'Со вставкой')   // ← исправлено: было hasStones
            ->onlyOnForms();

        yield AssociationField::new('stoneType', 'Тип камня')
            ->onlyOnForms()
            ->autocomplete();

        // --- Прочее ---
        yield TextField::new('size', 'Размер')->onlyOnForms();

        yield TextareaField::new('description', 'Описание')
            ->onlyOnForms()
            ->setNumOfRows(4);

        yield TextareaField::new('specification', 'Спецификации')
            ->onlyOnForms()
            ->setNumOfRows(4);

        // --- Detail only ---
        yield AssociationField::new('merchant', 'Филиал')->onlyOnDetail();
        yield AssociationField::new('category', 'Категория')->onlyOnDetail();
        yield AssociationField::new('goodType', 'Вид изделия')->onlyOnDetail();
        yield AssociationField::new('metalStandard', 'Металл / проба')->onlyOnDetail();
        yield AssociationField::new('metalColor', 'Цвет металла')->onlyOnDetail();
        yield AssociationField::new('stoneType', 'Тип камня')->onlyOnDetail();
        yield TextField::new('hiddenReasonLabel', 'Причина скрытия')->onlyOnDetail();
        yield TextField::new('description', 'Описание')->onlyOnDetail();
        yield TextField::new('specification', 'Спецификации')->onlyOnDetail();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof Good) {
            $entityInstance->setStatusDate(new \DateTime());
        }
        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof Good) {
            $entityInstance->setStatusDate(new \DateTime());
        }
        parent::updateEntity($em, $entityInstance);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'Название'))
            ->add(EntityFilter::new('merchant', 'Филиал'))
            ->add(EntityFilter::new('category', 'Категория'))
            ->add(ChoiceFilter::new('status', 'Статус')->setChoices([
                'Продано'  => Good::STATUS_SOLD,
                'Изъято'   => Good::STATUS_WITHDRAWN,
                'Скрыто'   => Good::STATUS_HIDDEN,
            ]));
    }
}
