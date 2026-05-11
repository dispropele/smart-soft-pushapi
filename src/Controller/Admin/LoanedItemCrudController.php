<?php

namespace App\Controller\Admin;

use App\Entity\LoanedItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class LoanedItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return LoanedItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Заложенное имущество')
            ->setEntityLabelInPlural('Заложенное имущество')
            ->setPageTitle(Crud::PAGE_INDEX, 'Заложенное имущество')
            ->setPageTitle(Crud::PAGE_NEW, 'Добавить имущество')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактирование имущества')
            ->setPageTitle(Crud::PAGE_DETAIL, fn(LoanedItem $i) => 'Имущество #' . $i->getId())
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        // Связь с билетом — скрыта при использовании во вложенной форме
        yield AssociationField::new('loanTicket', 'Залоговый билет')
            ->hideOnForm()
            ->autocomplete();

        // === Основные ===
        yield TextField::new('name', 'Название');

        yield AssociationField::new('goodType', 'Вид изделия')
            ->autocomplete()
            ->setRequired(false);

        // === Металл ===
        yield AssociationField::new('metal', 'Металл')
            ->autocomplete()
            ->setRequired(false);

        yield AssociationField::new('metalStandard', 'Проба')
            ->autocomplete()
            ->setRequired(false);

        yield AssociationField::new('metalColor', 'Цвет металла')
            ->autocomplete()
            ->setRequired(false);

        // === Камни ===
        yield BooleanField::new('hasStone', 'Со вставкой (камень)');

        yield AssociationField::new('stoneType', 'Тип камня')
            ->autocomplete()
            ->setRequired(false);

        // === Физические параметры ===
        yield NumberField::new('weight', 'Вес лома (г)')
            ->setNumDecimals(2)
            ->setFormTypeOptions([
                'attr' => ['inputmode' => 'decimal', 'step' => '0.01', 'min' => 0],
            ]);

        yield MoneyField::new('estimatedValue', 'Оценочная стоимость')
            ->setCurrency('RUB')
            ->setStoredAsCents(false)
            ->setFormTypeOptions([
                'attr' => ['inputmode' => 'numeric', 'min' => 0],
            ]);

        yield TextField::new('condition', 'Состояние')
            ->setRequired(false);

        yield TextField::new('description', 'Описание')
            ->setRequired(false);

        // === Только на просмотре ===
        yield DateTimeField::new('createdAt', 'Добавлено')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->onlyOnDetail();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('loanTicket', 'Билет'))
            ->add(EntityFilter::new('metal', 'Металл'))
            ->add(EntityFilter::new('goodType', 'Вид изделия'));
    }
}
