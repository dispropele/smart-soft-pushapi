<?php

namespace App\Controller\Admin;

use App\Entity\PushApiLog;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class PushApiLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PushApiLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Лог')
            ->setEntityLabelInPlural('Логи Push API')
            ->setPageTitle(Crud::PAGE_INDEX, 'Входящие запросы Push API')
            ->setPageTitle(Crud::PAGE_DETAIL, fn (PushApiLog $l) => 'Лог #' . $l->getId())
            ->setDefaultSort(['receivedAt' => 'DESC'])
            ->setPaginatorPageSize(100)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', '#')->setMaxLength(8);

        yield DateTimeField::new('receivedAt', 'Время')
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield TextField::new('entityType', 'Объект')
            ->formatValue(fn ($v) => match ($v) {
                'merchant'       => '🏢 Филиал (архив)',
                'pledged_item'   => '📦 Предмет залога',
                'good'           => '📦 Товар (архив)',
                default          => $v ?? '—',
            });

        yield TextField::new('eventType', 'Событие')
            ->formatValue(fn ($v) => match ($v) {
                'add'    => '<span style="color:#5cb85c">＋ add</span>',
                'edit'   => '<span style="color:#f0ad4e">✎ edit</span>',
                'remove' => '<span style="color:#d9534f">✕ remove</span>',
                'image_download_fail' => '<span style="color:#d9534f">⚠ img fail</span>',
                default  => $v ?? '—',
            })
            ->renderAsHtml();

        yield IntegerField::new('entityId', 'ID объекта');

        yield BooleanField::new('authStatus', 'Авторизация')
            ->renderAsSwitch(false);

        yield BooleanField::new('processStatus', 'Обработан')
            ->renderAsSwitch(false);

        yield TextField::new('errorMessage', 'Ошибка')
            ->onlyOnDetail();

        // Payload только на странице детали — в виде JSON-редактора
        yield CodeEditorField::new('payload', 'Payload')
            ->onlyOnDetail()
            ->setNumOfRows(20)
            ->formatValue(fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('entityType', 'Объект')->setChoices([
                'Филиал (архив)'   => 'merchant',
                'Предмет залога'   => 'pledged_item',
                'Товар (архив)'    => 'good',
            ]))
            ->add(ChoiceFilter::new('eventType', 'Событие')->setChoices([
                'Добавление'    => 'add',
                'Редактирование'=> 'edit',
                'Удаление'      => 'remove',
            ]))
            ->add(BooleanFilter::new('authStatus', 'Авторизован'))
            ->add(BooleanFilter::new('processStatus', 'Обработан'))
            ->add(DateTimeFilter::new('receivedAt', 'Дата'));
    }
}
