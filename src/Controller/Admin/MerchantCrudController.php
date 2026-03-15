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
        yield IdField::new('id', 'ID')->setMaxLength(10);
        yield TextField::new('name', 'Название');

        yield TextField::new('city.name', 'Город')
            ->onlyOnIndex();

        yield AssociationField::new('city', 'Город')
            ->onlyOnDetail();

        yield TextField::new('phone', 'Телефон');

        yield TextField::new('address', 'Адрес')
            ->onlyOnDetail();

        yield UrlField::new('shortlink', 'Ссылка')
            ->onlyOnDetail();

        yield TextareaField::new('description', 'Описание')
            ->onlyOnDetail()
            ->setNumOfRows(4);

        if ($pageName !== Crud::PAGE_EDIT && $pageName !== Crud::PAGE_NEW) {
            yield ImageField::new('imagePreview', 'Логотип')
                ->setTemplatePath('admin/field/merchant_logo.html.twig')
                ->setSortable(false);
        }
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'Название'));
    }
}
