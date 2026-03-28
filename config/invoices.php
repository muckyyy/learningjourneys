<?php

return [
    'date' => [

        /*
         * Carbon date format — Swiss standard: dd.mm.YYYY
         */
        'format' => 'd.m.Y',

        /*
         * Due date for payment since invoice's date.
         */
        'pay_until_days' => 30,
    ],

    'serial_number' => [
        'series'   => 'INV',
        'sequence' => 1,

        /*
         * Sequence will be padded accordingly, for ex. 00001
         */
        'sequence_padding' => 5,
        'delimiter'        => '-',

        /*
         * Supported tags {SERIES}, {DELIMITER}, {SEQUENCE}
         * Example: INV-00001
         */
        'format' => '{SERIES}{DELIMITER}{SEQUENCE}',
    ],

    'currency' => [
        'code' => 'CHF',

        /*
         * Usually cents — Swiss German: Rp. (Rappen)
         */
        'fraction' => 'Rp.',
        'symbol'   => 'CHF',

        /*
         * Example: 19.00
         */
        'decimals' => 2,

        /*
         * Example: 1.99
         */
        'decimal_point' => '.',

        /*
         * Swiss thousands separator is an apostrophe.
         * Example: 1'999.00
         */
        'thousands_separator' => "'",

        /*
         * Supported tags {VALUE}, {SYMBOL}, {CODE}
         * Example: CHF 1'999.00
         */
        'format' => '{SYMBOL} {VALUE}',
    ],

    'paper' => [
        // A4 = 210 mm x 297 mm = 595 pt x 842 pt
        'size'        => 'a4',
        'orientation' => 'portrait',
    ],

    'disk' => 's3',

    'seller' => [
        /*
         * Class used in templates via $invoice->seller
         *
         * Must implement LaravelDaily\Invoices\Contracts\PartyContract
         *      or extend LaravelDaily\Invoices\Classes\Party
         */
        'class' => \LaravelDaily\Invoices\Classes\Seller::class,

        /*
         * Default attributes for Seller::class
         *
         * Update these with your real Swiss company details.
         */
        'attributes' => [
            'name'          => env('INVOICE_SELLER_NAME', 'Your Company GmbH'),
            'address'       => env('INVOICE_SELLER_ADDRESS', 'Bahnhofstrasse 1, 8001 Zürich, Switzerland'),
            'phone'         => env('INVOICE_SELLER_PHONE', '+41 44 000 00 00'),
            'custom_fields' => [
                'UID'   => env('INVOICE_SELLER_UID', 'CHE-000.000.000 MWST'),
                'IBAN'  => env('INVOICE_SELLER_IBAN', 'CH00 0000 0000 0000 0000 0'),
            ],
        ],
    ],

    'dompdf_options' => [
        'enable_php' => true,
        /**
         * Do not write log.html or make it optional
         *
         *  @see https://github.com/dompdf/dompdf/issues/2810
         */
        'logOutputFile' => '',
    ],
];
