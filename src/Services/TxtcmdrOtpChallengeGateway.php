<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeData;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationProofData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationResultData;
use UnexpectedValueException;

class TxtcmdrOtpChallengeGateway implements OtpChallengeGateway
{
    private string $baseUrl;

    private string $apiToken;

    public function __construct()
    {
        $apiToken = config('otp-handler.txtcmdr.api_token');

        if (! is_string($apiToken) || trim($apiToken) === '') {
            throw new InvalidArgumentException('Txtcmdr API token is not configured.');
        }

        $this->baseUrl = rtrim((string) config('otp-handler.txtcmdr.base_url'), '/');
        $this->apiToken = trim($apiToken);
    }

    public function create(OtpChallengeRequestData $request): OtpChallengeData
    {
        $result = $this->request()->post('/api/v1/otp/challenges', $request->toArray())->throw()->json();

        return $this->challenge($result);
    }

    public function status(string $challengeReference): OtpChallengeData
    {
        $result = $this->request()->get("/api/v1/otp/challenges/{$challengeReference}")->throw()->json();

        return $this->challenge($result);
    }

    public function resend(string $challengeReference): OtpChallengeData
    {
        $result = $this->request()->post("/api/v1/otp/challenges/{$challengeReference}/resend")->throw()->json();
        $challenge = is_array($result) ? ($result['challenge'] ?? null) : null;

        if (! is_array($challenge) || ($result['ok'] ?? false) !== true) {
            throw new UnexpectedValueException('Txtcmdr returned an invalid OTP resend response.');
        }

        return $this->challenge($challenge);
    }

    public function verify(string $challengeReference, string $code): OtpVerificationResultData
    {
        $result = $this->request()
            ->post("/api/v1/otp/challenges/{$challengeReference}/verify", ['code' => $code])
            ->throw()
            ->json();

        if (! is_array($result) || ! is_bool($result['ok'] ?? null) || ! is_string($result['reason'] ?? null)) {
            throw new UnexpectedValueException('Txtcmdr returned an invalid OTP verification response.');
        }

        $proof = $result['proof'] ?? null;

        return new OtpVerificationResultData(
            ok: $result['ok'],
            reason: $result['reason'],
            proof: is_array($proof) ? new OtpVerificationProofData(
                reference: $this->requiredString($proof, 'verification_id', 'verification proof'),
                purpose: $this->requiredString($proof, 'purpose', 'verification proof'),
                verified_at: $this->requiredString($proof, 'verified_at', 'verification proof'),
            ) : null,
            attempts: is_int($result['attempts'] ?? null) ? $result['attempts'] : null,
            status: is_string($result['status'] ?? null) ? $result['status'] : null,
        );
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->connectTimeout(max(1, (int) config('otp-handler.txtcmdr.connect_timeout', 5)))
            ->timeout(max(1, (int) config('otp-handler.txtcmdr.timeout', 15)))
            ->withOptions(['verify' => (bool) config('otp-handler.txtcmdr.verify_ssl', true)])
            ->withToken($this->apiToken)
            ->acceptJson();
    }

    private function challenge(mixed $result): OtpChallengeData
    {
        if (! is_array($result)) {
            throw new UnexpectedValueException('Txtcmdr returned an invalid OTP challenge response.');
        }

        $expiresIn = $result['expires_in'] ?? null;
        $replayed = $result['replayed'] ?? false;

        if (! is_int($expiresIn) || ! is_bool($replayed)) {
            throw new UnexpectedValueException('Txtcmdr returned an invalid OTP challenge response.');
        }

        return new OtpChallengeData(
            reference: $this->requiredString($result, 'verification_id', 'challenge'),
            status: $this->requiredString($result, 'status', 'challenge'),
            expires_in: $expiresIn,
            replayed: $replayed,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requiredString(array $data, string $key, string $context): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new UnexpectedValueException("Txtcmdr returned an invalid OTP {$context} response.");
        }

        return $value;
    }
}
