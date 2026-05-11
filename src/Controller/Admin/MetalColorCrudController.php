<?php

namespace App\Controller\Admin;

use App\Entity\MetalColor;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MetalColorCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MetalColor::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Цвет металла')
            ->setEntityLabelInPlural('Цвета металлов')
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('code', 'Код');
        yield TextField::new('name', 'Название');
    }
}
