<?php
declare(strict_types = 1);

namespace Adshares\Common\Application\Factory;

use Adshares\Common\Application\Dto\Selector;
use Adshares\Common\Application\Dto\Taxonomy;
use Adshares\Common\Application\Dto\TaxonomyVersion0\Item;

final class SelectorFactory
{
    private $taxonomy;

    public function __construct(Taxonomy $taxonomy)
    {
        $this->taxonomy = $taxonomy;
    }

    public function toSelector(): Selector
    {
        return new Selector(...array_map(function (Item $item) {
            return $item->toSelectorOption();
        }, $this->taxonomy->toArray()));
    }
}
