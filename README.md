# OneStudio OTP Package

A comprehensive Laravel package for sending and verifying One-Time Passwords (OTP) via SMS using multiple providers including Twilio and Unifonic.

## Features

- 🔐 **Multi-Provider Support**: Twilio and Unifonic SMS providers
- 🛡️ **Security Features**: Rate limiting, attempt tracking, and automatic blocking
- ⏰ **Configurable Expiry**: Customizable OTP expiration times
- 🔄 **Resend Protection**: Prevents spam with configurable resend delays
- 🧪 **Fully Tested**: Comprehensive test suite with 16 tests
- 🎯 **Laravel Integration**: Service provider, facades, and auto-discovery
- 📱 **Easy to Use**: Simple API for generating and verifying OTPs

## Installation

### Via Composer

```bash
composer require one-studio/otp
```

### Laravel Auto-Discovery

The package will be automatically discovered by Laravel. If you're using Laravel 5.5+, no additional configuration is required.

### Manual Registration (Laravel < 5.5)

Add the service provider to your `config/app.php`:

```php
'providers' => [
    // ...
    OneStudio\Otp\OtpServiceProvider::class,
],
```

Add the facade alias:

```php
'aliases' => [
    // ...
    'Otp' => OneStudio\Otp\Facades\Otp::class,
],
```

## Configuration

### Publishing Configuration and Translations

Publish the configuration file:

```bash
php artisan vendor:publish --provider="OneStudio\Otp\OtpServiceProvider" --tag="otp-config"
```

Publish the translation files:

```bash
php artisan vendor:publish --provider="OneStudio\Otp\OtpServiceProvider" --tag="otp-translations"
```

This will create:
- `config/otp.php` - Configuration file
- `lang/vendor/otp/en/otp.php` - English translations
- `lang/vendor/otp/ar/otp.php` - Arabic translations

### Environment Variables

Add the following environment variables to your `.env` file:

```env
# OTP Provider (twilio or unifonic)
OTP_PROVIDER=twilio

# Twilio Configuration
TWILIO_ACCOUNT_SID=your_twilio_account_sid
TWILIO_AUTH_TOKEN=your_twilio_auth_token
TWILIO_FROM=your_twilio_phone_number

# Unifonic Configuration (if using Unifonic)
UNIFONIC_APP_SID=your_unifonic_app_sid
UNIFONIC_SENDER_ID=your_unifonic_sender_id

# OTP Settings
OTP_LENGTH=4
OTP_EXPIRY=5
MAX_ATTEMPTS=3
RESEND_DELAY=60
BLOCK_DURATION=30

# Test Mode Settings
OTP_TEST_MODE=false
OTP_TEST_CODE=8888
```

### Configuration File

The published configuration file (`config/otp.php`) contains:

```php
return [
    'default' => env('OTP_PROVIDER', 'twilio'),

    'providers' => [
        'twilio' => [
            'driver' => 'twilio',
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
        'unifonic' => [
            'driver' => 'unifonic',
            'app_sid' => env('UNIFONIC_APP_SID'),
            'sender_id' => env('UNIFONIC_SENDER_ID'),
        ],
    ],

    'otp_length' => env('OTP_LENGTH', 4),
    'otp_expiry' => env('OTP_EXPIRY', 5), // minutes
    'max_attempts' => env('MAX_ATTEMPTS', 3),
    'resend_delay' => env('RESEND_DELAY', 60), // seconds
    'block_duration' => env('BLOCK_DURATION', 30), // minutes after max attempts

    // Test mode configuration
    'test_mode' => env('OTP_TEST_MODE', false),
    'test_otp' => env('OTP_TEST_CODE', '8888'),
    'test_numbers' => [
        '+1234567890',
        '+9876543210',
        // Add more test numbers as needed
    ],

    // Rate limiting configuration
    'rate_limit' => [
        'enabled' => env('OTP_RATE_LIMIT_ENABLED', true),
        'max_requests_per_hour' => env('OTP_MAX_REQUESTS_PER_HOUR', 3), // per phone number
        'block_duration' => env('OTP_BLOCK_DURATION', 60), // minutes to block after limit exceeded
    ],
];
```

## Usage

### Multi-Language Support

The package supports multiple languages with built-in English and Arabic translations. You can customize messages by publishing and editing the translation files.

**Available Languages:**
- English (`en`)
- Arabic (`ar`)

**Setting Application Locale:**

In your Laravel application, set the locale in `config/app.php`:

```php
'locale' => 'ar', // For Arabic
'locale' => 'en', // For English
```

Or dynamically in your application:

```php
App::setLocale('ar'); // Switch to Arabic
App::setLocale('en'); // Switch to English
```

**Customizing Messages:**

After publishing translations, you can customize the messages in:
- `lang/vendor/otp/en/otp.php` - English messages
- `lang/vendor/otp/ar/otp.php` - Arabic messages

### Rate Limiting

The package includes built-in rate limiting to prevent abuse and excessive OTP requests. Rate limiting uses a rolling window approach - tracking requests in the last 60 minutes per phone number with automatic blocking.

### Configuration

Rate limiting can be configured in your `.env` file:

```env
# Enable/disable rate limiting
OTP_RATE_LIMIT_ENABLED=true

# Maximum requests per phone number per hour
OTP_MAX_REQUESTS_PER_HOUR=3

# Block duration in minutes after limit exceeded
OTP_BLOCK_DURATION=60
```

### How It Works

- **Per Phone Number**: Rate limits are applied individually to each phone number
- **Rolling Window**: Tracks requests in the last 60 minutes (not fixed hourly blocks)
- **Automatic Blocking**: When limit is exceeded, phone is blocked for specified duration
- **Block Duration**: Configurable block time (default: 60 minutes)
- **Cache-Based**: Uses Laravel's cache system for tracking with automatic cleanup

### Rate Limit Response

When rate limits are exceeded, the service returns:

```php
[
    'success' => false,
    'message' => 'Rate limit exceeded. Maximum 3 requests per hour allowed. Blocked for 60 minutes.',
    'rate_limited' => true,
    'retry_after' => 60 // minutes until unblocked
]
```

When phone is blocked, the service returns:

```php
[
    'success' => false,
    'message' => 'Phone number is blocked due to rate limiting. Try again in 45 minutes.',
    'rate_limited' => true,
    'retry_after' => 45 // minutes remaining
]
```

### Disabling Rate Limiting

To disable rate limiting entirely:

```env
OTP_RATE_LIMIT_ENABLED=false
```

### Use Cases

- **Production**: Enable rate limiting to prevent abuse
- **Development**: Disable rate limiting for easier testing
- **High Traffic**: Adjust limits based on your application needs

## Test Mode

The package includes a built-in test mode for development and testing purposes. This allows you to test OTP functionality without sending actual SMS messages.

**Enabling Test Mode:**

1. **Global Test Mode** - Enable for all phone numbers:
```env
OTP_TEST_MODE=true
OTP_TEST_CODE=8888

# Rate limiting configuration
OTP_RATE_LIMIT_ENABLED=true
OTP_MAX_REQUESTS_PER_HOUR=3
OTP_BLOCK_DURATION=60
```

2. **Specific Test Numbers** - Add phone numbers to test list:
```php
// In config/otp.php
'test_numbers' => [
    '+1234567890',
    '+9876543210',
    '+201120305686', // Your test number
],
```

**Test Mode Behavior:**

- **No SMS Sent**: When test mode is active, no actual SMS is sent
- **Fixed OTP**: Uses the configured test OTP code (default: 8888)
- **Same Verification**: Test OTPs work exactly like real OTPs
- **Response Indicators**: Test mode responses include `test_mode: true` and `test_otp` fields

**Test Mode Response Example:**
```php
[
    'success' => true,
    'message' => 'OTP sent successfully.',
    'expires_in' => 300,
    'remaining_time' => 60,
    'test_mode' => true,
    'test_otp' => '8888'
]
```

**Use Cases:**
- Development and testing
- Demo environments
- CI/CD pipelines
- Unit testing
- Avoiding SMS costs during development

### Using the Facade

```php
use OneStudio\Otp\Facades\Otp;

// Generate OTP
$result = Otp::generate('+1234567890');

if ($result['success']) {
    echo "OTP sent successfully!";
    echo "Expires in: " . $result['expires_in'] . " seconds";
} else {
    echo "Failed to send OTP: " . $result['message'];
}

// Verify OTP
$verifyResult = Otp::verify('+1234567890', '1234');

if ($verifyResult['success']) {
    echo "OTP verified successfully!";
} else {
    echo "Verification failed: " . $verifyResult['message'];
    if (isset($verifyResult['remaining_attempts'])) {
        echo "Remaining attempts: " . $verifyResult['remaining_attempts'];
    }
}
```

### Using Dependency Injection

```php
use OneStudio\Otp\OtpService;

class AuthController extends Controller
{
    protected $otpService;

f
    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function sendOtp(Request $request)
    {
        $phone = $request->input('phone');
        $result = $this->otpService->generate($phone);

        return response()->json($result);
    }
f
    public function verifyOtp(Request $request)
    {
        $phone = $request->input('phone');
        $code = $request->input('code');
        
        $result = $this->otpService->verify($phone, $code);

        return response()->json($result);
    }
}
```

### Using the Manager Directly

```php
use OneStudio\Otp\OtpManager;

$manager = app(OtpManager::class);
$provider = $manager->driver(); // Gets the default provider
$result = $provider->send('+1234567890', 'Your OTP is: 1234');
```

## API Reference

### OtpService Methods

#### `generate(string $phone): array`

Generates and sends an OTP to the specified phone number.

**Parameters:**
- `$phone` (string): Phone number in international format (e.g., +1234567890)

**Returns:**
```php
[
    'success' => bool,
    'message' => string,
    'expires_in' => int, // seconds
    'remaining_time' => int, // if resend delay active
    'blocked_until' => Carbon, // if blocked
]
```

#### `verify(string $phone, string $code): array`

Verifies an OTP code for the specified phone number.

**Parameters:**
- `$phone` (string): Phone number in international format
- `$code` (string): OTP code to verify

**Returns:**
```php
[
    'success' => bool,
    'message' => string,
    'remaining_attempts' => int, // if verification failed
]
```

## Security Features

### Rate Limiting
- **Resend Delay**: Prevents immediate resending of OTPs (default: 60 seconds)
- **Max Attempts**: Limits verification attempts (default: 3 attempts)
- **Block Duration**: Temporary blocking after max attempts exceeded (default: 30 minutes)

### OTP Management
- **Expiry**: OTPs expire after configured time (default: 5 minutes)
- **Single Use**: OTPs are automatically removed after successful verification
- **Cache Storage**: OTPs are stored securely in Laravel's cache system

## Error Handling

The package returns detailed error messages for various scenarios. All messages are translatable and will be returned in the current application locale:

**English Messages:**
```php
// Rate limiting
[
    'success' => false,
    'message' => 'Please wait 45 seconds before requesting a new OTP.',
    'remaining_time' => 45
]

// Blocked phone
[
    'success' => false,
    'message' => 'Too many attempts. Please try again later.',
    'blocked_until' => Carbon::now()->addMinutes(30)
]

// Invalid OTP
[
    'success' => false,
    'message' => 'Invalid OTP.',
    'remaining_attempts' => 2
]

// Expired OTP
[
    'success' => false,
    'message' => 'OTP expired or not found.'
]
```

**Arabic Messages (when locale is set to 'ar'):**
```php
// Rate limiting
[
    'success' => false,
    'message' => 'يرجى الانتظار 45 ثانية قبل طلب رمز تحقق جديد.',
    'remaining_time' => 45
]

// Blocked phone
[
    'success' => false,
    'message' => 'محاولات كثيرة جداً. يرجى المحاولة مرة أخرى لاحقاً.',
    'blocked_until' => Carbon::now()->addMinutes(30)
]

// Invalid OTP
[
    'success' => false,
    'message' => 'رمز التحقق غير صحيح.',
    'remaining_attempts' => 2
]

// Expired OTP
[
    'success' => false,
    'message' => 'انتهت صلاحية رمز التحقق أو لم يتم العثور عليه.'
]
```

## Testing

The package includes comprehensive tests. Run them using:

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suites
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Feature

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## Supported Providers

### Twilio
- **Driver**: `twilio`
- **Required Config**: `account_sid`, `auth_token`, `from`
- **Documentation**: [Twilio SMS API](https://www.twilio.com/docs/sms)

### Unifonic
- **Driver**: `unifonic`
- **Required Config**: `app_sid`, `sender_id`
- **Documentation**: [Unifonic SMS API](https://docs.unifonic.com/)

## Requirements

- PHP 8.2+
- Laravel 10.0+
- Twilio SDK (for Twilio provider)
- Guzzle HTTP (for Unifonic provider)

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Support

For support, please open an issue on the GitHub repository or contact the maintainer.

## Changelog

### Version 0.1
- Initial release
- Twilio and Unifonic provider support
- **Rate limiting per phone number** (hourly and daily limits)
- **Configurable rate limits** via environment variables
- **Rate limit responses** with retry information
- Comprehensive test suite (26 tests)
- Laravel auto-discovery support
- **Multi-language support** (English & Arabic)
- **Translatable messages** for all responses
- **Publishable translation files** for customization
- **Test Mode** for development and testing
- **Test phone numbers** support
- **Fixed test OTP** for consistent testing
