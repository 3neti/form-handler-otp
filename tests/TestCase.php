<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Tests;

use LBHurtado\FormHandlerOtp\OtpHandlerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            OtpHandlerServiceProvider::class,
        ];
    }
    
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // Configure OTP handler
        $app['config']->set('otp-handler.label', 'Test App');
        $app['config']->set('otp-handler.period', 600);
        $app['config']->set('otp-handler.digits', 4);
        $app['config']->set('otp-handler.cache_prefix', 'otp');
        $app['config']->set('otp-handler.max_resends', 3);
        $app['config']->set('otp-handler.resend_cooldown', 30);
    }
}
