<?php

namespace App\Controller\Admin;

use App\Entity\InsertType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class InsertTypeCrudController extends AbstractProtectedCrudController
{
    public function __construct(private EntityManagerInterface $em) {}

    public static function getEntityFqcn(): string { return InsertType::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Тип вставки')
            ->setEntityLabelInPlural('Типы вставок')
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Название')
            ->setFormTypeOptions(['attr' => ['maxlength' => 100]]);
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof InsertType) return null;

        $count = $this->em->createQuery(
            'SELECT COUNT(i) FROM App\Entity\Insert i WHERE i.insertType = :t'
        )->setParameter('t', $entity)->getSingleScalarResult();

        if ($count > 0) {
            return sprintf('Невозможно удалить тип «%s»: используется в %d вставках.', $entity->getName(), $count);
        }
        return null;
    }
}