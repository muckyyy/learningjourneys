<?php

namespace App\Exceptions;

use RuntimeException;

class VirtualVendorDisabledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Virtual Vendor is disabled. Enable it before simulating purchases.');
    }
}
