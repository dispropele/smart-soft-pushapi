<?php

namespace App\Controller\Admin;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Response;

class AdminCrudController extends AbstractCrudController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em
    ) {}

    public static function getEntityFqcn(): string
    {
        return Admin::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Администратор')
            ->setEntityLabelInPlural('Администраторы')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('username', 'Логин')
            ->setRequired(true);

        yield EmailField::new('email', 'Email')
            ->setRequired(true);

        yield Field::new('plainPassword', 'Пароль')
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Пароль'],
                'second_options' => ['label' => 'Подтверждение пароля'],
                'mapped' => false,
                'required' => $pageName === Crud::PAGE_NEW,
            ])
            ->onlyOnForms();

        yield DateTimeField::new('createdAt', 'Создан')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        parent::updateEntity($em, $entityInstance);
    }

    private function hashPassword(Admin $admin): void
    {
        $plainPassword = $this->getContext()?->getRequest()?->request->all('Admin')['plainPassword']['first'] ?? null;

        if (!empty($plainPassword)) {
            $hashed = $this->passwordHasher->hashPassword($admin, $plainPassword);
            $admin->setPasswordHash($hashed);
        }
    }
}
