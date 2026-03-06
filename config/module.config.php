<?php declare(strict_types=1);

namespace OmekaRapper;

use Laminas\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'omeka-rapper' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/omeka-rapper[/:action]',
                            'defaults' => [
                                '__NAMESPACE__' => 'OmekaRapper\Controller',
                                'controller' => Controller\AssistController::class,
                                'action' => 'index',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'controllers' => [
        'factories' => [
            Controller\AssistController::class => Factory\AssistControllerFactory::class,
        ],
    ],

    'service_manager' => [
        'factories' => [
            Service\AiClientManager::class => Factory\AiClientManagerFactory::class,
        ],
    ],

    'view_manager' => [
        'template_path_stack' => [
            OMEKA_PATH . '/modules/OmekaRapper/view',
        ],
    ],
];