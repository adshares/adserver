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

declare(strict_types=1);

namespace Adshares\Adserver\Http\Response\Stats;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class ReportResponse
{
    protected $data;
    protected $name;
    protected $creator;

    public function __construct(array $data, ?string $name = null, ?string $creator = null)
    {
        if ($name === null) {
            $name = 'Report';
        }

        $this->data = $data;
        $this->name = $name;
        $this->creator = $creator;
    }

    public function response(): StreamedResponse
    {
        $filename = sprintf(
            'report_%s.xlsx',
            strtolower(preg_replace('[^a-zA-Z0-9_-]', '_', $this->name))
        );

        $headers = [
            'Content-type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename=' . $filename,
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $file = function () {
            $this->generateXLSXFile();
        };

        return new StreamedResponse($file, 200, $headers);
    }

    private function generateXLSXFile(): void
    {
        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()
            ->setTitle($this->name)
            ->setSubject($this->name)
            ->setDescription('Adshares report: ' . $this->name)
            ->setKeywords('adshares report')
            ->setCategory('Report');

        if ($this->creator !== null) {
            $spreadsheet->getProperties()
                ->setCreator($this->creator)
                ->setLastModifiedBy($this->creator);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->name);

        $x = ord('A');
        $y = 1;
        $formats = $fills = $colors = [];

        foreach ($this->columns() as $column => $prop) {
            $coordinate = chr($x) . $y;
            $sheet->setCellValue($coordinate, $column);
            $sheet->getStyle($coordinate)->getFont()->setBold(true);

            if (!empty($prop['width'])) {
                $sheet->getColumnDimension(chr($x))->setWidth($prop['width']);
            } else {
                $sheet->getColumnDimension(chr($x))->setAutoSize(true);
            }
            if (!empty($prop['comment'])) {
                $sheet->getComment($coordinate)->getText()->createTextRun($prop['comment']);
            }
            if (!empty($prop['format'])) {
                $formats[$x] = $prop['format'];
            }
            if (!empty($prop['fill'])) {
                $fills[$x] = $prop['fill'];
                $sheet->getStyle($coordinate)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($fills[$x]);
            }
            if (!empty($prop['color'])) {
                $colors[$x] = $prop['color'];
                $sheet->getStyle($coordinate)->getFont()->getColor()->setRGB($colors[$x]);
            }

            ++$x;
        }
        ++$y;

        foreach ($this->rows() as $row) {
            $x = ord('A');
            foreach ($row as $cell) {
                $coordinate = chr($x) . $y;
                $sheet->setCellValue($coordinate, $cell);

                if (!empty($formats[$x])) {
                    $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode($formats[$x]);
                }
                if (!empty($fills[$x])) {
                    $sheet->getStyle($coordinate)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($fills[$x]);
                }
                if (!empty($colors[$x])) {
                    $sheet->getStyle($coordinate)->getFont()->getColor()->setRGB($colors[$x]);
                }

                ++$x;
            }
            ++$y;
        }

        --$x;
        --$y;

        $sheet->getStyle(sprintf('A1:%s%d', chr($x), $y))->getBorders()->applyFromArray([
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF333333'],
            ]
        ]);
        $sheet->getStyle(sprintf('A1:%s1', chr($x)))->getBorders()->applyFromArray([
            'bottom' => [
                'borderStyle' => Border::BORDER_DOUBLE,
                'color' => ['argb' => 'FF333333'],
            ]
        ]);


        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    abstract protected function columns(): array;

    abstract protected function rows(): array;
}
