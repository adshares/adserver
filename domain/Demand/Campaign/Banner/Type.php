<?php
/**
 * Created by PhpStorm.
 * User: box
 * Date: 10/21/18
 * Time: 7:51 PM
 */
declare(strict_types=1);

namespace AdServer\Demand\Campaign\Banner;

use Lib\Enum;

final class Type implements Enum
{
    public const IMAGE = 'image';
    public const HTML = 'html';
    public const ALLOWED_VALUES = [self::IMAGE, self::HTML];
    use Enum\Enum;
}
