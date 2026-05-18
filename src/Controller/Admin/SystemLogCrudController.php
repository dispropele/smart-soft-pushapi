<?php
namespace App\Controller\Admin;

use App\Entity\SystemLog;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class SystemLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return SystemLog::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Запись лога')
            ->setEntityLabelInPlural('Системные логи')
            ->setPageTitle(Crud::PAGE_INDEX,  'Системные логи')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Запись лога')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(100)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', '#')->setMaxLength(8);

        yield DateTimeField::new('createdAt', 'Время')
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield TextField::new('level', 'Уровень')
            ->formatValue(fn($v) => match($v) {
                'info'     => '<span class="badge bg-secondary">Инфо</span>',
                'warning'  => '<span class="badge bg-warning text-dark">Предупреждение</span>',
                'error'    => '<span class="badge bg-danger">Ошибка</span>',
                'critical' => '<span class="badge bg-dark">Критично</span>',
                default    => '<span class="badge bg-light text-dark">' . htmlspecialchars((string)$v) . '</span>',
            })
            ->renderAsHtml();

        yield TextField::new('channel', 'Канал')
            ->formatValue(fn($v) => match($v) {
                'auth'     => '🔐 Авторизация',
                'repledge' => '🔄 Перезалог',
                'sale'     => '💰 Продажа',
                'ticket'   => '🎫 Билет',
                'system'   => '⚙️ Система',
                default    => '⚙️ ' . htmlspecialchars((string)$v),
            });

        yield TextField::new('message', 'Сообщение')->setMaxLength(120);

        yield IntegerField::new('referenceId', 'ID объекта')->onlyOnDetail();

        yield CodeEditorField::new('context', 'Контекст')
            ->onlyOnDetail()
            ->setNumOfRows(15)
            ->formatValue(fn($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
            ->add(ChoiceFilter::new('level', 'Уровень')->setChoices([
                'Инфо'           => 'info',
                'Предупреждение' => 'warning',
                'Ошибка'         => 'error',
                'Критично'       => 'critical',
            ]))
            ->add(ChoiceFilter::new('channel', 'Канал')->setChoices([
                'Авторизация' => 'auth',
                'Перезалог'   => 'repledge',
                'Продажа'     => 'sale',
                'Билет'       => 'ticket',
                'Система'     => 'system',
            ]))
            ->add(DateTimeFilter::new('createdAt', 'Дата'));
    }
}
