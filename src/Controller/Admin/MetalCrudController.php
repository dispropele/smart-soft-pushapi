<?php

namespace App\Controller\Admin;

use App\Entity\Metal;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MetalCrudController extends AbstractProtectedCrudController
{
    public function __construct(private EntityManagerInterface $em) {}

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

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof Metal) return null;

        $colorCount = $this->em->createQuery(
            'SELECT COUNT(mc) FROM App\\Entity\\MetalColor mc WHERE mc.metal = :m'
        )->setParameter('m', $entity)->getSingleScalarResult();

        $standardCount = $this->em->createQuery(
            'SELECT COUNT(ms) FROM App\\Entity\\MetalStandard ms WHERE ms.metal = :m'
        )->setParameter('m', $entity)->getSingleScalarResult();

        $loanedCount = $this->em->createQuery(
            'SELECT COUNT(li) FROM App\\Entity\\LoanedItem li WHERE li.metal = :m'
        )->setParameter('m', $entity)->getSingleScalarResult();

        // Good has no direct metal field — link is Good.metalStandard → MetalStandard.metal
        $goodCount = $this->em->createQuery(
            'SELECT COUNT(g) FROM App\\Entity\\Good g JOIN g.metalStandard ms WHERE ms.metal = :m'
        )->setParameter('m', $entity)->getSingleScalarResult();

        $total = $colorCount + $standardCount + $loanedCount + $goodCount;
        if ($total > 0) {
            $parts = [];
            if ($colorCount   > 0) $parts[] = "{$colorCount} цветов металла";
            if ($standardCount > 0) $parts[] = "{$standardCount} проб";
            if ($loanedCount  > 0) $parts[] = "{$loanedCount} предметов залога";
            if ($goodCount    > 0) $parts[] = "{$goodCount} товаров";

            return sprintf(
                'Невозможно удалить металл «%s»: он используется в %s.',
                $entity->getName(), implode(', ', $parts)
            );
        }

        return null;
    }
}
