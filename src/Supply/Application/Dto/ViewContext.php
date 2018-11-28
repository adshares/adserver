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

namespace Adshares\Supply\Application\Dto;

final class ViewContext
{
    public function toArray(): array
    {
        return array_map(function (array $param) {
            if (isset($param['keywords']) && empty($param['keywords'])) {
                unset($param['keywords']);
            }

            return $param;
        }, $this->params());
    }

    private function params(): array
    {
        return json_decode(<<<JSON
[{
            "banner_filters": {
                "require": [],
                "exclude": []
            },
            "keywords": {},
            "banner_size": "300x300",
            "publisher_id": "321",
            "request_id": 123,
            "user_id": "uid"
        },
        {
            "banner_filters": {
                "require": [],
                "exclude": []
            },
            "keywords": {},
            "banner_size": "150x150",
            "publisher_id": "248",
            "request_id": 842,
            "user_id": "uid"
        }]
JSON
            , true);
    }
}
