<?php
/**
 * Created by PhpStorm.
 * User: box
 * Date: 10/21/18
 * Time: 8:39 PM
 */

namespace AdServer\Demand\Campaign\Banner;

final class Dimensions
{
    /** @var int */
    private $width;
    /** @var int */
    private $height;

    public function __construct(int $width, int $height)
    {
        $this->width = $width;
        $this->height = $height;
    }
}
