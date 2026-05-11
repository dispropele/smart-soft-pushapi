<?php

namespace App\Controller\Admin;

use App\Entity\MetalStandard;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MetalStandardCrudController extends AbstractCrudController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public static function getEntityFqcn(): string
    {
        return MetalStandard::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Проба')
            ->setEntityLabelInPlural('Пробы металлов')
            ->setDefaultSort(['metal' => 'ASC', 'name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('metal', 'Металл')
            ->autocomplete();

        yield TextField::new('name', 'Проба (напр. 585, 925)');
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof MetalStandard) {
            parent::deleteEntity($entityManager, $entityInstance);
            return;
        }

        $loanedItemCount = $this->entityManager->createQuery(
            'SELECT COUNT(li) FROM App\\Entity\\LoanedItem li WHERE li.metalStandard = :standard'
        )->setParameter('standard', $entityInstance)->getSingleScalarResult();

        $goodCount = $this->entityManager->createQuery(
            'SELECT COUNT(g) FROM App\\Entity\\Good g WHERE g.metalStandard = :standard'
        )->setParameter('standard', $entityInstance)->getSingleScalarResult();

        if ($loanedItemCount > 0 || $goodCount > 0) {
            throw new ForbiddenActionException(
                sprintf(
                    'Невозможно удалить пробу: она используется в %d предметах залога и %d товарах.',
                    $loanedItemCount,
                    $goodCount
                )
            );
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
