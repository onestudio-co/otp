<?php

declare(strict_types=1);

namespace OneStudio\Otp;

use Illuminate\Support\Manager;
use OneStudio\Otp\Providers\TwilioProvider;
use OneStudio\Otp\Providers\UnifonicProvider;

class OtpManager extends Manager
{
    public function getDefaultDriver()
    {
        return $this->config->get('otp.default');
    }

    protected function createTwilioDriver()
    {
        $config = $this->config->get('otp.providers.twilio');
        return new TwilioProvider($config);
    }

    protected function createUnifonicDriver()
    {
        $config = $this->config->get('otp.providers.unifonic');
        return new UnifonicProvider($config);
    }
}
