<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormHandlerOtp\OtpHandler;

class OtpChallengeController
{
    public function store(
        Request $request,
        string $flowId,
        int $step,
        FormFlowService $flows,
        OtpHandler $handler,
    ): JsonResponse {
        $stepData = $this->otpStep($flows, $flowId, $step);

        return response()->json($handler->requestChallenge($stepData, [
            'flow_id' => $flowId,
            'step_index' => $step,
        ]));
    }

    public function resend(
        Request $request,
        string $flowId,
        int $step,
        FormFlowService $flows,
        OtpHandler $handler,
    ): JsonResponse {
        $stepData = $this->otpStep($flows, $flowId, $step);

        return response()->json($handler->resendChallenge($stepData, [
            'flow_id' => $flowId,
            'step_index' => $step,
        ]));
    }

    private function otpStep(FormFlowService $flows, string $flowId, int $step): FormFlowStepData
    {
        $state = $flows->getFlowState($flowId);
        abort_if($state === null || (int) ($state['current_step'] ?? -1) !== $step, 404);
        $stepPayload = $state['instructions']['steps'][$step] ?? null;
        abort_unless(is_array($stepPayload) && ($stepPayload['handler'] ?? null) === 'otp', 404);

        return FormFlowStepData::from($stepPayload);
    }
}
