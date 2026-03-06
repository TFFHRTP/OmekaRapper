<?php declare(strict_types=1);

namespace OmekaRapper;

use Omeka\Module\AbstractModule;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;

class Module extends AbstractModule
{
    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $shared = $event->getApplication()->getEventManager()->getSharedManager();
        $this->attachListeners($shared);
    }

    private function attachListeners(SharedEventManagerInterface $shared): void
    {
        // Inject the panel on admin item add/edit pages.
        $shared->attach(
            \Omeka\Controller\Admin\ItemController::class,
            'view.add.after',
            [$this, 'injectPanel']
        );

        $shared->attach(
            \Omeka\Controller\Admin\ItemController::class,
            'view.edit.after',
            [$this, 'injectPanel']
        );
    }

    public function injectPanel($event): void
    {
        $view = $event->getTarget();

        // Load JS
        $view->headScript()->appendFile(
            $view->assetUrl('js/omeka-rapper.js', 'OmekaRapper')
        );

        // Render panel
        echo $view->partial('omeka-rapper/admin/assist/panel');
    }
}