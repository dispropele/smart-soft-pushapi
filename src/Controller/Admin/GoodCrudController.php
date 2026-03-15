<?php

namespace App\Controller\Admin;

use App\Entity\Good;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;

class GoodCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Good::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Товар')
            ->setEntityLabelInPlural('Скрытые и проданные товары')
            ->setDefaultSort(['statusDate' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    /** только не активные товары */
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.status != :activeStatus')
            ->setParameter('activeStatus', Good::STATUS_ACTIVE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->setMaxLength(10);

        yield Field::new('images', 'Фото')
            ->setTemplatePath('admin/field/good_cover.html.twig')
            ->setSortable(false)
            ->onlyOnIndex();

        yield TextField::new('name', 'Название');

        yield TextField::new('soldPrice', 'Цена')
            ->formatValue(fn ($v, $e) => $v
                ? number_format((float)$v, 0, '.', ' ') . ' ' . ($e->getCurrency()?->getCurrency() ?? '₽')
                : '—'
            );

        yield TextField::new('merchant.name', 'Филиал')
            ->onlyOnIndex();

        yield AssociationField::new('merchant', 'Филиал')
            ->onlyOnDetail();

        yield AssociationField::new('category', 'Категория')
            ->onlyOnDetail();

        yield AssociationField::new('subcategory', 'Подкатегория')
            ->onlyOnDetail();

        yield TextField::new('status', 'Статус')
            ->formatValue(fn ($v) => match ($v) {
                Good::STATUS_SOLD      => '<span style="color:#d9534f;font-weight:600">● Продано</span>',
                Good::STATUS_WITHDRAWN => '<span style="color:#f0ad4e;font-weight:600">● Изъято</span>',
                Good::STATUS_HIDDEN    => '<span style="color:#999">● Скрыто</span>',
                Good::STATUS_ACTIVE    => '<span style="color:#5cb85c;font-weight:600">● Активно</span>',
                default                => $v ?? '—',
            })
            ->renderAsHtml();

        yield TextField::new('hiddenReasonLabel', 'Причина скрытия')
            ->onlyOnDetail();

        yield DateTimeField::new('statusDate', 'Дата обновления')
            ->setFormat('dd.MM.yyyy HH:mm');

        yield TextField::new('description', 'Описание')
            ->onlyOnDetail();

        yield TextField::new('specification', 'Спецификации')
            ->onlyOnDetail();
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
            ->add(TextFilter::new('name', 'Название'))
            ->add(EntityFilter::new('merchant', 'Филиал'))
            ->add(ChoiceFilter::new('status', 'Статус')->setChoices([
                'Продано'  => Good::STATUS_SOLD,
                'Изъято'   => Good::STATUS_WITHDRAWN,
                'Скрыто'   => Good::STATUS_HIDDEN,
            ]));
    }
}
