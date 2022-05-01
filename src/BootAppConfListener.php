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

namespace ChenGang\ApiNote;

use ChenGang\ApiNote\Swagger\SwaggerJson;
use ChenGang\ApiNote\Controller\SwaggerController;
use Closure;
use Exception;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Str;
use RuntimeException;

class BootAppConfListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event)
    {
        $container = ApplicationContext::getContainer();
        $logger = $container->get(LoggerFactory::class)->get('apinote');
        $config = $container->get(ConfigInterface::class);
        if (!$config->get('apinote.enable')) {
            $logger->debug('apinote not enable');
            return;
        }
        $output = $config->get('apinote.output_file');
        if (!$output) {
            $logger->error('/config/autoload/apinote.php need set output_file');
            return;
        }
        $servers = $config->get('server.servers');
        if (count($servers) > 1 && !Str::contains($output, '{server}')) {
            $logger->warning('You have multiple serve, but your apinote.output_file not contains {server} var');
        }
        foreach ($servers as $server) {
            $router = $container->get(DispatcherFactory::class)->getRouter($server['name']);
            $basePath  = config('apinote.route.basePath','/');
            $router->get($basePath . config('apinote.route.url', 'swagger'), [SwaggerController::class, 'index']);
            $router->get($basePath . config('apinote.route.json', 'swagger/json'), [SwaggerController::class, 'json']);
            $data = $router->getData();
            $swagger = new SwaggerJson($server['name']);

            $ignore = $config->get('apinote.ignore', function ($controller, $action) {
                return false;
            });

            array_walk_recursive($data, function ($item) use ($swagger, $ignore, $logger) {
                if ($item instanceof Handler && !($item->callback instanceof Closure)) {
                    [$controller, $action] = $this->prepareHandler($item->callback);
                    try {
                        (!$ignore($controller, $action)) && $swagger->addPath($controller, $action);
                    } catch (Exception $e) {
                        $logger->warning($e->getMessage());
                    }
                }
            });

            $swagger->save();
        }
    }

    protected function prepareHandler($handler): array
    {
        if (is_string($handler)) {
            if (strpos($handler, '@') !== false) {
                return explode('@', $handler);
            }
            return explode('::', $handler);
        }
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            return $handler;
        }
        throw new RuntimeException('Handler not exist.');
    }
}
