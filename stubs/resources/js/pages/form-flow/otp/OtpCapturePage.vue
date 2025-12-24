<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';

interface Props {
    flow_id: string;
    step: string;
    mobile: string;
    config: {
        max_resends: number;
        resend_cooldown: number;
        digits: number;
    };
}

const props = defineProps<Props>();

const form = useForm({
    otp_code: '',
});

const cooldown = ref(0);
const cooldownTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const cooldownInterval = ref<ReturnType<typeof setInterval> | null>(null);
const isSending = ref(false);
const resendMessage = ref('');
const resendCount = ref(0);

const MAX_RESENDS = computed(() => props.config.max_resends);
const COOLDOWN_SECONDS = computed(() => props.config.resend_cooldown);

function submit() {
    form.post(`/form-flow/${props.flow_id}/step/${props.step}`, {
        data: {
            otp_code: form.otp_code,
        },
        preserveScroll: true,
    });
}

function resendOtp() {
    if (cooldown.value > 0 || resendCount.value >= MAX_RESENDS.value) {
        return;
    }

    isSending.value = true;
    resendMessage.value = '';

    router.post(
        `/form-flow/${props.flow_id}/step/${props.step}`,
        {
            resend: true,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                resendCount.value++;
                resendMessage.value = 'OTP resent successfully.';
                startCooldown();
            },
            onError: () => {
                resendMessage.value = 'Failed to resend OTP. Try again.';
            },
            onFinish: () => {
                isSending.value = false;
            },
        }
    );
}

function startCooldown() {
    // Clear any existing timers
    if (cooldownInterval.value) clearInterval(cooldownInterval.value);
    if (cooldownTimer.value) clearTimeout(cooldownTimer.value);

    cooldown.value = COOLDOWN_SECONDS.value;

    // Start countdown
    cooldownInterval.value = setInterval(() => {
        cooldown.value--;
        if (cooldown.value <= 0 && cooldownInterval.value) {
            clearInterval(cooldownInterval.value);
            cooldownInterval.value = null;
        }
    }, 1000);

    // Auto-hide message after 5 seconds
    cooldownTimer.value = setTimeout(() => {
        resendMessage.value = '';
    }, 5000);
}

const hasOtpError = ref(false);

// Watch for OTP validation error once
watch(
    () => form.errors.otp_code,
    (error) => {
        if (error && !hasOtpError.value) {
            hasOtpError.value = true;
        }
    }
);

// Auto-focus OTP input on mount
const otpInputRef = ref<HTMLInputElement | null>(null);
onMounted(() => {
    otpInputRef.value?.focus();
});
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 px-4">
        <Head title="OTP Verification" />

        <div class="w-full max-w-md">
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 space-y-6">
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        OTP Verification
                    </h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Enter the {{ config.digits }}-digit code sent to your mobile
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Mobile (Read-only) -->
                    <div class="flex flex-col gap-1">
                        <Label for="mobile">Mobile Number</Label>
                        <input
                            id="mobile"
                            type="text"
                            :value="mobile"
                            readonly
                            tabindex="-1"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 px-3 py-2 text-gray-700 shadow-sm dark:bg-gray-700 dark:text-gray-300"
                        />
                    </div>

                    <!-- OTP Input -->
                    <div class="flex flex-col gap-1">
                        <Label for="otp">One-Time Password</Label>
                        <Input
                            id="otp"
                            ref="otpInputRef"
                            v-model="form.otp_code"
                            type="text"
                            :maxlength="config.digits"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            placeholder="Enter OTP"
                            required
                        />

                        <div class="flex items-center justify-between text-sm min-h-[1.25rem]">
                            <!-- Error message on the left -->
                            <div class="text-red-600">
                                <InputError :message="form.errors.otp_code" />
                            </div>

                            <!-- Show "OTP sent" initially; hide after first error -->
                            <div v-if="!hasOtpError" class="text-gray-500 dark:text-gray-400">
                                OTP has been sent.
                            </div>

                            <!-- Show Resend OTP only after error -->
                            <div
                                v-else-if="resendCount < MAX_RESENDS"
                                class="text-blue-600 hover:underline cursor-pointer text-right dark:text-blue-400"
                                @click="resendOtp"
                                :class="{ 'opacity-50 pointer-events-none': isSending || cooldown > 0 }"
                            >
                                <template v-if="cooldown > 0">
                                    Resend in {{ cooldown }}s
                                </template>
                                <template v-else>
                                    Resend OTP
                                </template>
                            </div>
                        </div>

                        <!-- Resend confirmation message -->
                        <div v-if="resendMessage" class="mt-1 text-sm text-gray-500 text-right dark:text-gray-400">
                            {{ resendMessage }}
                        </div>

                        <!-- Max resend reached -->
                        <div v-if="resendCount >= MAX_RESENDS" class="mt-1 text-sm text-red-500">
                            You have reached the maximum number of resends.
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="form.processing || form.otp_code.length !== config.digits"
                            class="w-full"
                        >
                            {{ form.processing ? 'Verifying…' : 'Verify OTP' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
