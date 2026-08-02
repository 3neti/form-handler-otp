<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Services;

use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;

/**
 * @deprecated Resolve OtpChallengeGateway from the container instead.
 */
class TxtcmdrClient extends TxtcmdrOtpChallengeGateway
{
    /**
     * @return array<string, mixed>
     */
    public function requestOtp(string $mobile, ?string $externalRef = null): array
    {
        $challenge = $this->create(new OtpChallengeRequestData(
            mobile: $mobile,
            purpose: 'verification',
            client_reference: $externalRef,
        ));

        return [
            'verification_id' => $challenge->reference,
            'status' => $challenge->status,
            'expires_in' => $challenge->expires_in,
            'replayed' => $challenge->replayed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyOtp(string $verificationId, string $code): array
    {
        return $this->verify($verificationId, $code)->toArray();
    }
}
