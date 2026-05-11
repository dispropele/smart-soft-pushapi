<?php

namespace App\Controller\Admin;

use App\Entity\Currency;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CurrencyCrudController extends AbstractProtectedCrudController
{
    public function __construct(private EntityManagerInterface $em) {}

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

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof Currency) return null;

        $count = $this->em->createQuery(
            'SELECT COUNT(g) FROM App\\Entity\\Good g WHERE g.currency = :cur'
        )->setParameter('cur', $entity)->getSingleScalarResult();

        if ($count > 0) {
            return sprintf(
                'Невозможно удалить валюту «%s»: она используется в %d товарах.',
                $entity->getCurrency(), $count
            );
        }

        return null;
    }
}
