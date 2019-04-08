<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Client;

use Adshares\Common\Application\Dto\Taxonomy;
use Adshares\Common\Application\Factory\TaxonomyFactory;
use Adshares\Common\Application\Service\AdClassify;
use function GuzzleHttp\json_decode;

final class DummyAdClassifyClient implements AdClassify
{
    public function fetchFilteringOptions(): Taxonomy
    {
        return TaxonomyFactory::fromArray(json_decode($this->getData(), true));
    }

    private function getData(): string
    {
        return <<<JSON
{
  "data": [
    {
      "type": "dict",
      "label": "Content Type",
      "key": "type",
      "list": [
        {
          "label": "HTML",
          "value": "html"
        },
        {
          "label": "Image",
          "value": "image"
        }
      ]
    }
  ]
}
JSON;
    }
}
