<?php

namespace App\Controller\Admin;

use App\Entity\Currency;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CurrencyCrudController extends AbstractCrudController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public static function getEntityFqcn(): string
    {
        return Currency::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Валюта')
            ->setEntityLabelInPlural('Валюты')
            ->setDefaultSort(['currency' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('currency', 'Символ (₽, $, €)');
        yield TextField::new('name', 'Название');
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Currency) {
            parent::deleteEntity($entityManager, $entityInstance);
            return;
        }

        $goodCount = $this->entityManager->createQuery(
            'SELECT COUNT(g) FROM App\\Entity\\Good g WHERE g.currency = :currency'
        )->setParameter('currency', $entityInstance)->getSingleScalarResult();

        if ($goodCount > 0) {
            throw new ForbiddenActionException(
                sprintf(
                    'Невозможно удалить валюту: она используется в %d товарах.',
                    $goodCount
                )
            );
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
