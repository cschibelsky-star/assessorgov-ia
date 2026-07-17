<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\Subscriptions\ActivateSubscription;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProcessAsaasWebhook
{
    private const ACTIVATION_EVENTS = [
        'PAYMENT_RECEIVED',
        'PAYMENT_CONFIRMED',
    ];

    private const OVERDUE_EVENTS = [
        'PAYMENT_OVERDUE',
    ];

    private const CANCELLATION_EVENTS = [
        'PAYMENT_DELETED',
        'PAYMENT_REFUNDED',
        'SUBSCRIPTION_DELETED',
        'SUBSCRIPTION_CANCELLED',
    ];

    public function __construct(
        private readonly ActivateSubscription $activateSubscription,
    ) {
    }

    public function handle(array $payload): WebhookEvent
    {
        $eventType = strtoupper((string) Arr::get($payload, 'event', 'UNKNOWN'));
        $externalId = $this->resolveExternalId($payload, $eventType);

        $webhookEvent = WebhookEvent::query()->firstOrCreate(
            [
                'provider' => 'asaas',
                'external_id' => $externalId,
            ],
            [
                'event_type' => $eventType,
                'status' => 'received',
                'payload' => $payload,
            ],
        );

        if (! $webhookEvent->wasRecentlyCreated
            && in_array($webhookEvent->status, ['processed', 'ignored'], true)) {
            return $webhookEvent;
        }

        try {
            return DB::transaction(function () use ($webhookEvent, $payload, $eventType): WebhookEvent {
                $webhookEvent = WebhookEvent::query()
                    ->lockForUpdate()
                    ->findOrFail($webhookEvent->getKey());

                if (in_array($webhookEvent->status, ['processed', 'ignored'], true)) {
                    return $webhookEvent;
                }

                $webhookEvent->forceFill([
                    'event_type' => $eventType,
                    'status' => 'processing',
                    'payload' => $payload,
                    'error_message' => null,
                ])->save();

                $subscription = $this->findSubscription($payload);

                if ($subscription === null) {
                    $webhookEvent->forceFill([
                        'status' => 'ignored',
                        'processed_at' => now(),
                        'error_message' => 'No local subscription matched the Asaas event.',
                    ])->save();

                    return $webhookEvent->fresh();
                }

                $this->applyEvent($subscription, $eventType, $payload);

                $webhookEvent->forceFill([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'error_message' => null,
                ])->save();

                return $webhookEvent->fresh();
            });
        } catch (Throwable $exception) {
            $webhookEvent->forceFill([
                'status' => 'failed',
                'processed_at' => now(),
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            throw $exception;
        }
    }

    private function findSubscription(array $payload): ?Subscription
    {
        $asaasSubscriptionId = Arr::get($payload, 'payment.subscription')
            ?? Arr::get($payload, 'subscription.id');
        $asaasPaymentId = Arr::get($payload, 'payment.id');

        return Subscription::query()
            ->when(
                $asaasSubscriptionId,
                fn ($query) => $query->where('asaas_subscription_id', $asaasSubscriptionId),
            )
            ->when(
                ! $asaasSubscriptionId && $asaasPaymentId,
                fn ($query) => $query->where('asaas_payment_id', $asaasPaymentId),
            )
            ->first();
    }

    private function applyEvent(Subscription $subscription, string $eventType, array $payload): void
    {
        $metadata = [
            'asaas_last_event' => $eventType,
            'asaas_last_event_at' => now()->toIso8601String(),
            'asaas_payment_status' => Arr::get($payload, 'payment.status'),
        ];

        if (in_array($eventType, self::ACTIVATION_EVENTS, true)) {
            $subscription->forceFill([
                'asaas_payment_id' => Arr::get($payload, 'payment.id') ?? $subscription->asaas_payment_id,
            ])->save();

            $this->activateSubscription->handle($subscription, $metadata);

            return;
        }

        if (in_array($eventType, self::OVERDUE_EVENTS, true)) {
            $subscription->forceFill([
                'status' => 'past_due',
                'metadata' => array_merge($subscription->metadata ?? [], $metadata),
            ])->save();

            return;
        }

        if (in_array($eventType, self::CANCELLATION_EVENTS, true)) {
            $subscription->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'metadata' => array_merge($subscription->metadata ?? [], $metadata),
            ])->save();

            return;
        }

        $subscription->forceFill([
            'metadata' => array_merge($subscription->metadata ?? [], $metadata),
        ])->save();
    }

    private function resolveExternalId(array $payload, string $eventType): string
    {
        $eventId = Arr::get($payload, 'id');

        if (is_string($eventId) && $eventId !== '') {
            return $eventId;
        }

        $resourceId = Arr::get($payload, 'payment.id')
            ?? Arr::get($payload, 'subscription.id');

        if (! is_string($resourceId) || $resourceId === '') {
            throw new RuntimeException('Asaas webhook does not contain an identifiable event or resource ID.');
        }

        return $eventType.':'.$resourceId;
    }
}
