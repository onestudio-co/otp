<?php

namespace OneStudio\Otp\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use OneStudio\Otp\OtpServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            OtpServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Otp' => \OneStudio\Otp\Facades\Otp::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('otp', [
            'default' => 'twilio',
            'providers' => [
                'twilio' => [
                    'driver' => 'twilio',
                    'account_sid' => 'test_account_sid',
                    'auth_token' => 'test_auth_token',
                    'from' => '+1234567890',
                ],
                'unifonic' => [
                    'driver' => 'unifonic',
                    'app_sid' => 'test_app_sid',
                    'sender_id' => 'test_sender_id',
                ],
            ],
            'otp_length' => 4,
            'otp_expiry' => 5,
            'max_attempts' => 3,
            'resend_delay' => 60,
            'block_duration' => 30,
            'rate_limit' => [
                'enabled' => true, // Disable rate limiting for basic tests
                'max_requests_per_hour' => 3,
                'block_duration' => 60,
            ],
            'test_mode' => false,
            'test_otp' => '8888',
            'test_numbers' => [],
        ]);
    }
}
