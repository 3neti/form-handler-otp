<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\FormHandlerOtp\Services\TxtcmdrOtpChallengeGateway;

beforeEach(function (): void {
    config()->set('otp-handler.driver', 'txtcmdr');
    config()->set('otp-handler.txtcmdr.base_url', 'https://txtcmdr.example.test');
    config()->set('otp-handler.txtcmdr.api_token', 'test-api-token');
    config()->set('otp-handler.txtcmdr.connect_timeout', 3);
    config()->set('otp-handler.txtcmdr.timeout', 7);
    config()->set('otp-handler.txtcmdr.verify_ssl', true);
    Http::preventStrayRequests();
});

it('resolves the configured provider through the neutral contract', function (): void {
    expect(app(OtpChallengeGateway::class))->toBeInstanceOf(TxtcmdrOtpChallengeGateway::class);
});

it('creates a versioned challenge using the exact txtcmdr contract', function (): void {
    Http::fake([
        'https://txtcmdr.example.test/api/v1/otp/challenges' => Http::response([
            'verification_id' => 'challenge-123',
            'status' => 'queued',
            'expires_in' => 300,
            'replayed' => false,
        ]),
    ]);

    $challenge = app(OtpChallengeGateway::class)->create(new OtpChallengeRequestData(
        mobile: '+639171234567',
        purpose: 'onboarding.account',
        client_reference: 'onboarding:01TEST',
    ));

    expect($challenge->reference)->toBe('challenge-123')
        ->and($challenge->status)->toBe('queued')
        ->and($challenge->expires_in)->toBe(300);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://txtcmdr.example.test/api/v1/otp/challenges'
        && $request->hasHeader('Authorization', 'Bearer test-api-token')
        && $request->data() === [
            'mobile' => '+639171234567',
            'purpose' => 'onboarding.account',
            'client_reference' => 'onboarding:01TEST',
        ]
    );
});

it('checks, resends, and verifies through challenge-scoped endpoints', function (): void {
    Http::fake([
        'https://txtcmdr.example.test/api/v1/otp/challenges/challenge-123' => Http::response([
            'verification_id' => 'challenge-123',
            'status' => 'ready',
            'expires_in' => 240,
            'replayed' => true,
        ]),
        'https://txtcmdr.example.test/api/v1/otp/challenges/challenge-123/resend' => Http::response([
            'ok' => true,
            'reason' => 'queued',
            'challenge' => [
                'verification_id' => 'challenge-123',
                'status' => 'queued',
                'expires_in' => 300,
                'replayed' => false,
            ],
        ]),
        'https://txtcmdr.example.test/api/v1/otp/challenges/challenge-123/verify' => Http::response([
            'ok' => true,
            'reason' => 'verified',
            'proof' => [
                'verification_id' => 'challenge-123',
                'purpose' => 'onboarding.account',
                'verified_at' => '2026-08-02T12:00:00+08:00',
            ],
        ]),
    ]);

    $gateway = app(OtpChallengeGateway::class);

    expect($gateway->status('challenge-123')->status)->toBe('ready')
        ->and($gateway->resend('challenge-123')->status)->toBe('queued');

    $verification = $gateway->verify('challenge-123', '123456');

    expect($verification->ok)->toBeTrue()
        ->and($verification->reason)->toBe('verified')
        ->and($verification->proof?->reference)->toBe('challenge-123')
        ->and($verification->proof?->purpose)->toBe('onboarding.account');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://txtcmdr.example.test/api/v1/otp/challenges/challenge-123/verify'
        && $request->data() === ['code' => '123456']
    );
});

it('rejects malformed provider responses and missing credentials', function (): void {
    Http::fake([
        'https://txtcmdr.example.test/api/v1/otp/challenges' => Http::response(['status' => 'queued']),
    ]);

    expect(fn () => app(OtpChallengeGateway::class)->create(new OtpChallengeRequestData(
        mobile: '+639171234567',
        purpose: 'onboarding.account',
    )))->toThrow(UnexpectedValueException::class, 'invalid OTP challenge response');

    config()->set('otp-handler.txtcmdr.api_token');

    expect(fn () => new TxtcmdrOtpChallengeGateway)
        ->toThrow(InvalidArgumentException::class, 'Txtcmdr API token is not configured.');
});
