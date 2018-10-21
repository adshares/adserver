<?php
/**
 * Created by PhpStorm.
 * User: box
 * Date: 10/21/18
 * Time: 7:08 PM
 */

namespace Lib\Entity;

use Lib\Id;

trait Entity
{
    private $id;

    public function id(): Id
    {
        return $this->id;
    }
}
