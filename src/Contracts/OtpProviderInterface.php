<?php

declare(strict_types=1);

namespace OneStudio\Otp\Contracts;

interface OtpProviderInterface
{
    public function send(string $to, string $otp, ?string $message): bool;

    public function verify(string $phone, $otp): bool;
}