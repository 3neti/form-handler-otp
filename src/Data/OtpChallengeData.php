<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Data;

use Spatie\LaravelData\Data;

class OtpChallengeData extends Data
{
    public function __construct(
        public string $reference,
        public string $status,
        public int $expires_in,
        public bool $replayed = false,
    ) {}
}
