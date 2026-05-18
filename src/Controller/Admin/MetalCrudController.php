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
            ->setPageTitle(Crud::PAGE_NEW, 'Добавить металл')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать металл')
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Название')
            ->setFormTypeOptions(['attr' => ['maxlength' => 255]]);
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof Metal) return null;

        $pledgedByStandard = (int) $this->em->createQuery(
            'SELECT COUNT(p) FROM App\Entity\PledgedItem p JOIN p.metalStandard ms WHERE ms.metal = :m'
        )->setParameter('m', $entity)->getSingleScalarResult();

        $colorCount = (int) $this->em->createQuery(
            'SELECT COUNT(mc) FROM App\Entity\MetalColor mc WHERE mc.metal = :m'
        )->setParameter('m', $entity)->getSingleScalarResult();

        $standardCount = (int) $this->em->createQuery(
            'SELECT COUNT(ms) FROM App\Entity\MetalStandard ms WHERE ms.metal = :m'
        )->setParameter('m', $entity)->getSingleScalarResult();

        $total = $colorCount + $standardCount + $pledgedByStandard;
        if ($total > 0) {
            $parts = [];
            if ($colorCount > 0) {
                $parts[] = "{$colorCount} цветов металла";
            }
            if ($standardCount > 0) {
                $parts[] = "{$standardCount} проб";
            }
            if ($pledgedByStandard > 0) {
                $parts[] = "{$pledgedByStandard} предметов залога (через пробу)";
            }

            return sprintf(
                'Невозможно удалить металл «%s»: он используется в %s.',
                $entity->getName(), implode(', ', $parts)
            );
        }

        return null;
    }
}
