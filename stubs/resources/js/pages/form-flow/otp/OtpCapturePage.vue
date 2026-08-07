<script setup lang="ts">
import { Head, router, useHttp } from "@inertiajs/vue3";
import { computed, onMounted, ref, watch } from "vue";
import {
  resend as resendOtpChallenge,
  store as sendOtpChallenge,
} from "@/actions/LBHurtado/FormHandlerOtp/Http/Controllers/OtpChallengeController";
import { updateStep } from "@/actions/LBHurtado/FormFlowManager/Http/Controllers/FormFlowController";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import InputError from "@/components/InputError.vue";
import FormFlowActions from "@/pages/form-flow/core/components/FormFlowActions.vue";
import FormFlowScreen from "@/pages/form-flow/core/components/FormFlowScreen.vue";
import type { FormFlowUiVariant } from "@/pages/form-flow/core/components/formFlowUiVariant";

interface Props {
  flow_id: string;
  step: string;
  mobile: string;
  challenge_status: string;
  ui_variant?: FormFlowUiVariant | string | null;
  preview_mode?: boolean;
  config: {
    max_resends: number;
    resend_cooldown: number;
    digits: number;
  };
}

const props = defineProps<Props>();

const sendRequest = useHttp({});
const resendRequest = useHttp({});

const otpCode = ref("");
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const cooldown = ref(0);
const cooldownTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const cooldownInterval = ref<ReturnType<typeof setInterval> | null>(null);
const isSending = ref(false);
const resendMessage = ref("");
const resendCount = ref(0);
const challengeStatus = ref(props.challenge_status);

const MAX_RESENDS = computed(() => props.config.max_resends);
const COOLDOWN_SECONDS = computed(() => props.config.resend_cooldown);

const normalizedOtp = computed(() => {
  return String(otpCode.value ?? "")
    .replace(/\D/g, "")
    .slice(0, props.config.digits);
});

const canSubmit = computed(() => {
  return (
    challengeStatus.value !== "idle" &&
    !processing.value &&
    normalizedOtp.value.length === props.config.digits
  );
});

const codeRequested = computed(() => challengeStatus.value !== "idle");

function updateOtp(value: string | number) {
  otpCode.value = String(value ?? "")
    .replace(/\D/g, "")
    .slice(0, props.config.digits);
}

function submit() {
  if (props.preview_mode) return;
  if (!canSubmit.value) return;

  processing.value = true;
  errors.value = {};

  router.post(
    updateStep.url({ flow_id: props.flow_id, step: props.step }),
    {
      data: {
        otp_code: normalizedOtp.value,
      },
    },
    {
      preserveScroll: true,
      onError: (pageErrors) => {
        errors.value = pageErrors;
      },
      onFinish: () => {
        processing.value = false;
      },
    },
  );
}

async function sendOtp() {
  if (props.preview_mode) return;
  resendMessage.value = "";

  const response = await sendRequest.post(
    sendOtpChallenge.url({ flowId: props.flow_id, step: props.step }),
  );

  challengeStatus.value = String(response.data?.status ?? "requested");
  resendMessage.value = "Verification code requested.";
  startCooldown();
  otpInputRef.value?.focus();
}

async function resendOtp() {
  if (props.preview_mode) return;
  if (cooldown.value > 0 || resendCount.value >= MAX_RESENDS.value) {
    return;
  }

  isSending.value = true;
  resendMessage.value = "";

  try {
    const response = await resendRequest.post(
      resendOtpChallenge.url({ flowId: props.flow_id, step: props.step }),
    );
    challengeStatus.value = String(response.data?.status ?? "requested");
    resendCount.value++;
    resendMessage.value = "Verification code requested again.";
    startCooldown();
  } finally {
    isSending.value = false;
  }
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
    resendMessage.value = "";
  }, 5000);
}

const hasOtpError = ref(false);

// Watch for OTP validation error once
watch(
  () => errors.value.otp_code,
  (error) => {
    if (error && !hasOtpError.value) {
      hasOtpError.value = true;
    }
  },
);

// Auto-focus OTP input on mount
const otpInputRef = ref<HTMLInputElement | null>(null);
onMounted(() => {
  if (props.preview_mode) return;
  otpInputRef.value?.focus();
});
</script>

<template>
  <FormFlowScreen
    title="OTP Verification"
    :description="`Enter the ${config.digits}-digit code sent to your mobile`"
    :variant="ui_variant"
    inner-class="max-w-md"
  >
    <Head title="OTP Verification" />

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
          :model-value="otpCode"
          type="text"
          :maxlength="config.digits"
          inputmode="numeric"
          pattern="[0-9]*"
          autocomplete="one-time-code"
          placeholder="Enter OTP"
          :disabled="!codeRequested"
          required
          @update:model-value="updateOtp"
        />

        <div class="flex items-center justify-between text-sm min-h-[1.25rem]">
          <!-- Error message on the left -->
          <div class="text-red-600">
            <InputError :message="errors.otp_code" />
          </div>

          <!-- Show "OTP sent" initially; hide after first error -->
          <div v-if="!codeRequested" class="text-gray-500 dark:text-gray-400">
            Send a code to continue.
          </div>

          <!-- Show Resend OTP only after error -->
          <div
            v-else-if="resendCount < MAX_RESENDS"
            class="text-blue-600 hover:underline cursor-pointer text-right dark:text-blue-400"
            @click="resendOtp"
            :class="{
              'opacity-50 pointer-events-none': isSending || cooldown > 0,
            }"
          >
            <template v-if="cooldown > 0"> Resend in {{ cooldown }}s </template>
            <template v-else> Resend OTP </template>
          </div>
        </div>

        <!-- Resend confirmation message -->
        <div
          v-if="resendMessage"
          class="mt-1 text-sm text-gray-500 text-right dark:text-gray-400"
        >
          {{ resendMessage }}
        </div>

        <!-- Max resend reached -->
        <div
          v-if="resendCount >= MAX_RESENDS"
          class="mt-1 text-sm text-red-500"
        >
          You have reached the maximum number of resends.
        </div>
      </div>

      <!-- Submit Button -->
      <div class="grid gap-3">
        <button
          v-if="!codeRequested"
          type="button"
          class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="sendRequest.processing"
          @click="sendOtp"
        >
          {{ sendRequest.processing ? "Requesting…" : "Send Verification Code" }}
        </button>
        <FormFlowActions
          v-else
          :variant="ui_variant"
          :show-secondary="false"
          primary-label="Verify OTP"
          primary-type="button"
          :primary-disabled="!canSubmit"
          :processing="processing"
          @primary="submit"
        />
        <p
          v-if="errors.otp_code || errors['data.otp_code']"
          class="text-sm text-destructive"
        >
          {{ errors.otp_code || errors["data.otp_code"] }}
        </p>
      </div>
    </form>
  </FormFlowScreen>
</template>
