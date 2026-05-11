<?php

namespace App\Controller\Admin;

use App\Entity\MetalColor;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MetalColorCrudController extends AbstractProtectedCrudController
{
    public function __construct(private EntityManagerInterface $em) {}

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
        yield AssociationField::new('metal', 'Металл')->autocomplete()->setRequired(true);
        yield TextField::new('name', 'Название');
        yield TextField::new('code', 'Код')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $v ?? '—');
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof MetalColor) return null;

        $loanedCount = $this->em->createQuery(
            'SELECT COUNT(li) FROM App\\Entity\\LoanedItem li WHERE li.metalColor = :c'
        )->setParameter('c', $entity)->getSingleScalarResult();

        $goodCount = $this->em->createQuery(
            'SELECT COUNT(g) FROM App\\Entity\\Good g WHERE g.metalColor = :c'
        )->setParameter('c', $entity)->getSingleScalarResult();

        if ($loanedCount + $goodCount > 0) {
            $parts = [];
            if ($loanedCount > 0) $parts[] = "{$loanedCount} предметов залога";
            if ($goodCount   > 0) $parts[] = "{$goodCount} товаров";

            return sprintf(
                'Невозможно удалить цвет «%s»: он используется в %s.',
                $entity->getName(), implode(', ', $parts)
            );
        }

        return null;
    }
}
