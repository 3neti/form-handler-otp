<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Services;

use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeData;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationResultData;
use RuntimeException;

class UnavailableOtpChallengeGateway implements OtpChallengeGateway
{
    public function create(OtpChallengeRequestData $request): OtpChallengeData
    {
        throw $this->exception();
    }

    public function status(string $challengeReference): OtpChallengeData
    {
        throw $this->exception();
    }

    public function resend(string $challengeReference): OtpChallengeData
    {
        throw $this->exception();
    }

    public function verify(string $challengeReference, string $code): OtpVerificationResultData
    {
        throw $this->exception();
    }

    private function exception(): RuntimeException
    {
        return new RuntimeException('Identity OTP delivery is not configured.');
    }
}
