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

declare(strict_types=1);

namespace Adshares\Adserver\Http\Response\Stats;

use Adshares\Adserver\Models\BidStrategy;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BidStrategySpreadsheetResponse
{
    private const COLUMNS = [
        'Id' => [
            'comment' => 'Id should not be changed',
            'fill' => 'E5F2FF',
            'color' => '003771',
        ],
        'Category' => [
            'comment' => 'Category should not be changed',
            'fill' => 'E5F2FF',
            'color' => '003771',
        ],
        'Value [%]' => [
            'comment' => 'Set value in range <0,100>%',
            'fill' => 'B8F4B5',
            'color' => '056100',
        ],
    ];

    private $bidStrategy;

    private $data;

    protected $creator;

    public function __construct(BidStrategy $bidStrategy, array $data, ?string $creator = null)
    {
        $this->bidStrategy = $bidStrategy;
        $this->data = $data;
        $this->creator = $creator;
    }

    public function responseStream(): StreamedResponse
    {
        $headers = $this->prepareHeaders();

        $file = function () {
            $this->generateXLSXFile();
        };

        return new StreamedResponse($file, StreamedResponse::HTTP_OK, $headers);
    }

    private function createFilename(): string
    {
        return sprintf(
            'bid_strategy_%s.xlsx',
            strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $this->bidStrategy->name))
        );
    }

    private function prepareHeaders(): array
    {
        $filename = $this->createFilename();

        return [
            'Access-Control-Expose-Headers' => 'Content-Disposition',
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename=' . $filename,
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];
    }

    private function generateXLSXFile(string $uri = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()
            ->setTitle($this->bidStrategy->name)
            ->setSubject($this->bidStrategy->name)
            ->setDescription('Adshares bid strategy: ' . $this->bidStrategy->name)
            ->setKeywords('adshares bid strategy')
            ->setCategory('Bid strategy');

        if ($this->creator !== null) {
            $spreadsheet->getProperties()->setCreator($this->creator)->setLastModifiedBy($this->creator);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $this->setupMainPage($sheet);

        foreach ($this->data as $page) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($page['label']);

            $x = 1;
            $y = 1;
            $fills = $colors = [];

            foreach ($this->columns() as $column => $prop) {
                $sheet->setCellValueByColumnAndRow($x, $y, $column);
                $headerStyle = $sheet->getStyleByColumnAndRow($x, $y);
                $headerStyle->getFont()->setBold(true);

                $sheet->getColumnDimensionByColumn($x)->setAutoSize(true);
                if (!empty($prop['comment'])) {
                    $sheet->getCommentByColumnAndRow($x, $y)->getText()->createTextRun($prop['comment']);
                }
                if (!empty($prop['fill'])) {
                    $fills[$x] = $prop['fill'];
                    $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fills[$x]);
                }
                if (!empty($prop['color'])) {
                    $colors[$x] = $prop['color'];
                    $headerStyle->getFont()->getColor()->setRGB($colors[$x]);
                }

                ++$x;
            }
            ++$y;

            foreach ($page['data'] as $row) {
                $x = 1;
                foreach ($row as $cell) {
                    $sheet->setCellValueByColumnAndRow($x, $y, $cell);

                    ++$x;
                }
                ++$y;
            }

            --$y;

            if ($y > 1) {
                $x = 1;
                foreach ($this->columns() as $column => $prop) {
                    $cellStyle = $sheet->getStyleByColumnAndRow($x, 2, $x, $y);

                    if (!empty($fills[$x])) {
                        $cellStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fills[$x]);
                    }
                    if (!empty($colors[$x])) {
                        $cellStyle->getFont()->getColor()->setRGB($colors[$x]);
                    }

                    ++$x;
                }
            }

            --$x;

            $sheet->getStyleByColumnAndRow(1, 1, $x, $y)->getBorders()->applyFromArray(
                [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF333333'],
                    ],
                ]
            );
            $sheet->getStyleByColumnAndRow(1, 1, $x, 1)->getBorders()->applyFromArray(
                [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_DOUBLE,
                        'color' => ['argb' => 'FF333333'],
                    ],
                ]
            );
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($uri);
    }

    private function columns(): array
    {
        return self::COLUMNS;
    }

    private function setupMainPage(Worksheet $sheet): void
    {
        $sheet->setTitle('Main');
        $data = [
            ['Name', $this->bidStrategy->name],
            ['Created', (new DateTimeImmutable())->format(DateTimeImmutable::ATOM)],
        ];

        $x = 1;
        $y = 1;

        foreach ($data as $row) {
            foreach ($row as $cellValue) {
                $sheet->setCellValueByColumnAndRow($x, $y, $cellValue);
                ++$x;
            }

            $x = 1;
            ++$y;
        }
    }
}
