<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormHandlerOtp\Data\OtpData;
use LBHurtado\FormHandlerOtp\Services\TxtcmdrClient;

/**
 * OTP Handler
 *
 * Handles OTP generation, SMS delivery, and validation for form flows.
 */
class OtpHandler implements FormHandlerInterface
{
    public function getName(): string
    {
        return 'otp';
    }

    public function handle(Request $request, FormFlowStepData $step, array $context = []): array
    {
        // Extract data from 'data' key if present (from form submission)
        $inputData = $request->input('data', $request->all());

        // Get reference ID and mobile from context
        $referenceId = $context['flow_id'] ?? $context['reference_id'] ?? 'unknown';
        $mobile = $this->getMobileFromSession($referenceId);

        // Check if this is a resend request
        if ($request->input('resend')) {
            $this->ensureMobileIsAvailable($mobile);

            return $this->handleResend($referenceId, $mobile);
        }

        // Validate submitted OTP
        $validated = validator($inputData, [
            'otp_code' => 'required|string|min:4|max:10',
        ])->validate();

        // Get verification_id from session
        $verificationId = Session::get("otp_verification.{$referenceId}");

        if (! $verificationId) {
            throw ValidationException::withMessages([
                'otp_code' => ['Verification session expired. Please request a new OTP.'],
            ]);
        }

        // Verify OTP via txtcmdr API
        $client = new TxtcmdrClient;
        $result = $client->verifyOtp($verificationId, $validated['otp_code']);

        if (! $result['ok']) {
            $errorMessages = [
                'invalid_code' => 'The OTP code is incorrect.',
                'expired' => 'The OTP code has expired.',
                'locked' => 'Too many failed attempts. Please request a new OTP.',
                'already_verified' => 'This OTP code has already been used.',
                'not_found' => 'Verification session not found.',
            ];

            $message = $errorMessages[$result['reason']] ?? 'OTP verification failed.';

            throw ValidationException::withMessages([
                'otp_code' => [$message],
            ]);
        }

        // Clear session
        Session::forget("otp_verification.{$referenceId}");
        Session::forget("otp_delivery.{$referenceId}");

        // Return validated data
        return OtpData::from([
            'mobile' => $mobile,
            'otp_code' => $validated['otp_code'],
            'verified_at' => now()->toIso8601String(),
            'reference_id' => $referenceId,
        ])->toArray();
    }

    public function validate(array $data, array $rules): bool
    {
        // Validation handled in handle() method
        return true;
    }

    public function render(FormFlowStepData $step, array $context = [])
    {
        $referenceId = $context['flow_id'] ?? $context['reference_id'] ?? 'unknown';

        // Get mobile from collected data in session
        $mobile = $this->getMobileFromSession($referenceId);
        $this->ensureMobileIsAvailable($mobile);

        // Request OTP on first visit
        $sessionKey = "otp_verification.{$referenceId}";

        if (! Session::has($sessionKey)) {
            // Request OTP from txtcmdr API
            $client = new TxtcmdrClient;
            $result = $client->requestOtp($mobile, $referenceId);

            // Store verification_id in session
            Session::put($sessionKey, $result['verification_id']);
            Session::put("otp_delivery.{$referenceId}", [
                'resends' => 0,
                'sent_at' => now()->timestamp,
            ]);
        }

        // Render OTP capture page
        return Inertia::render('form-flow/otp/OtpCapturePage', [
            'flow_id' => $context['flow_id'] ?? null,
            'step' => (string) ($context['step_index'] ?? 0),
            'mobile' => $mobile,
            'config' => array_merge([
                'max_resends' => config('otp-handler.max_resends', 3),
                'resend_cooldown' => config('otp-handler.resend_cooldown', 30),
                'digits' => 6, // txtcmdr uses 6 digits
            ], $step->config),
            'ui_variant' => $step->config['ui_variant'] ?? config('form-flow.ui.variant', 'default'),
        ]);
    }

    public function getConfigSchema(): array
    {
        return [
            'max_resends' => 'integer|min:1|max:10',
            'resend_cooldown' => 'integer|min:10|max:120',
            'digits' => 'integer|in:4,5,6',
            'ui_variant' => 'nullable|string|in:default,compact,immersive',
        ];
    }

    /**
     * Get mobile number from collected data in session
     */
    protected function getMobileFromSession(string $flowId): string
    {
        $flowState = Session::get("form_flow.{$flowId}");

        if (! $flowState || ! isset($flowState['collected_data'])) {
            return '';
        }

        // Look for mobile in wallet_info step (or any step that has it)
        $collectedData = $flowState['collected_data'];

        foreach ($collectedData as $stepData) {
            if (isset($stepData['mobile'])) {
                return $stepData['mobile'];
            }
        }

        return '';
    }

    /**
     * Handle OTP resend request
     */
    protected function handleResend(string $referenceId, string $mobile): array
    {
        $deliveryKey = "otp_delivery.{$referenceId}";
        $delivery = Session::get($deliveryKey, [
            'resends' => 0,
            'sent_at' => 0,
        ]);
        $maxResends = (int) config('otp-handler.max_resends', 3);
        $cooldown = (int) config('otp-handler.resend_cooldown', 30);

        if ((int) $delivery['resends'] >= $maxResends) {
            throw ValidationException::withMessages([
                'resend' => ['The maximum number of OTP resend attempts has been reached.'],
            ]);
        }

        $retryAfter = $cooldown - (now()->timestamp - (int) $delivery['sent_at']);

        if ($retryAfter > 0) {
            throw ValidationException::withMessages([
                'resend' => [sprintf('Please wait %d seconds before requesting another OTP.', $retryAfter)],
            ]);
        }

        // Request new OTP from txtcmdr API
        $client = new TxtcmdrClient;
        $result = $client->requestOtp($mobile, $referenceId);

        // Update verification_id in session
        Session::put("otp_verification.{$referenceId}", $result['verification_id']);
        Session::put($deliveryKey, [
            'resends' => (int) $delivery['resends'] + 1,
            'sent_at' => now()->timestamp,
        ]);

        return ['resent' => true];
    }

    protected function ensureMobileIsAvailable(string $mobile): void
    {
        if ($mobile !== '') {
            return;
        }

        throw ValidationException::withMessages([
            'mobile' => ['A mobile number must be collected before OTP verification.'],
        ]);
    }
}
