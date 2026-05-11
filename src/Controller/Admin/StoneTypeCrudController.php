<?php

namespace App\Controller\Admin;

use App\Entity\StoneType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class StoneTypeCrudController extends AbstractProtectedCrudController
{
    public function __construct(private EntityManagerInterface $em) {}

    public static function getEntityFqcn(): string
    {
        return StoneType::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Тип камня')
            ->setEntityLabelInPlural('Типы камней')
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Название');
        yield TextField::new('code', 'Код')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $v ?? '—');
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof StoneType) return null;

        $loanedCount = $this->em->createQuery(
            'SELECT COUNT(li) FROM App\\Entity\\LoanedItem li WHERE li.stoneType = :s'
        )->setParameter('s', $entity)->getSingleScalarResult();

        $goodCount = $this->em->createQuery(
            'SELECT COUNT(g) FROM App\\Entity\\Good g WHERE g.stoneType = :s'
        )->setParameter('s', $entity)->getSingleScalarResult();

        if ($loanedCount + $goodCount > 0) {
            $parts = [];
            if ($loanedCount > 0) $parts[] = "{$loanedCount} предметов залога";
            if ($goodCount   > 0) $parts[] = "{$goodCount} товаров";

            return sprintf(
                'Невозможно удалить тип камня «%s»: он используется в %s.',
                $entity->getName(), implode(', ', $parts)
            );
        }

        return null;
    }
}
