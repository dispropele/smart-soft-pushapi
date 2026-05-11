<?php

namespace App\Controller\Admin;

use App\Entity\GoodType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class GoodTypeCrudController extends AbstractProtectedCrudController
{
    public function __construct(private EntityManagerInterface $em) {}

    public static function getEntityFqcn(): string
    {
        return GoodType::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Вид изделия')
            ->setEntityLabelInPlural('Виды изделий')
            ->setDefaultSort(['category' => 'ASC', 'name' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Название');
        yield AssociationField::new('category', 'Категория')->autocomplete();
        yield BooleanField::new('hasStones', 'Со вставками (камнями)')
            ->setHelp('Включите, если этот вид изделий обычно имеет камни');
        yield TextField::new('code', 'Код')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $v ?? '—');
    }

    /** Auto-generate code from name on create/update */
    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof GoodType && !$entityInstance->getCode()) {
            $entityInstance->setCode($this->generateCode($entityInstance->getName()));
        }
        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof GoodType && !$entityInstance->getCode()) {
            $entityInstance->setCode($this->generateCode($entityInstance->getName()));
        }
        parent::updateEntity($em, $entityInstance);
    }

    private function generateCode(string $name): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
            'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',' '=>'_',
        ];
        $code = mb_strtolower($name);
        $code = strtr($code, $map);
        $code = preg_replace('/[^a-z0-9_]/', '', $code);
        return mb_substr($code, 0, 50) ?: 'type_' . rand(1000, 9999);
    }

    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        if (!$entity instanceof GoodType) return null;

        $loanedCount = $this->em->createQuery(
            'SELECT COUNT(li) FROM App\\Entity\\LoanedItem li WHERE li.goodType = :t'
        )->setParameter('t', $entity)->getSingleScalarResult();

        $goodCount = $this->em->createQuery(
            'SELECT COUNT(g) FROM App\\Entity\\Good g WHERE g.goodType = :t'
        )->setParameter('t', $entity)->getSingleScalarResult();

        if ($loanedCount + $goodCount > 0) {
            $parts = [];
            if ($loanedCount > 0) $parts[] = "{$loanedCount} предметов залога";
            if ($goodCount   > 0) $parts[] = "{$goodCount} товаров";

            return sprintf(
                'Невозможно удалить вид изделия «%s»: он используется в %s.',
                $entity->getName(), implode(', ', $parts)
            );
        }

        return null;
    }
}
