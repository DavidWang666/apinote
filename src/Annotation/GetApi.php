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


/**
 * @Annotation
 * @Target({"METHOD"})
 */
class GetApi extends Mapping
{
    public $path;

    public $summary;

    public $description;

    public $deprecated;

    public $methods = ['GET'];

    public function __construct($value = null)
    {
        parent::__construct($value);
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $val;
                }
            }
        }
    }
}