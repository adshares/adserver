<?php
declare(strict_types = 1);

namespace Adshares\Common\Application\Factory;

use Adshares\Common\Application\Dto\Selector;
use Adshares\Common\Application\Dto\Taxonomy;
use Adshares\Common\Application\Dto\Taxonomy\Item;

final class SelectorFactory
{

    private function __construct()
    {
    }

    public static function fromTaxonomy(Taxonomy $taxonomy): Selector
    {
        return new Selector(...array_map(function (Item $item) {
            return $item->toSelectorOption();
        }, $taxonomy->toArray()));
    }
}
