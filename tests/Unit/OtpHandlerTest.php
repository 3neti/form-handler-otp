<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormHandlerOtp\OtpHandler;
use LBHurtado\FormHandlerOtp\Services\TxtcmdrClient;

beforeEach(function (): void {
    config()->set('otp-handler.txtcmdr.base_url', 'https://txtcmdr.example.test');
    config()->set('otp-handler.txtcmdr.api_token', 'test-api-token');
    config()->set('otp-handler.txtcmdr.timeout', 5);

    Session::put('form_flow.flow-123', [
        'collected_data' => [
            'recipient' => ['mobile' => '639171234567'],
        ],
    ]);
});

function otpStep(array $config = []): FormFlowStepData
{
    return new FormFlowStepData(handler: 'otp', config: $config);
}

/**
 * @return array{component: string, props: array<string, mixed>}
 */
function inertiaPayload(Response $response): array
{
    return [
        'component' => (fn (): string => $this->component)->call($response),
        'props' => (fn (): array => $this->props)->call($response),
    ];
}

it('implements the form handler contract', function (): void {
    $handler = new OtpHandler;

    expect($handler)
        ->toBeInstanceOf(FormHandlerInterface::class)
        ->and($handler->getName())->toBe('otp');
});

it('requests an OTP on first render and exposes the current UI contract', function (): void {
    Http::fake([
        'https://txtcmdr.example.test/api/v1/otp/challenges' => Http::response([
            'verification_id' => 'verification-123',
            'status' => 'queued',
            'expires_in' => 300,
            'replayed' => false,
        ]),
    ]);

    $response = (new OtpHandler)->render(
        otpStep(['ui_variant' => 'compact']),
        ['flow_id' => 'flow-123', 'step_index' => 2],
    );
    $payload = inertiaPayload($response);

    expect($payload['component'])->toBe('form-flow/otp/OtpCapturePage')
        ->and($payload['props'])->toMatchArray([
            'flow_id' => 'flow-123',
            'step' => '2',
            'mobile' => '639171234567',
            'ui_variant' => 'compact',
        ])
        ->and($payload['props']['config'])->toMatchArray([
            'max_resends' => 3,
            'resend_cooldown' => 30,
            'digits' => 6,
        ])
        ->and(Session::get('otp_verification.flow-123'))->toBe('verification-123');

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://txtcmdr.example.test/api/v1/otp/challenges'
            && $request->hasHeader('Authorization', 'Bearer test-api-token')
            && $request->data() === [
                'mobile' => '639171234567',
                'purpose' => 'verification',
                'client_reference' => 'flow-123',
            ];
    });
});

it('reuses the active verification session when rendering again', function (): void {
    Session::put('otp_verification.flow-123', 'verification-existing');
    Http::fake();

    (new OtpHandler)->render(otpStep(), ['flow_id' => 'flow-123']);

    Http::assertNothingSent();
    expect(Session::get('otp_verification.flow-123'))->toBe('verification-existing');
});

it('verifies the submitted code and clears the verification session', function (): void {
    Session::put('otp_verification.flow-123', 'verification-123');
    Http::fake([
        'https://txtcmdr.example.test/api/v1/otp/challenges/verification-123/verify' => Http::response([
            'ok' => true,
            'reason' => 'verified',
            'attempts' => 1,
            'status' => 'verified',
        ]),
    ]);

    $result = (new OtpHandler)->handle(
        Request::create('/', 'POST', ['data' => ['otp_code' => '123456']]),
        otpStep(),
        ['flow_id' => 'flow-123'],
    );

    expect($result)->toMatchArray([
        'mobile' => '639171234567',
        'otp_code' => '123456',
        'reference_id' => 'flow-123',
    ])
        ->and($result['verified_at'])->toBeString()
        ->and(Session::has('otp_verification.flow-123'))->toBeFalse()
        ->and(Session::has('otp_delivery.flow-123'))->toBeFalse();

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://txtcmdr.example.test/api/v1/otp/challenges/verification-123/verify'
            && $request->data() === ['code' => '123456'];
    });
});

it('maps provider rejection to a validation error without clearing the session', function (): void {
    Session::put('otp_verification.flow-123', 'verification-123');
    Http::fake([
        'https://txtcmdr.example.test/api/v1/otp/challenges/verification-123/verify' => Http::response([
            'ok' => false,
            'reason' => 'invalid_code',
            'attempts' => 2,
            'status' => 'pending',
        ]),
    ]);

    expect(fn () => (new OtpHandler)->handle(
        Request::create('/', 'POST', ['data' => ['otp_code' => '999999']]),
        otpStep(),
        ['flow_id' => 'flow-123'],
    ))->toThrow(ValidationException::class, 'The OTP code is incorrect.');

    expect(Session::get('otp_verification.flow-123'))->toBe('verification-123');
});

it('fails closed when the verification session has expired', function (): void {
    Http::fake();

    expect(fn () => (new OtpHandler)->handle(
        Request::create('/', 'POST', ['data' => ['otp_code' => '123456']]),
        otpStep(),
        ['flow_id' => 'flow-123'],
    ))->toThrow(ValidationException::class, 'Verification session expired.');

    Http::assertNothingSent();
});

it('requests and stores a replacement verification on resend', function (): void {
    Session::put('otp_verification.flow-123', 'verification-old');
    Session::put('otp_delivery.flow-123', [
        'resends' => 0,
        'sent_at' => now()->subSeconds(31)->timestamp,
    ]);
    Http::fake([
        'https://txtcmdr.example.test/api/v1/otp/challenges' => Http::response([
            'verification_id' => 'verification-new',
            'status' => 'queued',
            'expires_in' => 300,
            'replayed' => false,
        ]),
    ]);

    $result = (new OtpHandler)->handle(
        Request::create('/', 'POST', ['resend' => true]),
        otpStep(),
        ['flow_id' => 'flow-123'],
    );

    expect($result)->toBe(['resent' => true])
        ->and(Session::get('otp_verification.flow-123'))->toBe('verification-new')
        ->and(Session::get('otp_delivery.flow-123.resends'))->toBe(1);

    Http::assertSentCount(1);
});

it('enforces resend cooldown and maximum attempts on the server', function (): void {
    Session::put('otp_delivery.flow-123', [
        'resends' => 0,
        'sent_at' => now()->timestamp,
    ]);
    Http::fake();

    expect(fn () => (new OtpHandler)->handle(
        Request::create('/', 'POST', ['resend' => true]),
        otpStep(),
        ['flow_id' => 'flow-123'],
    ))->toThrow(ValidationException::class, 'Please wait 30 seconds');

    Session::put('otp_delivery.flow-123', [
        'resends' => 3,
        'sent_at' => now()->subMinute()->timestamp,
    ]);

    expect(fn () => (new OtpHandler)->handle(
        Request::create('/', 'POST', ['resend' => true]),
        otpStep(),
        ['flow_id' => 'flow-123'],
    ))->toThrow(ValidationException::class, 'maximum number');

    Http::assertNothingSent();
});

it('fails closed before provider delivery when no mobile was collected', function (): void {
    Session::forget('form_flow.flow-123');
    Http::fake();

    expect(fn () => (new OtpHandler)->render(
        otpStep(),
        ['flow_id' => 'flow-123'],
    ))->toThrow(ValidationException::class, 'A mobile number must be collected');

    Http::assertNothingSent();
});

it('publishes the supported configuration schema', function (): void {
    expect((new OtpHandler)->getConfigSchema())
        ->toHaveKeys(['max_resends', 'resend_cooldown', 'digits', 'ui_variant'])
        ->and((new OtpHandler)->validate([], []))->toBeTrue();
});

it('fails safely when Txtcmdr credentials are absent', function (): void {
    config()->set('otp-handler.txtcmdr.api_token');

    expect(fn () => new TxtcmdrClient)
        ->toThrow(InvalidArgumentException::class, 'Txtcmdr API token is not configured.');
});

it('rejects malformed provider responses', function (): void {
    Http::fake([
        'https://txtcmdr.example.test/api/v1/otp/challenges' => Http::response(['status' => 'accepted']),
    ]);

    expect(fn () => (new TxtcmdrClient)->requestOtp('639171234567', 'flow-123'))
        ->toThrow(UnexpectedValueException::class, 'invalid OTP challenge response');
});
