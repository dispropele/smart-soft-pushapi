<?php

namespace App\Controller\Admin;

use App\Entity\PledgedItemInsert;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PledgedItemInsertCrudController extends AbstractProtectedCrudController
{
    public static function getEntityFqcn(): string { return PledgedItemInsert::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Вставка изделия')
            ->setEntityLabelInPlural('Вставки изделий')
            ->setPageTitle(Crud::PAGE_NEW, 'Добавить вставку изделия')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать вставку изделия')
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('insert', 'Вставка')->autocomplete();
        yield NumberField::new('weight', 'Вес вставки (г)')
            ->setNumDecimals(2)
            ->setFormTypeOptions(['attr' => ['step' => '0.01', 'min' => 0]]);
        yield NumberField::new('quantity', 'Количество')
            ->setNumDecimals(0)
            ->setFormTypeOptions(['attr' => ['min' => 1]]);
        yield TextareaField::new('description', 'Описание')->setNumOfRows(2)->setRequired(false);
    }
}
