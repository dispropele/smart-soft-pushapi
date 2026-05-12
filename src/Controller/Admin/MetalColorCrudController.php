<?php

namespace App\Controller\Admin;

use App\Admin\AdminFormAttributes;
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
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('code', 'Код')->onlyOnIndex();
        yield AssociationField::new('metal', 'Металл')->autocomplete()->setRequired(false);
        yield TextField::new('name', 'Название')
            ->setFormTypeOptions(['attr' => ['maxlength' => 100]]);
        yield TextField::new('code', 'Код (латиница)')
            ->setRequired(false)
            ->setFormTypeOptions(array_merge(['required' => false], AdminFormAttributes::slugCode()));
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof MetalColor) return null;

        $pledgedCount = (int) $this->em->createQuery(
            'SELECT COUNT(p) FROM App\Entity\PledgedItem p WHERE p.metalColor = :c'
        )->setParameter('c', $entity)->getSingleScalarResult();

        if ($pledgedCount > 0) {
            return sprintf(
                'Невозможно удалить цвет «%s»: он используется в %d предметах залога.',
                $entity->getName(), $pledgedCount
            );
        }

        return null;
    }
}
