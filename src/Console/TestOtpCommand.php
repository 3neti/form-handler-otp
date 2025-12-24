<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Console;

use Illuminate\Console\Command;
use LBHurtado\FormHandlerOtp\Services\TxtcmdrClient;

class TestOtpCommand extends Command
{
    protected $signature = 'test:otp 
                            {mobile=09173011987 : Mobile number to send OTP to}
                            {--verify= : Verify an OTP code with verification ID (format: verification_id:code)}';

    protected $description = 'Test OTP request and verification via txtcmdr API';

    public function handle(): int
    {
        $mobile = $this->argument('mobile');
        $verifyOption = $this->option('verify');
        
        $this->info("📱 Testing OTP Handler (txtcmdr API)");
        $this->newLine();
        
        // Display configuration
        $this->line("Configuration:");
        $this->line("  API URL: " . config('otp-handler.txtcmdr.base_url'));
        $this->line("  Timeout: " . config('otp-handler.txtcmdr.timeout') . " seconds");
        $this->newLine();
        
        // If verifying
        if ($verifyOption) {
            [$verificationId, $code] = explode(':', $verifyOption, 2);
            return $this->verifyOtp($verificationId, $code);
        }
        
        // Request OTP
        $this->info("📤 Requesting OTP for: {$mobile}");
        
        try {
            $client = new TxtcmdrClient();
            $externalRef = 'test-' . now()->format('YmdHis');
            
            $result = $client->requestOtp($mobile, $externalRef);
            
            $this->info("✅ OTP Requested Successfully");
            $this->line("   Verification ID: {$result['verification_id']}");
            $this->line("   Expires in: {$result['expires_in']} seconds");
            
            if (isset($result['dev_code'])) {
                $this->warn("   Dev Code: {$result['dev_code']} (testing only)");
            }
            
            $this->newLine();
            
            // Show verification instructions
            $this->comment("To verify the OTP:");
            $this->line("php artisan test:otp {$mobile} --verify={$result['verification_id']}:YOUR_CODE");
            $this->newLine();
            
            if (isset($result['dev_code'])) {
                $this->comment("For testing, use the dev code:");
                $this->line("php artisan test:otp {$mobile} --verify={$result['verification_id']}:{$result['dev_code']}");
            }
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Failed: " . $e->getMessage());
            
            if ($this->output->isVerbose()) {
                $this->newLine();
                $this->line('Stack trace:');
                $this->line($e->getTraceAsString());
            }
            
            return self::FAILURE;
        }
    }
    
    protected function verifyOtp(string $verificationId, string $code): int
    {
        $this->info("🔍 Verifying OTP: {$code}");
        $this->line("   Verification ID: {$verificationId}");
        $this->newLine();
        
        try {
            $client = new TxtcmdrClient();
            $result = $client->verifyOtp($verificationId, $code);
            
            if ($result['ok']) {
                $this->info("✅ OTP is valid!");
                if (isset($result['status'])) {
                    $this->line("   Status: {$result['status']}");
                }
                return self::SUCCESS;
            } else {
                $this->error("❌ OTP verification failed");
                $this->line("   Reason: {$result['reason']}");
                if (isset($result['attempts'])) {
                    $this->line("   Attempts: {$result['attempts']}");
                }
                if (isset($result['status'])) {
                    $this->line("   Status: {$result['status']}");
                }
                return self::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Verification failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
