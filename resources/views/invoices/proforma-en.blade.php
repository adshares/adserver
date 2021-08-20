<html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 0.9em;
            }
            section {
                margin-bottom: 50px;
            }
            table {
                border-spacing: 0;
                border-collapse: collapse;
                width: 100%;
                font-size: 0.9em;
            }
            .justify > div {
                width: 48%;
                float: right;
                vertical-align: top;
            }
            .justify > div:first-of-type {
                float: left;
            }
            .clear {
                clear: both;
            }
            .header {
                margin-bottom: 20px;
            }
            .header .number {
                width: 80%;
            }
            .header .date {
                width: 20%;
                text-align: right;
                margin-top: 10px;
            }
            .address h2 {
                border-bottom: 1px solid #ccc;
                font-size: 1.1em;
                font-weight: normal;
            }
            .items th, .items td {
                border: 1px solid #ccc;
                padding: 5px 10px;
            }
            .items td {
                text-align: right;
            }
            .items .label {
                text-align: left;
            }
            .payment th, .payment td {
                padding: 5px 10px;
                border: 1px solid #ccc;
                border-left: none;
                border-right: none;
            }
            .payment th {
                font-weight: normal;
                text-align: left;
                vertical-align: top;
                width: 30%;
            }
            .payment td {
                text-align: right;
            }
            .summary div {
                border-bottom: 1px solid #ccc;
                padding: 5px 10px;
            }
            .summary div:first-of-type {
                border-top: 1px solid #ccc;
            }
            .summary strong {
                font-size: 1.5em;
            }
            .comments {
                border: 1px solid #ccc;
                border-left: none;
                border-right: none;
                padding: 5px 10px;
            }
        </style>
    </head>
    <body>
        <section class="header">
            <div class="justify">
                <div class="number">
                    <img alt="{{ $invoice->seller_name }}"
                         src="data:image/png;base64,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" />
                    <h1>Proforma invoice {{ $invoice->number }}</h1>
                </div>
                <div class="date">
                    Issue date: <b>@date($invoice->issue_date)</b>
                </div>
            </div>
            <div class="clear"></div>
        </section>
        <section class="addresses">
            <div class="justify">
                <div class="address">
                    <h2>Seller</h2>
                    <b>{{ $invoice->seller_name }}</b><br/>
                    <b>{{ $invoice->seller_address }}</b><br/>
                    @if ($invoice->seller_postal_code)<b>{{ $invoice->seller_postal_code }}</b>@endif
                    @if ($invoice->seller_city)<b>{{ $invoice->seller_city }}</b>@endif
                    @if ($invoice->seller_postal_code || $invoice->seller_city)<br/>@endif
                    <b>{{ $invoice->seller_country_name }}</b><br/>
                    VAT Reg No {{ $invoice->seller_vat_id }}
                </div>
                <div class="address">
                    <h2>Buyer</h2>
                    <b>{{ $invoice->buyer_name }}</b><br/>
                    <b>{{ $invoice->buyer_address }}</b><br/>
                    @if ($invoice->buyer_postal_code)<b>{{ $invoice->buyer_postal_code }}</b>@endif
                    @if ($invoice->buyer_city)<b>{{ $invoice->buyer_city }}</b>@endif
                    @if ($invoice->buyer_postal_code || $invoice->buyer_city)<br/>@endif
                    <b>{{ $invoice->buyer_country_name }}</b><br/>
                    VAT Reg No {{ $invoice->buyer_vat_id }}
                </div>
            </div>
            <div class="clear"></div>
        </section>
        <section class="items">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Product</th>
                        <th>Total net </th>
                        <th>VAT (%)</th>
                        <th>VAT amount</th>
                        <th>Total gross</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td class="label">Online advertising in Adshares network</td>
                        <td>@money($invoice->net_amount)</td>
                        <td>{{ $invoice->vat_rate }}</td>
                        <td>@money($invoice->vat_amount)</td>
                        <td>@money($invoice->gross_amount)</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="label" colspan="2">Total amount</td>
                        <td>@money($invoice->net_amount)</td>
                        <td>-</td>
                        <td>@money($invoice->vat_amount)</td>
                        <td>@money($invoice->gross_amount)</td>
                    </tr>
                    <tr>
                        <td class="label" colspan="2">Total incl. VAT</td>
                        <td>@money($invoice->net_amount)</td>
                        <td>{{ $invoice->vat_rate }}</td>
                        <td>@money($invoice->vat_amount)</td>
                        <td>@money($invoice->gross_amount)</td>
                    </tr>
                </tfoot>
            </table>
        </section>
        <section class="info">
            <div class="justify">
                <div class="payment">
                    <table>
                        <tr>
                            <th>Payment type</th>
                            <td><b>Transfer</b></td>
                        </tr>
                        <tr>
                            <th>Bank account number</th>
                            <td>
                                <b>{{ $invoice->bank_account['number'] }}</b><br/>
                                <b>{{ $invoice->bank_account['name'] }}</b>
                            </td>
                        </tr>
                        <tr>
                            <th>Due date</th>
                            <td><b>@date($invoice->due_date)</b></td>
                        </tr>
                        <tr>
                            <th>Paid</th>
                            <td><b>@money(0) {{ $invoice->currency }}</b></td>
                        </tr>
                        <tr>
                            <th>Amount due</th>
                            <td><b>@money($invoice->gross_amount) {{ $invoice->currency }}</b></td>
                        </tr>
                    </table>
                </div>
                <div class="summary">
                    <div>
                        <strong>Total amount: @money($invoice->gross_amount) {{ $invoice->currency }}</strong>
                    </div>
                    <div>
                        In words: @spellout($invoice->gross_amount) {{ $invoice->currency }}
                    </div>
                </div>
            </div>
            <div class="clear"></div>
        </section>
        @if ($invoice->comments)
        <section class="comments">
            Comments: {{ $invoice->comments }}
        </section>
        @endif
    </body>
</html>
