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

return [
    // enable false 将不会生成 swagger 文件
    'enable' => env('APP_ENV') !== 'production',
    // swagger 配置的输出文件
    // 当你有多个 http server 时, 可以在输出文件的名称中增加 {server} 字面变量
    // 比如 /runtime/swagger/swagger_{server}.json
    'output_file' => BASE_PATH . '/runtime/swagger/json/swagger.json',
    // 忽略的hook, 非必须 用于忽略符合条件的接口, 将不会输出到上定义的文件中
    'ignore' => function ($controller, $action) {
        return false;
    },
    //是否启用接口验证 为true时将启用 DavidWang\ApiNote\Middleware\ApiValidationMiddleware 中间件
    'api_validate' => true,
    //接口文档路由
    'route' => [
        'basePath' => env('BASE_PATH'),
        'url' => '/swagger',
        'json' => '/swagger/json',
    ],

    //登录验证
    'auth' => [
        'enable' => true, //是否开启登录验证，默认开启
    ],

    //公共部分
    'common' => [
        //公共头部
        'headers' => [
            //默认头部
            'default' => [
                [
                    'name' => 'apiToken',
                    'description' => '用户token',
                    'required' => true,
                    'type' => 'string',
                    'default' => '',
                ],
            ],
            //单独服务头部 已服务名称为key
            //'http' => [
            //
            //]
        ],
    ],
    // swagger 的基础配置
    'swagger' => [
        //swagger 版本
        'swagger' => '2.0.0',
        'info' => [
            'description' => 'swagger api desc',
            'version' => '1.0.0',
            'title' => 'DavidWang API DOC',
        ],
        'host' => '',
        'schemes' => ['http'],
        'securityDefinitions' => [
            'api_key' => [
                'type' => 'apiKey',
                'name' => 'api_key',
                'in' => 'header',
            ],
        ],
    ],
    'templates' => [
        // // {template} 字面变量  替换 schema 内容
        // // 默认 成功 返回
        // 'success' => [
        //     "code|code" => '0',
        //     "result" => '{template}',
        //     "message|message" => 'Success',
        // ],
        // // 分页
        // 'page' => [
        //     "code|code" => '0',
        //     "result" => [
        //         'pageSize' => 10,
        //         'total' => 1,
        //         'totalPage' => 1,
        //         'list' => '{template}'
        //     ],
        //     "message|message" => 'Success',
        // ],
    ],
];
