<?php
/**
 * Created by PhpStorm.
 * User: box
 * Date: 10/21/18
 * Time: 5:18 PM
 */
declare(strict_types=1);


namespace Lib\Filter;

trait FilterTrait
{
    /** @var Type */
    private $type;
    /** @var Key */
    private $key;
    /** @var Value */
    private $value;

    public function __construct(Type $type, Key $key, Value $value)
    {
        $this->type = $type;
        $this->key = $key;
        $this->value = $value;
    }
}
