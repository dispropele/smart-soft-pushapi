<?php

namespace App\Controller\Admin;

use App\Entity\MetalStandard;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MetalStandardCrudController extends AbstractProtectedCrudController
{
    public function __construct(private EntityManagerInterface $em) {}

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
        yield AssociationField::new('metal', 'Металл')->autocomplete();
        yield TextField::new('name', 'Проба (напр. 585, 925)');
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof MetalStandard) return null;

        $loanedCount = $this->em->createQuery(
            'SELECT COUNT(li) FROM App\\Entity\\LoanedItem li WHERE li.metalStandard = :s'
        )->setParameter('s', $entity)->getSingleScalarResult();

        $goodCount = $this->em->createQuery(
            'SELECT COUNT(g) FROM App\\Entity\\Good g WHERE g.metalStandard = :s'
        )->setParameter('s', $entity)->getSingleScalarResult();

        if ($loanedCount + $goodCount > 0) {
            $parts = [];
            if ($loanedCount > 0) $parts[] = "{$loanedCount} предметов залога";
            if ($goodCount   > 0) $parts[] = "{$goodCount} товаров";

            return sprintf(
                'Невозможно удалить пробу «%s»: она используется в %s.',
                $entity, implode(', ', $parts)
            );
        }

        return null;
    }
}
