<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Console;

use Illuminate\Console\Command;

/**
 * Install OTP Handler Command
 *
 * Installs required UI dependencies and publishes assets.
 */
class InstallOtpHandlerCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'otp-handler:install {--force : Overwrite existing files}';

    /**
     * The console command description.
     */
    protected $description = 'Install OTP handler UI dependencies and assets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Installing OTP Handler...');

        // Publish Vue components
        $this->publishAssets();

        $this->newLine();
        $this->info('✓ OTP Handler installed successfully!');
        $this->line('  Run "npm run build" to compile frontend assets.');

        return self::SUCCESS;
    }

    /**
     * Publish package assets
     */
    protected function publishAssets(): void
    {
        $this->line('  • Publishing Vue components...');

        $this->call('vendor:publish', [
            '--tag' => 'otp-handler-stubs',
            '--force' => $this->option('force'),
        ]);
    }
}
