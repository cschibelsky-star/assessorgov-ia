<?php

namespace App\Services\TransfereGov;

class TransfereGovNormalizer
{
    public function normalize(string $module, array $record, ?string $sourceUrl = null): array
    {
        $externalId = $this->first($record, [
            'id', 'codigo', 'numero', 'numeroInstrumento', 'numero_instrumento',
            'idInstrumento', 'id_instrumento', 'idProposta', 'id_proposta',
        ]);

        return [
            'source' => 'transferegov',
            'source_module' => $module,
            'external_id' => $externalId !== null ? (string) $externalId : null,
            'source_url' => $sourceUrl,
            'fetched_at' => now()->toISOString(),
            'raw_payload_hash' => hash('sha256', json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'raw_payload' => $record,
        ];
    }

    private function first(array $record, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $record) && $record[$key] !== null && $record[$key] !== '') {
                return $record[$key];
            }
        }

        return null;
    }
}
