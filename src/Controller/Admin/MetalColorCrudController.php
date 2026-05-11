<?php

namespace App\Controller\Admin;

use App\Entity\MetalColor;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class MetalColorCrudController extends AbstractCrudController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public static function getEntityFqcn(): string
    {
        return MetalColor::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Цвет металла')
            ->setEntityLabelInPlural('Цвета металлов')
            ->setDefaultSort(['metal.name' => 'ASC', 'name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        
        yield AssociationField::new('metal', 'Металл')
            ->autocomplete()
            ->setRequired(true);
        
        yield TextField::new('name', 'Название');
        
        yield TextField::new('code', 'Код')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $v ?? '—');
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof MetalColor) {
            parent::deleteEntity($entityManager, $entityInstance);
            return;
        }

        $loanedItemCount = $this->entityManager->createQuery(
            'SELECT COUNT(li) FROM App\\Entity\\LoanedItem li WHERE li.metalColor = :color'
        )->setParameter('color', $entityInstance)->getSingleScalarResult();

        $goodCount = $this->entityManager->createQuery(
            'SELECT COUNT(g) FROM App\\Entity\\Good g WHERE g.metalColor = :color'
        )->setParameter('color', $entityInstance)->getSingleScalarResult();

        if ($loanedItemCount > 0 || $goodCount > 0) {
            throw new ForbiddenActionException(
                sprintf(
                    'Невозможно удалить цвет металла: он используется в %d предметах залога и %d товарах.',
                    $loanedItemCount,
                    $goodCount
                )
            );
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
