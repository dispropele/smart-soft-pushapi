<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ClientCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Client::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Клиент')
            ->setEntityLabelInPlural('Клиенты')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('fullName', 'ФИО');

        yield TextField::new('passportNumber', 'Номер паспорта')
            ->setFormTypeOptions([
                'attr' => [
                    'inputmode' => 'numeric',
                    'pattern' => '[0-9]*',
                    'autocomplete' => 'off',
                ],
            ]);

        yield TextField::new('passportSeries', 'Серия паспорта')
            ->setFormTypeOptions([
                'attr' => [
                    'inputmode' => 'numeric',
                    'pattern' => '[0-9]*',
                    'autocomplete' => 'off',
                ],
            ]);

        yield TextField::new('address', 'Адрес');

        yield TextField::new('phone', 'Телефон')
            ->setFormTypeOptions([
                'attr' => [
                    'inputmode' => 'numeric',
                    'pattern' => '[0-9]*',
                    'autocomplete' => 'off',
                ],
            ]);

        yield TextField::new('email', 'Email');

        yield DateTimeField::new('createdAt', 'Дата создания')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->hideOnForm();
    }
}
