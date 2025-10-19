<?php

namespace OneStudio\Otp\Tests\Unit;

use OneStudio\Otp\Tests\TestCase;
use OneStudio\Otp\Providers\UnifonicProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;

class UnifonicProviderTest extends TestCase
{
    public function test_can_create_unifonic_provider()
    {
        $config = [
            'app_sid' => 'test_app_sid',
            'sender_id' => 'test_sender_id',
        ];

        $provider = new UnifonicProvider($config);

        $this->assertInstanceOf(UnifonicProvider::class, $provider);
    }

    public function test_send_method_returns_boolean()
    {
        $config = [
            'app_sid' => 'test_app_sid',
            'sender_id' => 'test_sender_id',
        ];

        $provider = new UnifonicProvider($config);

        // Mock the Guzzle client
        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(200);

        $mockClient->shouldReceive('post')
            ->once()
            ->with('https://api.unifonic.com/rest/SMS/messages', [
                'form_params' => [
                    'AppSid' => 'test_app_sid',
                    'SenderID' => 'test_sender_id',
                    'Recipient' => '+201120305686',
                    'Body' => 'Test message',
                ]
            ])
            ->andReturn($mockResponse);

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
            'app_sid' => 'test_app_sid',
            'sender_id' => 'test_sender_id',
        ];

        $provider = new UnifonicProvider($config);

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
