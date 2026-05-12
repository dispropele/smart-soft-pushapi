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
        yield TextField::new('name', 'Проба (напр. 585, 925)')
            ->setFormTypeOptions(['attr' => ['maxlength' => 50]]);
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof MetalStandard) return null;

        $pledgedCount = (int) $this->em->createQuery(
            'SELECT COUNT(p) FROM App\Entity\PledgedItem p WHERE p.metalStandard = :s'
        )->setParameter('s', $entity)->getSingleScalarResult();

        if ($pledgedCount > 0) {
            return sprintf(
                'Невозможно удалить пробу «%s»: она используется в %d предметах залога.',
                (string) $entity, $pledgedCount
            );
        }

        return null;
    }
}
