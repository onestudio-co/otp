<?php

declare(strict_types=1);

namespace OneStudio\Otp\Providers;

use Twilio\Rest\Client;
use OneStudio\Otp\Contracts\OtpProviderInterface;

class TwilioProvider implements OtpProviderInterface
{
    protected $client;
    protected $from;

    public function __construct(array $config)
    {
        $this->client = new Client($config['account_sid'], $config['auth_token']);
        $this->from = $config['from'];
    }

    public function send(string $phone, string $message): bool
    {
        try {
            $this->client->messages->create($phone, [
                'from' => $this->from,
                'body' => $message
            ]);
            return true;
        } catch (\Exception $e) {
            error_log('Twilio OTP Error: ' . $e->getMessage());
            return false;
        }
    }

    public function verify(string $phone, $otp): bool
    {
        return true;
    }
}
