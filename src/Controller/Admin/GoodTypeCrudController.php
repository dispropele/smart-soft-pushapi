<?php

namespace App\Controller\Admin;

use App\Entity\GoodType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class GoodTypeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return GoodType::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Вид изделия')
            ->setEntityLabelInPlural('Виды изделий')
            ->setDefaultSort(['category' => 'ASC', 'name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('code', 'Код');
        yield TextField::new('name', 'Название');
        yield AssociationField::new('category', 'Категория');
    }
}
