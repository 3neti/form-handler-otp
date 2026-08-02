<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\FormHandlerOtp\Data\OtpData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationProofData;

class OtpHandler implements FormHandlerInterface
{
    public function __construct(private OtpChallengeGateway $gateway) {}

    public function getName(): string
    {
        return 'otp';
    }

    public function handle(Request $request, FormFlowStepData $step, array $context = []): array
    {
        $input = $request->validate([
            'data.otp_code' => ['required', 'string', 'min:4', 'max:10'],
        ]);
        $referenceId = $this->referenceId($context);
        $mobile = $this->mobile($referenceId);
        $this->ensureMobileIsAvailable($mobile);
        $challengeReference = Session::get("otp_challenge.{$referenceId}.reference");

        if (! is_string($challengeReference) || $challengeReference === '') {
            throw ValidationException::withMessages([
                'otp_code' => ['Send a verification code before continuing.'],
            ]);
        }

        $result = $this->gateway->verify($challengeReference, $input['data']['otp_code']);

        if (! $result->ok) {
            throw ValidationException::withMessages([
                'otp_code' => [$this->verificationFailureMessage($result->reason)],
            ]);
        }

        if (! $result->proof instanceof OtpVerificationProofData) {
            throw ValidationException::withMessages([
                'otp_code' => ['The verification provider did not return a valid proof.'],
            ]);
        }

        Session::forget("otp_challenge.{$referenceId}");
        Session::forget("otp_delivery.{$referenceId}");

        return new OtpData(
            mobile: $mobile,
            verified_at: $result->proof->verified_at,
            reference_id: $referenceId,
            verification_reference: $result->proof->reference,
            verification_purpose: $result->proof->purpose,
        )->toArray();
    }

    public function validate(array $data, array $rules): bool
    {
        return true;
    }

    public function render(FormFlowStepData $step, array $context = [])
    {
        $referenceId = $this->referenceId($context);
        $mobile = $this->mobile($referenceId);
        $this->ensureMobileIsAvailable($mobile);
        $challenge = Session::get("otp_challenge.{$referenceId}");

        return Inertia::render('form-flow/otp/OtpCapturePage', [
            'flow_id' => $context['flow_id'] ?? null,
            'step' => (string) ($context['step_index'] ?? 0),
            'mobile' => $mobile,
            'challenge_status' => is_array($challenge) ? ($challenge['status'] ?? 'requested') : 'idle',
            'config' => array_merge([
                'max_resends' => config('otp-handler.max_resends', 3),
                'resend_cooldown' => config('otp-handler.resend_cooldown', 30),
                'digits' => 6,
            ], $step->config),
            'ui_variant' => $step->config['ui_variant'] ?? config('form-flow.ui.variant', 'default'),
        ]);
    }

    /**
     * @return array{status: string, expires_in: int, replayed: bool}
     */
    public function requestChallenge(FormFlowStepData $step, array $context): array
    {
        $referenceId = $this->referenceId($context);
        $mobile = $this->mobile($referenceId);
        $this->ensureMobileIsAvailable($mobile);
        $challenge = $this->gateway->create(new OtpChallengeRequestData(
            mobile: $mobile,
            purpose: (string) ($step->config['purpose'] ?? 'form_flow.otp'),
            client_reference: "form-flow:{$referenceId}:step:".($context['step_index'] ?? 0),
        ));

        Session::put("otp_challenge.{$referenceId}", [
            'reference' => $challenge->reference,
            'status' => $challenge->status,
            'expires_in' => $challenge->expires_in,
        ]);
        Session::put("otp_delivery.{$referenceId}", [
            'resends' => 0,
            'sent_at' => now()->timestamp,
        ]);

        return [
            'status' => $challenge->status,
            'expires_in' => $challenge->expires_in,
            'replayed' => $challenge->replayed,
        ];
    }

    /**
     * @return array{status: string, expires_in: int, resent: true}
     */
    public function resendChallenge(FormFlowStepData $step, array $context): array
    {
        $referenceId = $this->referenceId($context);
        $deliveryKey = "otp_delivery.{$referenceId}";
        $delivery = Session::get($deliveryKey, ['resends' => 0, 'sent_at' => 0]);
        $challengeReference = Session::get("otp_challenge.{$referenceId}.reference");

        if (! is_string($challengeReference) || $challengeReference === '') {
            throw ValidationException::withMessages([
                'otp_code' => ['Send a verification code before requesting another one.'],
            ]);
        }

        if ((int) $delivery['resends'] >= (int) config('otp-handler.max_resends', 3)) {
            throw ValidationException::withMessages(['otp_code' => ['The resend limit has been reached.']]);
        }

        $retryAfter = (int) config('otp-handler.resend_cooldown', 30)
            - (now()->timestamp - (int) $delivery['sent_at']);

        if ($retryAfter > 0) {
            throw ValidationException::withMessages([
                'otp_code' => [sprintf('Wait %d seconds before requesting another code.', $retryAfter)],
            ]);
        }

        $challenge = $this->gateway->resend($challengeReference);
        Session::put("otp_challenge.{$referenceId}", [
            'reference' => $challenge->reference,
            'status' => $challenge->status,
            'expires_in' => $challenge->expires_in,
        ]);
        Session::put($deliveryKey, [
            'resends' => (int) $delivery['resends'] + 1,
            'sent_at' => now()->timestamp,
        ]);

        return ['status' => $challenge->status, 'expires_in' => $challenge->expires_in, 'resent' => true];
    }

    public function getConfigSchema(): array
    {
        return [
            'purpose' => 'nullable|string|max:64',
            'max_resends' => 'integer|min:1|max:10',
            'resend_cooldown' => 'integer|min:10|max:120',
            'digits' => 'integer|in:4,5,6',
            'ui_variant' => 'nullable|string|in:default,compact,immersive',
        ];
    }

    protected function mobile(string $flowId): string
    {
        $flowState = Session::get("form_flow.{$flowId}");

        foreach (($flowState['collected_data'] ?? []) as $stepData) {
            if (is_array($stepData) && is_string($stepData['mobile'] ?? null)) {
                return $stepData['mobile'];
            }
        }

        return '';
    }

    protected function ensureMobileIsAvailable(string $mobile): void
    {
        if ($mobile === '') {
            throw ValidationException::withMessages([
                'mobile' => ['A mobile number must be collected before OTP verification.'],
            ]);
        }
    }

    private function referenceId(array $context): string
    {
        return (string) ($context['flow_id'] ?? $context['reference_id'] ?? 'unknown');
    }

    private function verificationFailureMessage(string $reason): string
    {
        return match ($reason) {
            'invalid_code' => 'The verification code is incorrect.',
            'expired' => 'The verification code has expired.',
            'locked' => 'Too many failed attempts. Request another code.',
            'not_ready' => 'The code is still being delivered. Try again shortly.',
            'delivery_failed' => 'The code could not be delivered. Request another code.',
            'not_found' => 'The verification session was not found.',
            default => 'Verification failed. Request another code and try again.',
        };
    }
}
