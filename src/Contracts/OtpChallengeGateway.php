<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Contracts;

use LBHurtado\FormHandlerOtp\Data\OtpChallengeData;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationResultData;

interface OtpChallengeGateway
{
    public function create(OtpChallengeRequestData $request): OtpChallengeData;

    public function status(string $challengeReference): OtpChallengeData;

    public function resend(string $challengeReference): OtpChallengeData;

    public function verify(string $challengeReference, string $code): OtpVerificationResultData;
}
