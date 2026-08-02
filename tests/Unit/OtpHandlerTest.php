<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeData;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationProofData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationResultData;
use LBHurtado\FormHandlerOtp\OtpHandler;

class FakeOtpChallengeGateway implements OtpChallengeGateway
{
    public ?OtpChallengeRequestData $created = null;

    public int $resends = 0;

    public OtpVerificationResultData $verification;

    public function __construct()
    {
        $this->verification = new OtpVerificationResultData(
            ok: true,
            reason: 'verified',
            proof: new OtpVerificationProofData(
                reference: 'challenge-123',
                purpose: 'onboarding.account',
                verified_at: '2026-08-02T12:00:00+08:00',
            ),
        );
    }

    public function create(OtpChallengeRequestData $request): OtpChallengeData
    {
        $this->created = $request;

        return new OtpChallengeData('challenge-123', 'queued', 300);
    }

    public function status(string $challengeReference): OtpChallengeData
    {
        return new OtpChallengeData($challengeReference, 'ready', 240, true);
    }

    public function resend(string $challengeReference): OtpChallengeData
    {
        $this->resends++;

        return new OtpChallengeData($challengeReference, 'queued', 300);
    }

    public function verify(string $challengeReference, string $code): OtpVerificationResultData
    {
        return $this->verification;
    }
}

beforeEach(function (): void {
    $this->gateway = new FakeOtpChallengeGateway;
    app()->instance(OtpChallengeGateway::class, $this->gateway);
    Session::put('form_flow.flow-123', [
        'current_step' => 1,
        'instructions' => [
            'steps' => [
                ['handler' => 'form', 'config' => []],
                ['handler' => 'otp', 'config' => ['purpose' => 'onboarding.account']],
            ],
        ],
        'collected_data' => [
            ['mobile' => '+639171234567'],
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

it('implements the handler contract without sending on render', function (): void {
    $handler = app(OtpHandler::class);
    $payload = inertiaPayload($handler->render(
        otpStep(['ui_variant' => 'compact']),
        ['flow_id' => 'flow-123', 'step_index' => 1],
    ));

    expect($handler)->toBeInstanceOf(FormHandlerInterface::class)
        ->and($payload['component'])->toBe('form-flow/otp/OtpCapturePage')
        ->and($payload['props'])->toMatchArray([
            'flow_id' => 'flow-123',
            'step' => '1',
            'mobile' => '+639171234567',
            'challenge_status' => 'idle',
        ])
        ->and($this->gateway->created)->toBeNull()
        ->and(Session::has('otp_challenge.flow-123'))->toBeFalse();
});

it('explicitly requests a challenge and stores only its reference and state', function (): void {
    $result = app(OtpHandler::class)->requestChallenge(
        otpStep(['purpose' => 'onboarding.account']),
        ['flow_id' => 'flow-123', 'step_index' => 1],
    );

    expect($result)->toMatchArray(['status' => 'queued', 'expires_in' => 300])
        ->and($this->gateway->created?->mobile)->toBe('+639171234567')
        ->and($this->gateway->created?->purpose)->toBe('onboarding.account')
        ->and($this->gateway->created?->client_reference)->toBe('form-flow:flow-123:step:1')
        ->and(Session::get('otp_challenge.flow-123.reference'))->toBe('challenge-123');
});

it('verifies through the gateway and returns proof without the raw code', function (): void {
    Session::put('otp_challenge.flow-123', [
        'reference' => 'challenge-123',
        'status' => 'ready',
        'expires_in' => 240,
    ]);

    $result = app(OtpHandler::class)->handle(
        Request::create('/', 'POST', ['data' => ['otp_code' => '123456']]),
        otpStep(),
        ['flow_id' => 'flow-123'],
    );

    expect($result)->toMatchArray([
        'mobile' => '+639171234567',
        'reference_id' => 'flow-123',
        'verification_reference' => 'challenge-123',
        'verification_purpose' => 'onboarding.account',
        'verified_at' => '2026-08-02T12:00:00+08:00',
    ])->not->toHaveKey('otp_code')
        ->and(Session::has('otp_challenge.flow-123'))->toBeFalse();
});

it('requires an explicit challenge before verification', function (): void {
    expect(fn () => app(OtpHandler::class)->handle(
        Request::create('/', 'POST', ['data' => ['otp_code' => '123456']]),
        otpStep(),
        ['flow_id' => 'flow-123'],
    ))->toThrow(ValidationException::class, 'Send a verification code');
});

it('fails closed when the provider omits a verification proof', function (): void {
    Session::put('otp_challenge.flow-123.reference', 'challenge-123');
    $this->gateway->verification = new OtpVerificationResultData(true, 'verified');

    expect(fn () => app(OtpHandler::class)->handle(
        Request::create('/', 'POST', ['data' => ['otp_code' => '123456']]),
        otpStep(),
        ['flow_id' => 'flow-123'],
    ))->toThrow(ValidationException::class, 'did not return a valid proof');
});

it('resends without completing or advancing the form flow', function (): void {
    Session::put('otp_challenge.flow-123.reference', 'challenge-123');
    Session::put('otp_delivery.flow-123', [
        'resends' => 0,
        'sent_at' => now()->subSeconds(31)->timestamp,
    ]);

    $this->postJson('/form-flow/flow-123/step/1/otp-challenge/resend')
        ->assertOk()
        ->assertJsonPath('resent', true);

    expect($this->gateway->resends)->toBe(1)
        ->and(Session::get('form_flow.flow-123.current_step'))->toBe(1)
        ->and(Session::get('form_flow.flow-123.collected_data'))->toHaveCount(1);
});

it('sends through the dedicated endpoint without completing the step', function (): void {
    $this->postJson('/form-flow/flow-123/step/1/otp-challenge')
        ->assertOk()
        ->assertJsonPath('status', 'queued');

    expect(Session::get('form_flow.flow-123.current_step'))->toBe(1)
        ->and(Session::get('form_flow.flow-123.collected_data'))->toHaveCount(1);
});

it('rejects challenge actions for a non-current or non-OTP step', function (): void {
    $this->postJson('/form-flow/flow-123/step/0/otp-challenge')->assertNotFound();
    $this->postJson('/form-flow/flow-123/step/2/otp-challenge')->assertNotFound();
});

it('publishes the supported configuration schema', function (): void {
    expect(app(OtpHandler::class)->getConfigSchema())
        ->toHaveKeys(['purpose', 'max_resends', 'resend_cooldown', 'digits', 'ui_variant'])
        ->and(app(OtpHandler::class)->validate([], []))->toBeTrue();
});
