<?php
declare(strict_types = 1);

namespace Adshares\Adserver\ViewModel;

use Adshares\Common\Application\Model\Selector;
use Illuminate\Contracts\Support\Arrayable;

class OptionsSelector implements Arrayable
{
    /** @var Selector */
    private $selector;

    public function __construct(Selector $selector)
    {
        $this->selector = $selector;
    }

    public function toArray(): array
    {
        return $this->selector->toArrayRecursiveWithoutEmptyFields();
    }
}
