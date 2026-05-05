<?php

namespace App\Controller\Admin;

use App\Entity\LoanedItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

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
            ->setPageTitle(Crud::PAGE_DETAIL, fn (LoanedItem $i) => 'Имущество #' . $i->getId());
    }

    public function configureFields(string $pageName): iterable
    {
        // При использовании во вложенной форме поле loanTicket лучше скрыть
        yield AssociationField::new('loanTicket', 'Залоговый билет')->hideOnForm();

        yield TextField::new('name', 'Название');
        yield TextField::new('jewelryType', 'Тип украшения');
        
        yield AssociationField::new('metal', 'Металл');
        yield AssociationField::new('metalStandard', 'Проба');

        yield NumberField::new('weight', 'Вес (г)')
            ->setNumDecimals(2)
            ->setFormTypeOptions([
                'attr' => [
                    'inputmode' => 'decimal',
                    'pattern' => '[0-9]+([\\.,][0-9]{0,2})?',
                    'step' => '0.01',
                    'min' => 0,
                ],
            ]);
        
        yield MoneyField::new('estimatedValue', 'Оценочная стоимость')
            ->setCurrency('RUB')
            ->setStoredAsCents(false)
            ->setFormTypeOptions([
                'attr' => [
                    'inputmode' => 'numeric',
                    'pattern' => '[0-9]*',
                    'min' => 0,
                ],
            ]);

        yield TextField::new('condition', 'Состояние');
        yield TextField::new('description', 'Описание');
    }
}
