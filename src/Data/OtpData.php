<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Data;

use Spatie\LaravelData\Data;

class OtpData extends Data
{
    public function __construct(
        public string $mobile,
        public string $verified_at,
        public string $reference_id,
        public string $verification_reference,
        public string $verification_purpose,
    ) {}
}
