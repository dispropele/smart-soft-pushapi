<?php

namespace App\Controller\Admin;

use App\Entity\City;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CityCrudController extends AbstractProtectedCrudController
{
    public function __construct(private EntityManagerInterface $em) {}

    public static function getEntityFqcn(): string
    {
        return City::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Город')
            ->setEntityLabelInPlural('Города')
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
        if (!$entity instanceof City) return null;

        $count = $this->em->createQuery(
            'SELECT COUNT(m) FROM App\\Entity\\Merchant m WHERE m.city = :city'
        )->setParameter('city', $entity)->getSingleScalarResult();

        if ($count > 0) {
            return sprintf(
                'Невозможно удалить город «%s»: он привязан к %d филиалам. Сначала измените город у филиалов.',
                $entity->getName(), $count
            );
        }

        return null;
    }
}
