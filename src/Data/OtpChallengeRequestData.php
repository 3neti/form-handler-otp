<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Data;

use Spatie\LaravelData\Data;

class OtpChallengeRequestData extends Data
{
    public function __construct(
        public string $mobile,
        public string $purpose,
        public ?string $client_reference = null,
    ) {}
}
