<?php

namespace App\Controller\Admin;

use App\Entity\LoanTicket;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use Doctrine\ORM\EntityManagerInterface;

class LoanTicketCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return LoanTicket::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Залоговый билет')
            ->setEntityLabelInPlural('Залоговые билеты')
            ->setPageTitle(Crud::PAGE_INDEX, 'Залоговые билеты')
            ->setPageTitle(Crud::PAGE_NEW, 'Новый залоговый билет')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактирование залогового билета')
            ->setPageTitle(Crud::PAGE_DETAIL, fn (LoanTicket $t) => 'Залоговый билет #' . $t->getId());
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        // Номер билета: делаем доступным только для чтения, если он генерируется системой
        yield TextField::new('ticketNumber', 'Номер билета')
            ->setFormTypeOptions(['disabled' => true])
            ->hideWhenCreating() // Скрываем при создании, так как сгенерируем сами
            ->setHelp('Генерируется автоматически');

        yield AssociationField::new('client', 'Клиент')->autocomplete();

        yield MoneyField::new('loanAmount', 'Сумма займа')
            ->setCurrency('RUB')
            ->setStoredAsCents(false)
            ->setFormTypeOptions([
                'attr' => [
                    'inputmode' => 'numeric',
                    'pattern' => '[0-9]*',
                    'min' => 0,
                ],
            ]);

        yield NumberField::new('interestRate', 'Процентная ставка (%)')
            ->setNumDecimals(2)
            ->setFormTypeOptions([
                'attr' => [
                    'inputmode' => 'decimal',
                    'pattern' => '[0-9]+([\\.,][0-9]{0,2})?',
                    'step' => '0.01',
                    'min' => 0,
                ],
            ]);

        yield DateTimeField::new('issuedAt', 'Дата выдачи')
            ->setFormat('dd.MM.yyyy HH:mm');

        yield DateTimeField::new('returnDate', 'Срок возврата')
            ->setFormat('dd.MM.yyyy HH:mm');

        yield ChoiceField::new('status', 'Статус')
            ->setChoices([
                'Открыт' => LoanTicket::STATUS_OPEN,
                'Закрыт' => LoanTicket::STATUS_CLOSED,
                'Просрочен' => LoanTicket::STATUS_EXPIRED,
            ]);

        // ТАБЛИЦА ИМУЩЕСТВА: позволяет добавлять вещи прямо в билете
        yield CollectionField::new('loanedItems', 'Заложенное имущество')
            ->useEntryCrudForm(LoanedItemCrudController::class)
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex(true)
            ->hideOnIndex();

        yield TextField::new('notes', 'Примечания');
    }

    // Автоматическая генерация номера перед сохранением
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof LoanTicket && !$entityInstance->getTicketNumber()) {
            $entityInstance->setTicketNumber($this->generateTicketNumber());
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    private function generateTicketNumber(): string
    {
        return 'ЛБ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
    }
}
