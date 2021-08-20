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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Mail\InvoiceCreated;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

use function Psy\debug;

class InvoicesController extends Controller
{
    public function browse(): JsonResponse
    {
        if (!Config::isTrueOnly(Config::INVOICE_ENABLED)) {
            throw new NotFoundHttpException();
        }

        return self::json(
            Invoice::where('user_id', Auth::user()->id)
                ->get()
                ->sortByDesc('id')
                ->values()
                ->toArray()
        );
    }

    public function add(Request $request): JsonResponse
    {
        if (!Config::isTrueOnly(Config::INVOICE_ENABLED)) {
            throw new NotFoundHttpException();
        }

        $user = Auth::user();

        if (null === ($input = $request->input('invoice'))) {
            $input = [];
        }

        $input['user_id'] = $user->id;
        Validator::make($input, Invoice::$rules)->validate();
        if (!in_array($input['currency'] ?? '', explode(',', Config::fetchStringOrFail(Config::INVOICE_CURRENCIES)))) {
            throw new UnprocessableEntityHttpException('Unsupported currency');
        }
        $invoice = Invoice::createProforma($input);

        $mail = new InvoiceCreated($invoice);
        $mail->attach($invoice->pdf_file);
        Mail::to($user)->bcc(config('app.adshares_operator_email'))->queue($mail);

        return self::json($invoice, Response::HTTP_CREATED);
    }

    public function download(string $uuid): BinaryFileResponse
    {
        if (!Utils::isUuidValid($uuid) || null === ($invoice = Invoice::fetchByPublicId($uuid))) {
            throw new NotFoundHttpException(sprintf('Cannot find invoice %s', $uuid));
        }
        return response()->file($invoice->pdf_file);
    }
}
