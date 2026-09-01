<?php

namespace App\Services\Radar;

use App\Contracts\Radar\OpportunitySourceAdapter;
use App\Models\Opportunity;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PncpContractingAdapter implements OpportunitySourceAdapter
{
    public function toCanonical(mixed $source): array
    {
        if (! is_array($source)) {
            throw new InvalidArgumentException('PNCP contracting source must be an array.');
        }

        $object = trim((string) ($source['objetoCompra'] ?? ''));
        $number = trim((string) ($source['numeroCompra'] ?? ''));
        $year = trim((string) ($source['anoCompra'] ?? $source['ano'] ?? ''));
        $sourceUrl = trim((string) (
            $source['source_url']
            ?? $source['linkSistemaOrigem']
            ?? $source['linkProcessoEletronico']
            ?? ''
        ));

        if ($object === '') {
            throw new InvalidArgumentException('PNCP contracting object is required.');
        }

        if ($sourceUrl === '') {
            throw new InvalidArgumentException('PNCP contracting source URL is required.');
        }

        $title = $number !== '' && $year !== ''
            ? "PNCP {$number}/{$year}"
            : Str::limit($object, 220, '');

        $organization = data_get($source, 'orgaoEntidade.razaoSocial')
            ?? data_get($source, 'orgaoEntidade.razaosocial')
            ?? data_get($source, 'orgaoEntidade.nome');

        $state = data_get($source, 'unidadeOrgao.ufSigla');
        $municipality = data_get($source, 'unidadeOrgao.municipioNome');
        $sphere = data_get($source, 'orgaoEntidade.esferaId');

        return [
            'external_id' => $source['numeroControlePNCP'] ?? null,
            'title' => $title,
            'summary' => $object,
            'source_url' => $sourceUrl,
            'source_type' => 'official',
            'organization' => $organization,
            'jurisdiction' => $this->jurisdiction($sphere, $state),
            'state' => $state,
            'municipalities' => $municipality ? [$municipality] : null,
            'estimated_value' => $source['valorTotalEstimado'] ?? null,
            'opens_at' => $source['dataAberturaProposta'] ?? null,
            'closes_at' => $source['dataEncerramentoProposta'] ?? null,
            'event_at' => $source['dataPublicacaoPncp'] ?? null,
            'requirements' => null,
            'required_documents' => null,
            'metadata' => [
                'provider' => 'pncp',
                'numero_controle_pncp' => $source['numeroControlePNCP'] ?? null,
                'numero_compra' => $number ?: null,
                'ano_compra' => $year ?: null,
                'numero_processo' => $source['numeroProcesso'] ?? null,
                'modalidade_id' => $source['modalidadeId'] ?? null,
                'modalidade_nome' => $source['modalidadeNome'] ?? null,
                'modo_disputa_id' => $source['modoDisputaId'] ?? null,
                'situacao_compra_id' => $source['situacaoCompraId'] ?? null,
                'srp' => $source['srp'] ?? null,
                'valor_total_homologado' => $source['valorTotalHomologado'] ?? null,
                'link_processo_eletronico' => $source['linkProcessoEletronico'] ?? null,
                'orgao_cnpj' => data_get($source, 'orgaoEntidade.cnpj'),
                'unidade_codigo' => data_get($source, 'unidadeOrgao.codigoUnidade'),
                'data_atualizacao_pncp' => $source['dataAtualizacao'] ?? null,
            ],
            'status' => 'review',
            'source_checked_at' => now(),
            '_channel' => Opportunity::CHANNEL_LICITACAO,
            '_source_name' => 'pncp:contratacoes',
        ];
    }

    private function jurisdiction(?string $sphere, ?string $state): ?string
    {
        return match ($sphere) {
            'F' => 'Federal',
            'E' => $state ? "Estadual/{$state}" : 'Estadual',
            'M' => $state ? "Municipal/{$state}" : 'Municipal',
            'D' => 'Distrital/DF',
            default => $state,
        };
    }
}
