<?php

namespace App\Controller\Admin;

use App\Entity\Merchant;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class MerchantCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Merchant::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Филиал')
            ->setEntityLabelInPlural('Все филиалы')
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(30)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->setMaxLength(10)->hideOnForm();
        yield TextField::new('name', 'Название');

        yield TextField::new('city.name', 'Город')
            ->onlyOnIndex();

        yield AssociationField::new('city', 'Город');

        yield TextField::new('phone', 'Телефон')
            ->setFormTypeOptions([
                'attr' => [
                    'inputmode' => 'numeric',
                    'pattern' => '[0-9]*',
                    'autocomplete' => 'off',
                ],
            ]);

        yield TextField::new('address', 'Адрес')
            ->onlyOnDetail();

        yield UrlField::new('shortlink', 'Ссылка')
            ->onlyOnDetail();

        yield TextareaField::new('description', 'Описание')
            ->setNumOfRows(4);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // NEW/EDIT уже есть по умолчанию, добавляем только DETAIL
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'Название'));
    }
}
