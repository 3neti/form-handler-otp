<?php

use Illuminate\Support\Facades\Cache;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormHandlerOtp\Actions\GenerateOtp;
use LBHurtado\FormHandlerOtp\Actions\ValidateOtp;
use LBHurtado\FormHandlerOtp\OtpHandler;

it('implements FormHandlerInterface', function () {
    $handler = new OtpHandler();
    expect($handler)->toBeInstanceOf(FormHandlerInterface::class);
});

it('returns correct handler name', function () {
    $handler = new OtpHandler();
    expect($handler->getName())->toBe('otp');
});

it('generates valid OTP with correct format', function () {
    $generator = new GenerateOtp(
        cachePrefix: 'otp',
        period: 600,
        digits: 4
    );
    
    $result = $generator->execute('test-ref-123', '09171234567');
    
    expect($result)->toHaveKeys(['code', 'expires_at'])
        ->and($result['code'])->toMatch('/^\d{4}$/')
        ->and($result['expires_at'])->toBeString();
});

it('caches OTP secret for validation', function () {
    $generator = new GenerateOtp(
        cachePrefix: 'otp',
        period: 600,
        digits: 4
    );
    
    $generator->execute('test-ref-456', '09171234567');
    
    expect(Cache::has('otp.test-ref-456'))->toBeTrue();
});

it('validates correct OTP', function () {
    $generator = new GenerateOtp(
        cachePrefix: 'otp',
        period: 600,
        digits: 4
    );
    
    $result = $generator->execute('test-ref-789', '09171234567');
    
    $validator = new ValidateOtp(
        cachePrefix: 'otp',
        period: 600,
        digits: 4
    );
    
    $isValid = $validator->execute('test-ref-789', $result['code']);
    
    expect($isValid)->toBeTrue();
});

it('rejects incorrect OTP', function () {
    $generator = new GenerateOtp(
        cachePrefix: 'otp',
        period: 600,
        digits: 4
    );
    
    $generator->execute('test-ref-wrong', '09171234567');
    
    $validator = new ValidateOtp(
        cachePrefix: 'otp',
        period: 600,
        digits: 4
    );
    
    $isValid = $validator->execute('test-ref-wrong', '9999');
    
    expect($isValid)->toBeFalse();
});

it('clears cache after successful validation', function () {
    $generator = new GenerateOtp(
        cachePrefix: 'otp',
        period: 600,
        digits: 4
    );
    
    $result = $generator->execute('test-ref-clear', '09171234567');
    
    $validator = new ValidateOtp(
        cachePrefix: 'otp',
        period: 600,
        digits: 4
    );
    
    $validator->execute('test-ref-clear', $result['code']);
    
    expect(Cache::has('otp.test-ref-clear'))->toBeFalse();
});

it('returns false for expired/non-existent OTP', function () {
    $validator = new ValidateOtp(
        cachePrefix: 'otp',
        period: 600,
        digits: 4
    );
    
    $isValid = $validator->execute('non-existent-ref', '1234');
    
    expect($isValid)->toBeFalse();
});

it('has correct config schema', function () {
    $handler = new OtpHandler();
    $schema = $handler->getConfigSchema();
    
    expect($schema)->toHaveKeys(['max_resends', 'resend_cooldown', 'digits'])
        ->and($schema['max_resends'])->toBeString()
        ->and($schema['resend_cooldown'])->toBeString()
        ->and($schema['digits'])->toBeString();
});

it('validates OTP with different digit lengths', function () {
    // Test with 6 digits
    $generator = new GenerateOtp(
        cachePrefix: 'otp',
        period: 600,
        digits: 6
    );
    
    $result = $generator->execute('test-ref-six', '09171234567');
    
    expect($result['code'])->toMatch('/^\d{6}$/');
    
    $validator = new ValidateOtp(
        cachePrefix: 'otp',
        period: 600,
        digits: 6
    );
    
    $isValid = $validator->execute('test-ref-six', $result['code']);
    
    expect($isValid)->toBeTrue();
});

it('handler returns otp as name', function () {
    $handler = app(OtpHandler::class);
    expect($handler->getName())->toBe('otp');
});
