<?php

namespace OneStudio\Otp\Facades;

use Illuminate\Support\Facades\Facade;
use OneStudio\Otp\OtpService;

class Otp extends Facade
{
    protected static function getFacadeAccessor()
    {
        return OtpService::class;
    }
}