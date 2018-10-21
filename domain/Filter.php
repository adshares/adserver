<?php
/**
 * Created by PhpStorm.
 * User: box
 * Date: 10/21/18
 * Time: 5:18 PM
 */
declare(strict_types=1);

namespace AdServer;

final class Filter
{
    /** @var Filter\Type */
    private $type;
    /** @var Filter\Key */
    private $key;
    /** @var Filter\Value */
    private $value;

    public function __construct(Filter\Type $type, Filter\Key $key, Filter\Value $value)
    {
        $this->type = $type;
        $this->key = $key;
        $this->value = $value;
    }
}
