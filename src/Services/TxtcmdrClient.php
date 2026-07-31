<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * txtcmdr OTP API Client
 *
 * Handles OTP request and verification via txtcmdr external API.
 */
class TxtcmdrClient
{
    protected string $baseUrl;

    protected string $apiToken;

    protected int $timeout;

    public function __construct()
    {
        $baseUrl = config('otp-handler.txtcmdr.base_url', 'http://txtcmdr.test');
        $apiToken = config('otp-handler.txtcmdr.api_token');

        if (! is_string($apiToken) || trim($apiToken) === '') {
            throw new InvalidArgumentException('Txtcmdr API token is not configured.');
        }

        $this->baseUrl = rtrim((string) $baseUrl, '/');
        $this->apiToken = $apiToken;
        $this->timeout = max(1, (int) config('otp-handler.txtcmdr.timeout', 30));
    }

    /**
     * Request a new OTP code
     *
     * @param  string  $mobile  Mobile number in E.164 format
     * @param  string|null  $externalRef  Your reference ID (optional)
     * @return array ['verification_id' => string, 'expires_in' => int, 'dev_code' => string|null]
     *
     * @throws RequestException
     */
    public function requestOtp(string $mobile, ?string $externalRef = null): array
    {
        $response = Http::timeout($this->timeout)
            ->withToken($this->apiToken)
            ->acceptJson()
            ->post("{$this->baseUrl}/api/otp/request", [
                'mobile' => $mobile,
                'purpose' => 'verification',
                'external_ref' => $externalRef,
            ]);

        $response->throw();

        $result = $response->json();

        if (! is_array($result) || ! is_string($result['verification_id'] ?? null)) {
            throw new UnexpectedValueException('Txtcmdr returned an invalid OTP request response.');
        }

        return $result;
    }

    /**
     * Verify an OTP code
     *
     * @param  string  $verificationId  UUID from requestOtp
     * @param  string  $code  OTP code entered by user
     * @return array ['ok' => bool, 'reason' => string, 'attempts' => int|null, 'status' => string|null]
     *
     * @throws RequestException
     */
    public function verifyOtp(string $verificationId, string $code): array
    {
        $response = Http::timeout($this->timeout)
            ->withToken($this->apiToken)
            ->acceptJson()
            ->post("{$this->baseUrl}/api/otp/verify", [
                'verification_id' => $verificationId,
                'code' => $code,
            ]);

        $response->throw();

        $result = $response->json();

        if (! is_array($result) || ! is_bool($result['ok'] ?? null)) {
            throw new UnexpectedValueException('Txtcmdr returned an invalid OTP verification response.');
        }

        return $result;
    }
}
