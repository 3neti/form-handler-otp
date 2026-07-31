# OTP Handler

`3neti/form-handler-otp` adds mobile one-time-password verification to
`3neti/form-flow`. It delegates OTP generation, delivery, expiry, attempt
tracking, and verification to a configured Txtcmdr service.

## Requirements

- PHP 8.2 or newer
- Laravel 12 or 13
- Form Flow 1.8 or newer
- Inertia Laravel 2 or 3 in applications that render the published Vue page

## Installation

```bash
composer require 3neti/form-handler-otp
php artisan otp-handler:install --no-interaction
```

The package service provider registers the `otp` handler automatically. The
install command publishes the package configuration and Vue page.

## Configuration

Publish the configuration independently when needed:

```bash
php artisan vendor:publish --tag=otp-handler-config
```

Configure the Txtcmdr endpoint and bearer token in the host environment:

```dotenv
TXTCMDR_API_URL=https://txtcmdr.example
TXTCMDR_API_TOKEN=
TXTCMDR_TIMEOUT=30

OTP_MAX_RESENDS=3
OTP_RESEND_COOLDOWN=30
```

Do not commit the API token. The handler sends it only as the bearer token for
Txtcmdr requests.

## Form Flow contract

Add an `otp` step after a step that collects `mobile`:

```php
[
    'handler' => 'otp',
    'config' => [
        'max_resends' => 3,
        'resend_cooldown' => 30,
        'digits' => 6,
        'ui_variant' => 'compact',
    ],
]
```

The handler reads the mobile number from the current Form Flow session. It
fails before contacting Txtcmdr if no mobile number has been collected.

On the first render, the handler:

1. requests an OTP from `POST /api/otp/request`;
2. stores only the returned verification identifier in the session;
3. renders the OTP capture page.

On submission, it verifies the code through `POST /api/otp/verify`. A successful
verification clears the verification and delivery-control session state, then
returns:

```php
[
    'mobile' => '639171234567',
    'otp_code' => '123456',
    'verified_at' => '2026-07-31T15:00:00+08:00',
    'reference_id' => 'flow-123',
]
```

The returned code is Form Flow result data. Hosts should apply their own data
retention and redaction policy before persisting or logging collected results.

## User interface

The published Vue page supports Form Flow's `default`, `compact`, and
`immersive` UI variants. It uses the shared Form Flow screen and action
components, accepts numeric input, submits the nested `data.otp_code` payload,
and focuses the code input on entry.

## Delivery safeguards

- Verification identifiers are isolated by Form Flow reference in the session.
- Successful codes are one-time because Txtcmdr is the verification authority
  and local session state is cleared after acceptance.
- Resend count and cooldown are enforced by the server handler, not only by
  the browser.
- Provider rejection reasons are mapped to sanitized validation messages.
- A missing verification session or mobile number fails closed.
- Hosts should still rate-limit Form Flow endpoints by authenticated principal,
  session, mobile reference, and source address.

## Testing

```bash
composer test
composer pint -- --test
composer audit
```

The package suite fakes HTTP and verifies the request, session, resend,
provider-rejection, expiry, and UI render contracts without sending SMS.

## License

MIT
