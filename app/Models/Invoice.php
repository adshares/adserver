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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\Ownership;
use Adshares\Adserver\Utilities\InvoiceUtils;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use mikehaertl\wkhtmlto\Pdf;
use RuntimeException;
use Symfony\Component\Intl\Countries;
use Throwable;

/**
 * @property int id
 * @property string uuid
 * @property int user_id
 * @property User user
 * @property string type
 * @property string number
 * @property Carbon issue_date
 * @property Carbon due_date
 * @property string seller_name
 * @property string seller_address
 * @property string seller_postal_code
 * @property string seller_city
 * @property string seller_country
 * @property string seller_vat_id
 * @property string buyer_name
 * @property string buyer_address
 * @property string buyer_postal_code
 * @property string buyer_city
 * @property string buyer_country
 * @property string buyer_vat_id
 * @property string currency
 * @property float net_amount
 * @property float gross_amount
 * @property float vat_amount
 * @property string vat_rate
 * @property string comments
 * @property string html_output
 * @property string pdf_file
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property ?Carbon deleted_at
 * @property string download_url
 */
class Invoice extends Model
{
    use AutomateMutators;
    use SoftDeletes;
    use BinHex;

    public const TYPE_PROFORMA = 'proforma';

    public const DEFAULT_DUE_DAYS = 7;

    protected $fillable = [
        'buyer_name',
        'buyer_address',
        'buyer_postal_code',
        'buyer_city',
        'buyer_country',
        'buyer_vat_id',
        'currency',
        'net_amount',
        'comments',
    ];

    public static array $rules = [
        'buyer_name' => 'required|max:256',
        'buyer_address' => 'required|max:256',
        'buyer_postal_code' => 'max:16',
        'buyer_city' => 'max:128',
        'buyer_country' => 'required|min:2|max:2',
        'buyer_vat_id' => 'required|max:32',
        'currency' => 'required|min:3|max:3',
        'net_amount' => 'numeric|min:0',
        'comments' => 'max:256',
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'net_amount' => 'float',
        'gross_amount' => 'float',
        'vat_amount' => 'float',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    protected $dates = [
        'issue_date',
        'due_date',
    ];

    protected $appends = [
        'download_url',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSellerCountryNameAttribute(): string
    {
        return Countries::getName($this->seller_country);
    }

    public function getBuyerCountryNameAttribute(): string
    {
        return Countries::getName($this->buyer_country);
    }

    public function getBankAccountAttribute(): array
    {
        $accounts = Config::fetchJsonOrFail(Config::INVOICE_COMPANY_BANK_ACCOUNTS);
        if (!array_key_exists($this->currency, $accounts)) {
            return [
                'number' => null,
                'name' => null,
            ];
        }
        return $accounts[$this->currency];
    }

    public function getPdfFileAttribute(): string
    {
        $disk = Storage::disk('local');
        $directory = sprintf('invoices/%s', $this->issue_date->format('Y-m'));
        $path = sprintf('%s/%s.pdf', $directory, $this->uuid);
        if (!$disk->exists($path)) {
            $disk->makeDirectory($directory);
            $pdf = new Pdf($this->html_output);
            if (!$pdf->saveAs($disk->path($path))) {
                throw new RuntimeException(
                    sprintf('Error during creating PDF for %s: %s', $this->uuid, $pdf->getError())
                );
            }
        }
        return $disk->path($path);
    }

    public function getDownloadUrlAttribute(): string
    {
        return (new SecureUrl(route('invoices.download', ['invoice_uuid' => $this->uuid])))->toString();
    }

    protected function renderHtml(): string
    {
        return view('invoices/proforma-en', ['invoice' => $this])->render();
    }

    public static function getNextSequence(string $type, DateTimeInterface $date): int
    {
        return self::withTrashed()
                ->where('type', $type)
                ->whereYear('issue_date', (int)$date->format('Y'))
                ->whereMonth('issue_date', (int)$date->format('n'))
                ->count() + 1;
    }

    public static function fetchByPublicId(string $publicId): ?self
    {
        return self::where('uuid', hex2bin($publicId))->first();
    }

    public static function createProforma(array $input = []): self
    {
        $settings = Config::fetchAdminSettings();

        $proforma = new self();
        $proforma->user_id = (int)$input['user_id'];
        $proforma->type = self::TYPE_PROFORMA;
        $proforma->issue_date = now()->startOfDay();
        $proforma->due_date = $proforma->issue_date->copy()->addDays(self::DEFAULT_DUE_DAYS);

        $proforma->seller_name = $settings[Config::INVOICE_COMPANY_NAME];
        $proforma->seller_address = $settings[Config::INVOICE_COMPANY_ADDRESS];
        $proforma->seller_postal_code = $settings[Config::INVOICE_COMPANY_POSTAL_CODE];
        $proforma->seller_city = $settings[Config::INVOICE_COMPANY_CITY];
        $proforma->seller_country = $settings[Config::INVOICE_COMPANY_COUNTRY];
        $proforma->seller_vat_id = $settings[Config::INVOICE_COMPANY_VAT_ID];

        $proforma->buyer_name = $input['buyer_name'];
        $proforma->buyer_address = $input['buyer_address'];
        $proforma->buyer_postal_code = $input['buyer_postal_code'] ?? null;
        $proforma->buyer_city = $input['buyer_city'] ?? null;
        $proforma->buyer_country = $input['buyer_country'];
        $proforma->buyer_vat_id = $input['buyer_vat_id'];
        $proforma->currency = $input['currency'];
        $proforma->net_amount = (float)$input['net_amount'];
        $proforma->comments = $input['comments'] ?? null;

        $vatRate = InvoiceUtils::getVatRate($proforma->buyer_country, $input['eu_vat'] ?? false);
        $proforma->vat_rate = array_keys($vatRate)[0];
        $proforma->vat_amount = reset($vatRate) * $proforma->net_amount;
        $proforma->gross_amount = $proforma->net_amount + $proforma->vat_amount;

        DB::beginTransaction();
        try {
            $proforma->number = InvoiceUtils::formatNumber(
                $settings[Config::INVOICE_NUMBER_FORMAT],
                self::getNextSequence(self::TYPE_PROFORMA, $proforma->issue_date),
                $proforma->issue_date,
                config('app.adserver_id')
            );
            $proforma->html_output = $proforma->renderHtml();
            $proforma->saveOrFail();
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return $proforma;
    }
}
