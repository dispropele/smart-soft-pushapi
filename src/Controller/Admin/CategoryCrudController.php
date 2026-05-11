<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CategoryCrudController extends AbstractProtectedCrudController
{
    public function __construct(private EntityManagerInterface $em) {}

    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Категория')
            ->setEntityLabelInPlural('Категории')
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Название');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof Category) return null;

        $count = $this->em->createQuery(
            'SELECT COUNT(gt) FROM App\\Entity\\GoodType gt WHERE gt.category = :cat'
        )->setParameter('cat', $entity)->getSingleScalarResult();

        if ($count > 0) {
            return sprintf(
                'Невозможно удалить категорию «%s»: она используется в %d видах изделий. Сначала удалите или переназначьте их.',
                $entity->getName(), $count
            );
        }

        return null;
    }
}
