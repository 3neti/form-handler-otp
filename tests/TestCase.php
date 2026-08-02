<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Tests;

use LBHurtado\FormHandlerOtp\OtpHandlerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            OtpHandlerServiceProvider::class,
            LaravelDataServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('inertia.testing.ensure_pages_exist', false);
        $app['config']->set('otp-handler.label', 'Test App');
        $app['config']->set('otp-handler.driver', 'txtcmdr');
        $app['config']->set('otp-handler.max_resends', 3);
        $app['config']->set('otp-handler.resend_cooldown', 30);
    }
}
