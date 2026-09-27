<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Plan;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AsaasClient
{
    private function http(): PendingRequest
    {
        $apiKey = (string) config('services.asaas.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('ASAAS_API_KEY is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('services.asaas.api_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withHeader('access_token', $apiKey)
            ->timeout((int) config('services.asaas.timeout', 15))
            ->retry(2, 250, throw: false);
    }

    public function ensureCustomer(Customer $customer): string
    {
        if ($customer->asaas_customer_id) {
            return $customer->asaas_customer_id;
        }

        $response = $this->http()->post('/customers', array_filter([
            'name' => $customer->legal_name,
            'cpfCnpj' => preg_replace('/\D+/', '', $customer->document),
            'email' => $customer->email,
            'phone' => $customer->phone ? preg_replace('/\D+/', '', $customer->phone) : null,
            'externalReference' => (string) $customer->id,
        ], static fn ($value) => $value !== null && $value !== ''));

        if (! $response->successful() || ! is_string($response->json('id'))) {
            throw new RuntimeException('Unable to create Asaas customer: '.$response->body());
        }

        $customer->forceFill(['asaas_customer_id' => $response->json('id')])->save();

        return (string) $customer->asaas_customer_id;
    }

    public function createSubscription(Customer $customer, Plan $plan, string $billingType = 'UNDEFINED'): array
    {
        if ((float) $plan->price <= 0) {
            throw new RuntimeException('Free plans do not require an Asaas subscription.');
        }

        $customerId = $this->ensureCustomer($customer);
        $cycle = match (strtolower((string) $plan->billing_cycle)) {
            'yearly', 'annual', 'annually' => 'YEARLY',
            default => 'MONTHLY',
        };

        $response = $this->http()->post('/subscriptions', [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => (float) $plan->price,
            'nextDueDate' => now()->addDay()->toDateString(),
            'cycle' => $cycle,
            'description' => 'AssessorGov Cultura - '.$plan->name,
            'externalReference' => (string) $customer->id,
        ]);

        if (! $response->successful() || ! is_string($response->json('id'))) {
            throw new RuntimeException('Unable to create Asaas subscription: '.$response->body());
        }

        return $response->json();
    }
}
