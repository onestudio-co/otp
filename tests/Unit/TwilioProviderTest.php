<?php

namespace OneStudio\Otp\Tests\Unit;

use OneStudio\Otp\Tests\TestCase;
use OneStudio\Otp\Providers\TwilioProvider;
use Twilio\Rest\Client;
use Mockery;

class TwilioProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_create_twilio_provider()
    {
        $config = [
            'account_sid' => 'test_account_sid',
            'auth_token' => 'test_auth_token',
            'from' => '+1234567890',
        ];

        $provider = new TwilioProvider($config);

        $this->assertInstanceOf(TwilioProvider::class, $provider);
    }

    public function test_send_method_returns_boolean()
    {
        $config = [
            'account_sid' => 'test_account_sid',
            'auth_token' => 'test_auth_token',
            'from' => '+1234567890',
        ];

        $provider = new TwilioProvider($config);

        // Mock the Twilio client
        $mockClient = Mockery::mock(Client::class);
        $mockMessages = Mockery::mock();
        $mockMessages->shouldReceive('create')
            ->once()
            ->with('+201120305686', [
                'from' => '+1234567890',
                'body' => 'Test message'
            ]);

        $mockClient->messages = $mockMessages;

        // Use reflection to set the private client property
        $reflection = new \ReflectionClass($provider);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($provider, $mockClient);

        $result = $provider->send('+201120305686', 'Test message');

        $this->assertIsBool($result);
    }

    public function test_verify_method_returns_boolean()
    {
        $config = [
            'account_sid' => 'test_account_sid',
            'auth_token' => 'test_auth_token',
            'from' => '+1234567890',
        ];

        $provider = new TwilioProvider($config);

        $result = $provider->verify('+201120305686', '1234');

        $this->assertIsBool($result);
        $this->assertTrue($result); // Current implementation always returns true
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
