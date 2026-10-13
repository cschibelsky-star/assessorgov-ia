<?php

namespace App\Services\Radar;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OperationalSearchGate
{
    public function assertAllowed(): void
    {
        if (app()->environment('testing')) {
            // Fixtures/fakes are allowed, but a missing fake must never contact a source.
            Http::preventStrayRequests();

            return;
        }

        if (config('assessorgov_gate.homologation_approved') !== true
            || config('assessorgov_gate.real_search_enabled') !== true) {
            throw new RuntimeException('Real searches blocked pending isolated homologation and owner approval.');
        }
    }
}
