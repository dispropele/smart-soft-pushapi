<?php

namespace App\Controller\Admin;

use App\Entity\Metal;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MetalCrudController extends AbstractCrudController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public static function getEntityFqcn(): string
    {
        return Metal::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Металл')
            ->setEntityLabelInPlural('Металлы')
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
        if (!$entityInstance instanceof Metal) {
            parent::deleteEntity($entityManager, $entityInstance);
            return;
        }

        $metalColorCount = $this->entityManager->createQuery(
            'SELECT COUNT(mc) FROM App\\Entity\\MetalColor mc WHERE mc.metal = :metal'
        )->setParameter('metal', $entityInstance)->getSingleScalarResult();

        $metalStandardCount = $this->entityManager->createQuery(
            'SELECT COUNT(ms) FROM App\\Entity\\MetalStandard ms WHERE ms.metal = :metal'
        )->setParameter('metal', $entityInstance)->getSingleScalarResult();

        $loanedItemCount = $this->entityManager->createQuery(
            'SELECT COUNT(li) FROM App\\Entity\\LoanedItem li WHERE li.metal = :metal'
        )->setParameter('metal', $entityInstance)->getSingleScalarResult();

        $goodCount = $this->entityManager->createQuery(
            'SELECT COUNT(g) FROM App\\Entity\\Good g WHERE g.metal = :metal'
        )->setParameter('metal', $entityInstance)->getSingleScalarResult();

        if ($metalColorCount > 0 || $metalStandardCount > 0 || $loanedItemCount > 0 || $goodCount > 0) {
            throw new ForbiddenActionException(
                sprintf(
                    'Невозможно удалить металл "%s": он используется в %d цветах, %d пробах, %d предметах залога и %d товарах.',
                    $entityInstance->getName(),
                    $metalColorCount,
                    $metalStandardCount,
                    $loanedItemCount,
                    $goodCount
                )
            );
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
