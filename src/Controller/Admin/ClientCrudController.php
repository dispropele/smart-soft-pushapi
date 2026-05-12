<?php

namespace App\Controller\Admin;

use App\Admin\AdminFormAttributes;
use App\Entity\Client;
use App\Entity\LoanTicket;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ClientCrudController extends AbstractProtectedCrudController
{
    public function __construct(private EntityManagerInterface $em) {}

    public static function getEntityFqcn(): string { return Client::class; }

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
        yield TextField::new('fullName', 'ФИО')
            ->setFormTypeOptions(['attr' => ['maxlength' => 255]]);
        yield TextField::new('passportNumber', 'Номер паспорта')
            ->setFormTypeOptions(array_merge(['required' => true], AdminFormAttributes::passportNumber()));
        yield TextField::new('passportSeries', 'Серия паспорта')
            ->setFormTypeOptions(array_merge(['required' => false], AdminFormAttributes::passportSeries()));
        yield TextField::new('address', 'Адрес')
            ->setFormTypeOptions(['attr' => ['maxlength' => 2000], 'required' => false]);
        yield TextField::new('phone', 'Телефон')
            ->setFormTypeOptions(array_merge(['required' => false], AdminFormAttributes::phoneDigits()));
        yield TextField::new('email', 'Email')
            ->setFormTypeOptions(['attr' => ['maxlength' => 100], 'required' => false]);
        yield DateTimeField::new('createdAt', 'Дата создания')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->hideOnForm();
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof Client) return null;

        $openCount = $this->em->createQuery(
            'SELECT COUNT(lt) FROM App\\Entity\\LoanTicket lt
             WHERE lt.client = :c
             AND lt.status IN (:statuses)'
        )
            ->setParameter('c', $entity)
            ->setParameter('statuses', [LoanTicket::STATUS_OPEN, LoanTicket::STATUS_EXPIRED])
            ->getSingleScalarResult();

        if ($openCount > 0) {
            return sprintf(
                'Невозможно удалить клиента «%s»: у него %d открытых залоговых билетов. Сначала закройте все билеты.',
                $entity->getFullName(), $openCount
            );
        }

        return null;
    }
}
