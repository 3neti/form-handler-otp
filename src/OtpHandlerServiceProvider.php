<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp;

use Illuminate\Support\ServiceProvider;
use LBHurtado\FormHandlerOtp\Console\InstallOtpHandlerCommand;

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
        
        // Register OtpHandler as singleton
        $this->app->singleton(OtpHandler::class, function ($app) {
            return new OtpHandler();
        });
    }
    
    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \LBHurtado\FormHandlerOtp\Console\TestOtpCommand::class,
                InstallOtpHandlerCommand::class,
            ]);
        }
        
        // Register test routes (only in local/testing)
        if (!$this->app->isProduction()) {
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
