<?php

declare(strict_types=1);

namespace OneStudio\Otp\Providers;

use Twilio\Rest\Client;
use OneStudio\Otp\Contracts\OtpProviderInterface;

class TwilioProvider implements OtpProviderInterface
{
    private const SERVICE_TYPE_SMS = 'sms';
    private const SERVICE_TYPE_VERIFY = 'verify';

    protected $client;
    private $twilloServiceType;
    private $sid;
    private $from;

    public function __construct(array $config)
    {
        $this->client = new Client($config['account_sid'], $config['auth_token']);
        $this->twilloServiceType = $config['service_type'] ?? self::SERVICE_TYPE_SMS;
        $this->sid = isset($config['verification_sid']) ? $config['verification_sid'] : null;
        $this->from = isset($config['from']) ? $config['from'] : null;
    }

    public function send(string $phone, string $otp, ?string $message): bool
    {
        try {
            if($this->twilloServiceType == self::SERVICE_TYPE_SMS){
                return $this->sendViaSms($phone, $message);
            }
            if($this->twilloServiceType == self::SERVICE_TYPE_VERIFY){
                return $this->sendViaVerify($phone, $otp);
            }
            error_log('Twilio OTP Error: Invalid service type');
            return false;
        } catch (\Exception $e) {
            error_log('Twilio OTP Error: ' . $e->getMessage());
            return false;
        }
    }

    public function verify(string $phone, $otp): bool
    {
        return true;
    }

    private function sendViaSms(string $phone, ?string $message): bool
    {
        $this->client->messages->create($phone, [
            'from' => $this->from,
            'body' => $message
        ]);
        return true;
    }

    private function sendViaVerify(string $phone, string $otp): bool
    {
        $this->client->verify->v2
            ->services($this->sid)
            ->verifications->create($phone, self::SERVICE_TYPE_SMS, ["customCode" => $otp]);
        return true;
    }
}
