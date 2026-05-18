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
            ->setPageTitle(Crud::PAGE_NEW, 'Добавить валюту')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать валюту')
            ->setDefaultSort(['currency' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('currency', 'Символ (₽, $, €)')
            ->setFormTypeOptions(['attr' => ['maxlength' => 50]]);
        yield TextField::new('name', 'Название')
            ->setFormTypeOptions(['attr' => ['maxlength' => 255]]);
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof Currency) return null;

        $count = $this->em->createQuery(
            'SELECT COUNT(p) FROM App\Entity\PledgedItem p WHERE p.currency = :cur'
        )->setParameter('cur', $entity)->getSingleScalarResult();

        if ($count > 0) {
            return sprintf(
                'Невозможно удалить валюту «%s»: она используется в %d предметах залога.',
                $entity->getCurrency(), $count
            );
        }

        return null;
    }
}
