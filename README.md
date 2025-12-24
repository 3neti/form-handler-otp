# OTP Handler Plugin

A Form Flow Manager plugin for OTP (One-Time Password) verification using time-based tokens (TOTP).

## Features

✅ Time-based OTP generation (TOTP RFC 6238)  
✅ Configurable OTP period, digits, and resend limits  
✅ SMS delivery via callback (provider-agnostic)  
✅ Resend functionality with cooldown timer  
✅ Automatic cache management  
✅ Auto-registration with Form Flow Manager  
✅ Mobile-optimized Vue component

## Installation

```bash
composer require 3neti/form-handler-otp
```

That's it! The handler automatically registers itself with the Form Flow Manager.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=otp-handler-config
```

Publish the Vue component:

```bash
php artisan vendor:publish --tag=otp-handler-stubs
```

Edit `config/otp-handler.php`:

```php
return [
    'label' => env('OTP_LABEL', config('app.name')),
    'period' => env('OTP_PERIOD', 600),  // 10 minutes
    'digits' => env('OTP_DIGITS', 4),
    'cache_prefix' => 'otp',
    'max_resends' => env('OTP_MAX_RESENDS', 3),
    'resend_cooldown' => env('OTP_RESEND_COOLDOWN', 30),  // 30 seconds
    'send_sms_callback' => null,  // Configure in service provider
];
```

### Environment Variables

Add to `.env`:

```bash
OTP_LABEL="Your App"
OTP_PERIOD=600         # 10 minutes
OTP_DIGITS=4           # 4-digit OTP
OTP_MAX_RESENDS=3      # Max 3 resend attempts
OTP_RESEND_COOLDOWN=30 # 30 seconds cooldown
```

### SMS Integration

The plugin includes built-in SMS support via `lbhurtado/sms` with EngageSpark. No additional configuration needed if your app already uses EngageSpark.

**Environment Variables:**

```bash
ENGAGESPARK_API_KEY=your_api_key
ENGAGESPARK_ORG_ID=your_org_id
ENGAGESPARK_SENDER_ID=cashless
```

The plugin automatically uses these credentials from your existing SMS configuration.

## Usage

### In a Form Flow

```javascript
const response = await fetch('/form-flow/start', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        reference_id: 'unique-id',
        steps: [
            {
                handler: 'otp',
                config: {
                    title: 'Verify Your Identity',
                    description: 'Enter the OTP sent to your mobile',
                    max_resends: 3,
                    resend_cooldown: 30,
                    digits: 4
                }
            }
        ],
        callbacks: {
            on_complete: 'https://your-app.test/callback'
        }
    })
});
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `max_resends` | integer | `3` | Maximum OTP resend attempts |
| `resend_cooldown` | integer | `30` | Cooldown between resends (seconds) |
| `digits` | integer | `4` | Number of digits in OTP (4, 5, or 6) |

### Context Requirements

The OTP handler requires `mobile` to be present in the context:

```php
// In your form flow
$context = [
    'flow_id' => $flowId,
    'mobile' => '09171234567',  // Required for OTP delivery
];
```

## Collected Data

The handler returns the following data structure:

```php
[
    'mobile' => '09171234567',
    'otp_code' => '1234',
    'verified_at' => '2024-12-15T08:00:00+08:00',
    'reference_id' => 'flow-abc123',
]
```

## Testing

```bash
cd packages/form-handler-otp
composer test
```

### Test Coverage

- ✅ Interface implementation
- ✅ OTP generation (4, 5, 6 digits)
- ✅ OTP validation (correct/incorrect)
- ✅ Cache management
- ✅ Expiry handling
- ✅ Config schema
- ✅ Handler name

## How It Works

### 1. Plugin Auto-Registration

```php
// OtpHandlerServiceProvider::boot()
protected function registerHandler(): void
{
    $handlers = config('form-flow.handlers', []);
    $handlers['otp'] = OtpHandler::class;
    config(['form-flow.handlers' => $handlers]);
}
```

### 2. OTP Generation

Uses `spomky-labs/otphp` library for TOTP (Time-based One-Time Password):

```php
$totp = TOTP::createFromSecret($secret);
$totp->setPeriod(600);  // 10 minutes
$totp->setDigits(4);    // 4-digit code
$code = $totp->now();   // Generate current OTP
```

### 3. SMS Delivery

OTP is sent via configurable callback:

```php
$callback = config('otp-handler.send_sms_callback');
$callback($mobile, $otpCode, $appName);
```

### 4. Validation

```php
$totp = TOTP::createFromSecret($cachedSecret);
$isValid = $totp->verify($submittedCode, null, $window);  // ±1 time window
```

### 5. Resend Logic

Frontend handles resend with:
- Cooldown timer (default 30 seconds)
- Max attempts limit (default 3)
- Success/error messaging

## Architecture

This is a **plugin package** for Form Flow Manager:

```
form-handler-otp/          (Plugin)
├── Implements FormHandlerInterface
├── Self-registers via service provider
└── Optional dependency

form-flow-manager/         (Core)
├── Discovers plugins automatically
└── Orchestrates flow with registered handlers

redeem-x/                  (Host App)
└── Installs: core + chosen plugins
```

### Plugin Benefits

✅ **Optional** - Install only if needed  
✅ **Independent** - Tested separately  
✅ **Reusable** - Works across different apps  
✅ **Maintainable** - Clean separation of concerns  
✅ **Provider-agnostic** - No hardcoded SMS dependencies

## Security Considerations

- **Rate Limiting**: Max 3 resends per session
- **Timing Attack Prevention**: Constant-time comparison via TOTP library
- **Clock Skew**: ±1 time window for validation
- **Cache Isolation**: Unique prefix to avoid collisions
- **Auto-cleanup**: Cache TTL matches OTP period
- **One-time use**: Cache cleared after successful validation

## Requirements

- PHP 8.2+
- Laravel 12+
- Form Flow Manager (`lbhurtado/form-flow-manager`)
- Spomky Labs OTPHP (`spomky-labs/otphp`)

## Troubleshooting

### "Handler not found: otp"

**Solution:**
```bash
php artisan config:clear
php artisan cache:clear
composer dump-autoload
```

### OTP not sent

**Check:**
1. SMS callback configured?
2. Mobile number valid?
3. SMS provider credentials correct?
4. Check logs for callback errors

### OTP validation fails

**Check:**
1. OTP not expired? (default: 10 minutes)
2. Correct digits configured?
3. Cache working properly?
4. Clock sync between server and client?

## Related Packages

- [form-flow-manager](../form-flow-manager) - Core orchestration
- [form-handler-location](../form-handler-location) - GPS capture
- [form-handler-selfie](../form-handler-selfie) - Camera capture (planned)
- [form-handler-signature](../form-handler-signature) - Digital signature (planned)

## License

MIT

## Author

3neti
