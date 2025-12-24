<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Data;

use Spatie\LaravelData\Data;

/**
 * OTP Data
 * 
 * Represents validated OTP data from form submission.
 */
class OtpData extends Data
{
    public function __construct(
        public string $mobile,
        public string $otp_code,
        public string $verified_at,
        public string $reference_id,
    ) {}
}
