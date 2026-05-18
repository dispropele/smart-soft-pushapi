<?php
namespace App\Controller\Admin;

use App\Entity\Tariff;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TariffCrudController extends AbstractProtectedCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tariff::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Тариф')
            ->setEntityLabelInPlural('Тарифы')
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Название тарифа');
        yield NumberField::new('dailyRate', 'Ежедневная ставка (%)')
            ->setNumDecimals(4)
            ->setHelp('Например: 0.3 = 0.3% в день = ~9% в месяц')
            ->setFormTypeOptions(['attr' => ['step' => '0.0001', 'min' => 0]]);
        yield BooleanField::new('isActive', 'Активен');
    }
}
