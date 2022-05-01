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
namespace ChenGang\ApiNote\Annotation;

use Hyperf\HttpServer\Annotation\Mapping as HyperfMapping;

abstract class Mapping extends HyperfMapping
{
    /**
     * 是否进行登录验证
     * @var bool
     */
    public $auth;
}
