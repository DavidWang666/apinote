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

use Hyperf\Utils\Arr;

/**
 * 需要id配置.
 * @Annotation
 * @Target({"METHOD"})
 */
class Story
{
    public $value;

    public function __construct($value = null)
    {
        $value = Arr::get($value, 'value');
        if (is_array($value)) {
            $this->value = implode(',', $value);
        } else {
            $this->value = $value;
        }
    }
}
