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

namespace Adshares\Adserver\Http\Response\Stats;

use Symfony\Component\HttpFoundation\StreamedResponse;
use function fclose;
use function fopen;
use function fputcsv;

abstract class ReportResponse
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function response(): StreamedResponse
    {
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=report.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $csv = function () {
            $this->generateCSVFile();
        };

        return new StreamedResponse($csv, 200, $headers);
    }

    private function generateCSVFile(): void
    {
        $fp = fopen('php://output', 'wb');

        fputcsv($fp, $this->columns());

        foreach ($this->rows() as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);
    }

    abstract protected function columns(): array;

    abstract protected function rows(): array;
}
