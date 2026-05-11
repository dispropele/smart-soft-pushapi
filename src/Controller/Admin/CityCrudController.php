<?php

namespace App\Controller\Admin;

use App\Entity\City;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CityCrudController extends AbstractCrudController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public static function getEntityFqcn(): string
    {
        return City::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Город')
            ->setEntityLabelInPlural('Города')
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Название');
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof City) {
            parent::deleteEntity($entityManager, $entityInstance);
            return;
        }

        $merchantCount = $this->entityManager->createQuery(
            'SELECT COUNT(m) FROM App\\Entity\\Merchant m WHERE m.city = :city'
        )->setParameter('city', $entityInstance)->getSingleScalarResult();

        if ($merchantCount > 0) {
            throw new ForbiddenActionException(
                sprintf(
                    'Невозможно удалить город: он используется в %d филиалах.',
                    $merchantCount
                )
            );
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
