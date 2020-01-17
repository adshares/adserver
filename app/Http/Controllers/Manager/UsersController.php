<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function browse(Request $request): LengthAwarePaginator
    {
        $query = $request->get('q');
        if ($query) {
            $domains =
                DB::select(
                    'SELECT DISTINCT user_id from sites WHERE deleted_at IS NULL AND domain LIKE ? LIMIT 100',
                    ['%'.$query.'%']
                );
            $campaigns =
                DB::select(
                    'SELECT DISTINCT user_id from campaigns WHERE deleted_at IS NULL AND landing_url LIKE ? LIMIT 100',
                    ['%'.$query.'%']
                );

            $ids = array_unique(
                array_merge(
                    array_map(
                        function ($row) {
                            return $row->user_id;
                        },
                        $domains
                    ),
                    array_map(
                        function ($row) {
                            return $row->user_id;
                        },
                        $campaigns
                    )
                )
            );

            return User::where('email', 'LIKE', '%'.$query.'%')->orWhereIn('id', $ids)->paginate();
        }

        return User::paginate();
    }
}
