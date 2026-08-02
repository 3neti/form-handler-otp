<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp;

use Illuminate\Support\ServiceProvider;
use LBHurtado\FormHandlerOtp\Console\InstallOtpHandlerCommand;
use LBHurtado\FormHandlerOtp\Console\TestOtpCommand;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Services\TxtcmdrOtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Services\UnavailableOtpChallengeGateway;

/**
 * OTP Handler Service Provider
 *
 * Registers the OTP handler with the form flow system.
 */
class OtpHandlerServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/otp-handler.php',
            'otp-handler'
        );

        $this->app->singleton(OtpChallengeGateway::class, function ($app): OtpChallengeGateway {
            return match ($app['config']->get('otp-handler.driver')) {
                'txtcmdr' => $app->make(TxtcmdrOtpChallengeGateway::class),
                default => $app->make(UnavailableOtpChallengeGateway::class),
            };
        });

        $this->app->singleton(OtpHandler::class);
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallOtpHandlerCommand::class,
            ]);

            if ($this->app->environment(['local', 'testing'])) {
                $this->commands([
                    TestOtpCommand::class,
                ]);
            }
        }

        // Register test routes only in local and automated test environments.
        if ($this->app->environment(['local', 'testing'])) {
            $this->loadRoutesFrom(__DIR__.'/../routes/test.php');
        }

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/otp-handler.php' => config_path('otp-handler.php'),
        ], 'otp-handler-config');

        // Publish frontend assets (Vue components)
        $this->publishes([
            __DIR__.'/../stubs/resources/js/pages/form-flow/otp' => resource_path('js/pages/form-flow/otp'),
        ], 'otp-handler-stubs');

        // Auto-register handler with form-flow-manager
        $this->registerHandler();
    }

    /**
     * Register the OTP handler with form-flow-manager
     */
    protected function registerHandler(): void
    {
        // Get current handlers from config
        $handlers = config('form-flow.handlers', []);

        // Add otp handler
        $handlers['otp'] = OtpHandler::class;

        // Update config
        config(['form-flow.handlers' => $handlers]);
    }
}
