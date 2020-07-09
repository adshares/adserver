<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Services\Advertiser;

use Adshares\Adserver\Services\Advertiser\Dto\TextAdSource;

class TextAdsGenerator
{
    /** @var TextAdSource */
    private $source;

    public function __construct(TextAdSource $source)
    {
        $this->source = $source;
    }

    public function generate(string $size): string
    {
        $content = <<<HTML
<!DOCTYPE html>
<head>
  <meta content="utf-8">
  <title>Adshares</title>
</head>
<body>
<h1>:title</h1>
<div>:text</div>
</body>
HTML;

        return str_replace([':title', ':text'], [$this->source->getTitle(), $this->source->getText()], $content);
    }
}
