<?php

namespace App\Controller\Admin;

use App\Entity\PledgedItem;
use App\Entity\SaleRequest;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;

class SaleRequestCrudController extends AbstractProtectedCrudController
{
    public static function getEntityFqcn(): string
    {
        return SaleRequest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Заявка на покупку изделия')
            ->setEntityLabelInPlural('Продажи изделий')
            ->setPageTitle(Crud::PAGE_INDEX, 'Заявки на покупку')
            ->setPageTitle(Crud::PAGE_NEW, 'Новая заявка')
            ->setPageTitle(Crud::PAGE_EDIT, 'Обработать заявку')
            ->setDefaultSort(['requestedAt' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        $saleRequest = null;
        $context = $this->getContext();
        if ($context !== null && $context->getCrud() !== null && $context->getEntity() !== null) {
            $saleRequest = $context->getEntity()->getInstance();
        }

        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('pledgedItem', 'Предмет');
        yield TextField::new('fullName', 'ФИО')->setRequired(true);
        yield TextField::new('phone', 'Телефон')->setRequired(true);

        if ($pageName !== Crud::PAGE_DETAIL || $saleRequest?->getEmail()) {
            yield EmailField::new('email', 'Email')->setRequired(false);
        }

        yield TextField::new('message', 'Комментарий')->hideOnForm()->onlyOnIndex();
        yield TextareaField::new('message', 'Комментарий')->hideOnForm()->onlyOnDetail();

        yield ChoiceField::new('status', 'Статус')
            ->setChoices([
                'Заявка' => SaleRequest::STATUS_REQUEST,
                'Продан' => SaleRequest::STATUS_SOLD,
                'Отменено' => SaleRequest::STATUS_CANCELLED,
            ]);

        if ($pageName !== Crud::PAGE_DETAIL || $saleRequest?->getSoldPrice() !== null) {
            yield MoneyField::new('soldPrice', 'Цена продажи')
                ->setCurrency('RUB')
                ->setStoredAsCents(false)
                ->setNumDecimals(2)
                ->setRequired(false);
        }

        yield DateTimeField::new('requestedAt', 'Дата заявки')
            ->setFormat('dd.MM.yyyy HH:mm')
            ->hideOnForm();

        if ($pageName !== Crud::PAGE_DETAIL || $saleRequest?->getProcessedAt() !== null) {
            yield DateTimeField::new('processedAt', 'Дата обработки')
                ->setFormat('dd.MM.yyyy HH:mm')
                ->hideOnForm();
        }
    }

    public function configureActions(Actions $actions): Actions
    {
        $approve = Action::new('approve', 'Одобрить', 'fa fa-check-circle')
            ->linkToCrudAction('approveAction')
            ->addCssClass('btn btn-success')
            ->displayIf(fn(SaleRequest $saleRequest) => $saleRequest->isRequest());

        $cancel = Action::new('cancel', 'Отменить', 'fa fa-ban')
            ->linkToCrudAction('cancelAction')
            ->addCssClass('btn btn-secondary')
            ->displayIf(fn(SaleRequest $saleRequest) => $saleRequest->getStatus() !== SaleRequest::STATUS_CANCELLED);

        return $actions
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $approve)
            ->add(Crud::PAGE_DETAIL, $cancel);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Статус')
                ->setChoices([
                    'Заявка' => SaleRequest::STATUS_REQUEST,
                    'Продан' => SaleRequest::STATUS_SOLD,
                    'Отменено' => SaleRequest::STATUS_CANCELLED,
                ]))
            ->add(EntityFilter::new('pledgedItem', 'Предмет'));
    }

    public function cancelAction(AdminContext $context, EntityManagerInterface $em): Response
    {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        $saleRequest = $entityId > 0 ? $em->find(SaleRequest::class, $entityId) : null;
        if (!$saleRequest instanceof SaleRequest) {
            $this->addFlash('danger', 'Заявка не найдена.');
            return $this->redirect($this->container->get(AdminUrlGenerator::class)
                ->setController(static::class)
                ->setAction(Action::INDEX)
                ->generateUrl());
        }

        if ($saleRequest->getStatus() !== SaleRequest::STATUS_CANCELLED) {
            $saleRequest->setStatus(SaleRequest::STATUS_CANCELLED);
            if ($saleRequest->getProcessedAt() === null) {
                $saleRequest->setProcessedAt(new \DateTime());
            }
            $em->persist($saleRequest);
            $em->flush();
            $this->addFlash('success', 'Заявка была отменена.');
        }

        return $this->redirect($this->container->get(AdminUrlGenerator::class)
            ->setController(static::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($saleRequest->getId())
            ->generateUrl());
    }

    public function approveAction(AdminContext $context, EntityManagerInterface $em, FormFactoryInterface $formFactory): Response
    {
        $entityId = (int) $context->getRequest()->query->get('entityId');
        $saleRequest = $entityId > 0 ? $em->find(SaleRequest::class, $entityId) : null;
        if (!$saleRequest instanceof SaleRequest) {
            $this->addFlash('danger', 'Заявка не найдена.');
            return $this->redirect($this->container->get(AdminUrlGenerator::class)
                ->setController(static::class)
                ->setAction(Action::INDEX)
                ->generateUrl());
        }

        if (!$saleRequest->isRequest()) {
            $this->addFlash('warning', 'Заявка уже обработана.');
            return $this->redirect($this->container->get(AdminUrlGenerator::class)
                ->setController(static::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($saleRequest->getId())
                ->generateUrl());
        }

        $form = $formFactory->createBuilder()
            ->add('soldPrice', MoneyType::class, [
                'label' => 'Цена продажи',
                'currency' => 'RUB',
                'scale' => 2,
                'required' => true,
                'attr' => ['min' => 0],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Комментарий для обработки',
                'required' => false,
            ])
            ->getForm();

        $form->handleRequest($context->getRequest());
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $saleRequest->setSoldPrice((string) $data['soldPrice']);
            $saleRequest->setStatus(SaleRequest::STATUS_SOLD);
            if ($saleRequest->getProcessedAt() === null) {
                $saleRequest->setProcessedAt(new \DateTime());
            }

            if ($saleRequest->getPledgedItem() !== null) {
                $item = $saleRequest->getPledgedItem();
                if (!$item->isSold()) {
                    $item->setStatus(PledgedItem::STATUS_SOLD);
                }
                $item->setSoldPrice($saleRequest->getSoldPrice());
            }

            if (!empty($data['notes'])) {
                $saleRequest->setMessage(trim(($saleRequest->getMessage() ?? '') . "\n[Одобрено] " . $data['notes']));
            }

            $em->persist($saleRequest);
            $em->flush();
            $this->addFlash('success', 'Заявка одобрена и товар отмечен как проданный.');

            return $this->redirect($this->container->get(AdminUrlGenerator::class)
                ->setController(static::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($saleRequest->getId())
                ->generateUrl());
        }

        return $this->render('admin/approve_sale_request.html.twig', [
            'saleRequest' => $saleRequest,
            'form' => $form->createView(),
        ]);
    }

    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof SaleRequest) {
            $this->syncSaleRequest($entityInstance);
        }

        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof SaleRequest) {
            $this->syncSaleRequest($entityInstance);
        }

        parent::updateEntity($em, $entityInstance);
    }

    private function syncSaleRequest(SaleRequest $saleRequest): void
    {
        if ($saleRequest->isSold()) {
            if ($saleRequest->getProcessedAt() === null) {
                $saleRequest->setProcessedAt(new \DateTime());
            }

            $item = $saleRequest->getPledgedItem();
            if ($item !== null) {
                if (!$item->isSold()) {
                    $item->setStatus(PledgedItem::STATUS_SOLD);
                }
                if ($saleRequest->getSoldPrice() !== null) {
                    $item->setSoldPrice($saleRequest->getSoldPrice());
                }
            }
        }
    }
}
