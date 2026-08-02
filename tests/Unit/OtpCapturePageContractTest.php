<?php

declare(strict_types=1);

it('uses explicit non-progressing challenge actions and never posts a resend through the form step', function (): void {
    $component = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/form-flow/otp/OtpCapturePage.vue');

    expect($component)
        ->toContain('useHttp')
        ->toContain('sendOtpChallenge.url')
        ->toContain('resendOtpChallenge.url')
        ->toContain('updateStep.url')
        ->toContain('Send Verification Code')
        ->not->toContain('`/form-flow/${props.flow_id}/step/${props.step}`')
        ->not->toContain('resend: true');
});
