<?php

namespace App\Controller\Admin;

use App\Entity\Insert;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class InsertCrudController extends AbstractProtectedCrudController
{
    public function __construct(private EntityManagerInterface $em) {}

    public static function getEntityFqcn(): string { return Insert::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Вставка')
            ->setEntityLabelInPlural('Вставки')
            ->setDefaultSort(['insertType' => 'ASC', 'name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('insertType', 'Тип')->autocomplete();
        yield TextField::new('name', 'Название')
            ->setFormTypeOptions(['attr' => ['maxlength' => 100]]);
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof Insert) return null;

        $count = $this->em->createQuery(
            'SELECT COUNT(p) FROM App\Entity\PledgedItem p WHERE p.insert = :i'
        )->setParameter('i', $entity)->getSingleScalarResult();

        if ($count > 0) {
            return sprintf('Невозможно удалить «%s»: используется в %d предметах.', $entity->getName(), $count);
        }
        return null;
    }
}