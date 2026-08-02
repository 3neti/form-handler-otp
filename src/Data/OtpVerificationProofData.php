<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Data;

use Spatie\LaravelData\Data;

class OtpVerificationProofData extends Data
{
    public function __construct(
        public string $reference,
        public string $purpose,
        public string $verified_at,
    ) {}
}
