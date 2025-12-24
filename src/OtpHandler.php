<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
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
            return $this->handleResend($referenceId, $mobile);
        }
        
        // Validate submitted OTP
        $validated = validator($inputData, [
            'otp_code' => 'required|string|min:4|max:10',
        ])->validate();
        
        // Get verification_id from session
        $verificationId = Session::get("otp_verification.{$referenceId}");
        
        if (!$verificationId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'otp_code' => ['Verification session expired. Please request a new OTP.'],
            ]);
        }
        
        // Verify OTP via txtcmdr API
        $client = new TxtcmdrClient();
        $result = $client->verifyOtp($verificationId, $validated['otp_code']);
        
        if (!$result['ok']) {
            $errorMessages = [
                'invalid_code' => 'The OTP code is incorrect.',
                'expired' => 'The OTP code has expired.',
                'locked' => 'Too many failed attempts. Please request a new OTP.',
                'already_verified' => 'This OTP code has already been used.',
                'not_found' => 'Verification session not found.',
            ];
            
            $message = $errorMessages[$result['reason']] ?? 'OTP verification failed.';
            
            throw \Illuminate\Validation\ValidationException::withMessages([
                'otp_code' => [$message],
            ]);
        }
        
        // Clear session
        Session::forget("otp_verification.{$referenceId}");
        
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
        
        // Request OTP on first visit
        $sessionKey = "otp_verification.{$referenceId}";
        
        if (!Session::has($sessionKey)) {
            // Request OTP from txtcmdr API
            $client = new TxtcmdrClient();
            $result = $client->requestOtp($mobile, $referenceId);
            
            // Store verification_id in session
            Session::put($sessionKey, $result['verification_id']);
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
        ]);
    }
    
    public function getConfigSchema(): array
    {
        return [
            'max_resends' => 'integer|min:1|max:10',
            'resend_cooldown' => 'integer|min:10|max:120',
            'digits' => 'integer|in:4,5,6',
        ];
    }
    
    /**
     * Get mobile number from collected data in session
     */
    protected function getMobileFromSession(string $flowId): string
    {
        $flowState = Session::get("form_flow.{$flowId}");
        
        if (!$flowState || !isset($flowState['collected_data'])) {
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
        // Request new OTP from txtcmdr API
        $client = new TxtcmdrClient();
        $result = $client->requestOtp($mobile, $referenceId);
        
        // Update verification_id in session
        Session::put("otp_verification.{$referenceId}", $result['verification_id']);
        
        return ['resent' => true];
    }
}
