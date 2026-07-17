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
    public function __construct(
        private readonly ActivateSubscription $activateSubscription,
    ) {
    }

    public function handle(array $payload): WebhookEvent
    {
        $eventType = (string) Arr::get($payload, 'event', 'UNKNOWN');
        $paymentId = Arr::get($payload, 'payment.id');
        $subscriptionId