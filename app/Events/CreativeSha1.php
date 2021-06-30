<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Events;

use Adshares\Adserver\Models\Banner;
use Adshares\Lib\DOMDocumentSafe;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreativeSha1
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(Banner $model)
    {
        if ($model->creative_sha1 !== ($sha1 = sha1($model->creative_contents))) {
            $model->creative_sha1 = $sha1;
            $model->cdn_url = null;
            $model->classifications()->delete();
        }
    }
}
