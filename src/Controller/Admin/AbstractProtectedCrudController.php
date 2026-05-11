<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Base CRUD controller with safe delete protection.
 *
 * Subclasses override getDeletionBlockMessage() to return a human-readable
 * error string when an entity must not be deleted.  Returning null means
 * deletion is allowed and proceeds normally.
 *
 * On a block the controller adds a 'danger' flash message and redirects
 * back to the index page instead of crashing with an exception.
 */
abstract class AbstractProtectedCrudController extends AbstractCrudController
{
    /**
     * Return a non-empty string to block deletion and show it as an error.
     * Return null to allow deletion.
     */
    protected function getDeletionBlockMessage(mixed $entity): ?string
    {
        return null;
    }

    public function delete(AdminContext $context): \EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore|Response
    {
        $entity = $context->getEntity()->getInstance();
        $blockMessage = $this->getDeletionBlockMessage($entity);

        if ($blockMessage !== null) {
            $this->addFlash('danger', $blockMessage);

            /** @var AdminUrlGenerator $urlGenerator */
            $urlGenerator = $this->container->get(AdminUrlGenerator::class);
            $url = $urlGenerator
                ->setController(static::class)
                ->setAction(Action::INDEX)
                ->generateUrl();

            return $this->redirect($url);
        }

        return parent::delete($context);
    }
}
