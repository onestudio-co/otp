<?php

declare(strict_types=1);

namespace OneStudio\Otp\Providers;

use GuzzleHttp\Client;
use OneStudio\Otp\Contracts\OtpProviderInterface;

class UnifonicProvider implements OtpProviderInterface
{
    protected $client;
    protected $appSid;
    protected $senderId;

    public function __construct(array $config)
    {
        $this->client = new Client();
        $this->appSid = $config['app_sid'];
        $this->senderId = $config['sender_id'];
    }

    public function send(string $phone, string $message): bool
    {
        try {
            $response = $this->client->post('https://api.unifonic.com/rest/SMS/messages', [
                'form_params' => [
                    'AppSid' => $this->appSid,
                    'SenderID' => $this->senderId,
                    'Recipient' => $phone,
                    'Body' => $message,
                ]
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            error_log('Unifonic OTP Error: ' . $e->getMessage());
            return false;
        }
    }

    public function verify(string $phone, $otp): bool
    {
        return true;
    }
}
