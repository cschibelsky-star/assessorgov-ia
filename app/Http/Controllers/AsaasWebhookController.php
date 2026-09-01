<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\Billing\SubscriptionLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AsaasWebhookController extends Controller
{
    public function __invoke(Request $request, SubscriptionLifecycleService $lifecycle): JsonResponse
    {
        $configuredToken = (string) config('services.asaas.webhook_token');
        $receivedToken = (string) $request->header('asaas-access-token');

        if ($configuredToken === '') {
            return response()->json(['ok' => false, 'error' => 'webhook_not_configured'], 503);
        }

        if ($receivedToken === '' || ! hash_equals($configuredToken, $receivedToken)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $payload = $request->all();
        $externalId = data_get($payload, 'id');
        $eventType = data_get($payload, 'event');

        if (! is_string($externalId) || $externalId === '' || ! is_string($eventType) || $eventType === '') {
            return response()->json(['ok' => false, 'error' => 'invalid_payload'], 422);
        }

        try {
            $event = WebhookEvent::query()->firstOrCreate(
                ['provider' => 'asaas', 'external_id' => $externalId],
                [
                    'event_type' => $eventType,
                    'status' => 'received',
                    'payload' => $payload,
                ],
            );
        } catch (QueryException) {
            $event = WebhookEvent::query()
                ->where('provider', 'asaas')
                ->where('external_id', $externalId)
                ->first();
        }

        if ($event?->status === 'processed') {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        if (! $event) {
            return response()->json(['ok' => false, 'error' => 'event_persistence_failed'], 500);
        }

        try {
            $subscriptionId = data_get($payload, 'subscription.id')
                ?? data_get($payload, 'payment.subscription');
            $paymentId = data_get($payload, 'payment.id');

            $subscription = null;

            if (is_string($subscriptionId) && $subscriptionId !== '') {
                $subscription = Subscription::query()
                    ->where('asaas_subscription_id', $subscriptionId)
                    ->first();
            }

            if (! $subscription && is_string($paymentId) && $paymentId !== '') {
                $subscription = Subscription::query()
                    ->where('asaas_payment_id', $paymentId)
                    ->first();
            }

            if ($subscription) {
                $occurredAt = $this->parseOccurredAt(data_get($payload, 'dateCreated'));

                match ($eventType) {
                    'PAYMENT_RECEIVED',
                    'PAYMENT_CONFIRMED',
                    'PAYMENT_RESTORED' => $lifecycle->markPaid($subscription),
                    'PAYMENT_OVERDUE' => $lifecycle->markPastDue($subscription, $occurredAt),
                    default => null,
                };
            }

            $event->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ])->save();

            return response()->json([
                'ok' => true,
                'matched_subscription' => (bool) $subscription,
            ]);
        } catch (Throwable $exception) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            report($exception);

            return response()->json(['ok' => false, 'error' => 'processing_failed'], 500);
        }
    }

    private function parseOccurredAt(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
