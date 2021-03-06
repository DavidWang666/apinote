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
namespace DavidWang\ApiNote\Middleware;

use DavidWang\ApiNote\Exception\ApiNoteException;
use DavidWang\ApiNote\Validation\ValidationApi;
use FastRoute\Dispatcher;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\CoreMiddleware;
use Hyperf\HttpServer\Router\Dispatched;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApiValidationMiddleware extends CoreMiddleware
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var HttpResponse
     */
    protected $response;

    protected $validationApi;

    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request, ValidationApi $validation)
    {
        $this->container = $container;
        $this->response = $response;
        $this->request = $request;
        $this->validationApi = $validation;
        parent::__construct($container, 'http');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Dispatched $dispatched */
        $dispatched = $request->getAttribute(Dispatched::class);
        if ($dispatched->status !== Dispatcher::FOUND) {
            return $handler->handle($request);
        }

        // do not check Closure
        if ($dispatched->handler->callback instanceof \Closure) {
            return $handler->handle($request);
        }

        [$controller, $action] = $this->prepareHandler($dispatched->handler->callback);

        $result = $this->validationApi->validated($controller, $action);
        if ($result !== true) {
            $config = $this->container->get(ConfigInterface::class);
            $exceptionEnable = $config->get('apinote.exception_enable', false);
            if ($exceptionEnable) {
                $fieldErrorMessage = $config->get('apinote.field_error_message', 'message');
                throw new ApiNoteException($result[$fieldErrorMessage]);
            }
            $httpStatusCode = $config->get('apinote.http_status_code', 400);
            return $this->response->json($result)->withStatus($httpStatusCode);
        }

        return $handler->handle($request);
    }
}
