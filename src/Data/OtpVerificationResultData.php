<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Data;

use Spatie\LaravelData\Data;

class OtpVerificationResultData extends Data
{
    public function __construct(
        public bool $ok,
        public string $reason,
        public ?OtpVerificationProofData $proof = null,
        public ?int $attempts = null,
        public ?string $status = null,
    ) {}
}
