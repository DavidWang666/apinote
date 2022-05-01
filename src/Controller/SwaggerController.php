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

namespace DavidWang\ApiNote\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpServer\Server;
use Hyperf\Utils\ApplicationContext;
use function Hyperf\ViewEngine\view;

/**
 * Class SwaggerController.
 */
class SwaggerController
{
    public function index(RequestInterface $request)
    {
        $version = $request->input('version', 2);
        $url = config('apinote.route.basePath', '') . config('apinote.route.json', '/swagger/json');
        return view('apinote::index' . $version, ['jsonUrl' => $url]);
    }

    /**
     * 获取json文件.
     */
    public function json(ResponseInterface $response)
    {
        $output = config('apinote.output_file');
        $container = ApplicationContext::getContainer();
        $name = $container->get(Server::class)->getServerName();
        $outputFile = str_replace('{server}', $name, $output);
        $data = json_decode(file_get_contents($outputFile));
        return $response->json($data);
    }
}
