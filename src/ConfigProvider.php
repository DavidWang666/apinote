<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace DavidWang\ApiNote;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'commands' => [
            ],

            'dependencies' => [
                \Hyperf\HttpServer\Router\DispatcherFactory::class => DispatcherFactory::class,
            ],
            'listeners' => [
                BootAppConfListener::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for apinote.',
                    'source' => __DIR__ . '/../publish/apinote.php',
                    'destination' => BASE_PATH . '/config/autoload/apinote.php',
                ],
                [
                    'id' => 'resource2',
                    'description' => 'The view for apinote.',
                    'source' => __DIR__ . '/../publish/resource/two/',
                    'destination' => BASE_PATH . '/public/swagger/two/',
                ],
                [
                    'id' => 'resource3',
                    'description' => 'The view for apinote.',
                    'source' => __DIR__ . '/../publish/resource/three/',
                    'destination' => BASE_PATH . '/public/swagger/three/',
                ],
            ],
            'view' => [
                // ...others config
                'namespaces' => [
                    'apinote' => __DIR__ . '/../views',
                ],
            ],
        ];
    }
}
