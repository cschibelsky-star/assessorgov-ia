<?php

namespace App\Services\Gov;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Collection;

class GovIntelligenceService
{
    public function forUser(User $user): array
    {
        $customer = $user->customer;
        $channels = collect();
        $activeStages = collect();

        if ($customer) {
            $customerOpportunities = CustomerOpportunity::query()
                ->with('opportunity:id,channel,status')
                ->where('customer_id', $customer->getKey())
                ->get();

            $channels = $customerOpportunities
                ->pluck('opportunity.channel')
                ->filter()
                ->unique()
                ->values();

            $activeStages = $customerOpportunities
                ->pluck('stage')
                ->filter()
                ->unique()
                ->values();
        }

        $items = $this->catalog()
            ->map(fn (array $item): array => $this->personalize($item, $customer, $channels, $activeStages))
            ->sortBy(fn (array $item): int => $this->priorityWeight($item['priority']))
            ->values();

        return [
            'customer' => $customer,
            'items' => $items,
            'stats' => [
                'new_changes' => $items->count(),
                'requires_action' => $items->whereIn('impact_class', ['alter_rule', 'alter_data', 'alter_ux'])->count(),
                'opportunities' => $items->where('kind', 'opportunity')->count(),
                'monitoring' => $items->where('impact_class', 'monitor')->count(),
            ],
            'priorities' => $items->whereIn('priority', ['critical', 'high'])->take(3)->values(),
        ];
    }

    private function personalize(array $item, ?Customer $customer, Collection $channels, Collection $stages): array
    {
        $matchedChannel = isset($item['channels'])
            && collect($item['channels'])->intersect($channels)->isNotEmpty();

        $inParticipation = $stages->intersect([
            CustomerOpportunity::STAGE_ANALYSIS,
            CustomerOpportunity::STAGE_STRATEGY,
            CustomerOpportunity::STAGE_PARTICIPATION,
            CustomerOpportunity::STAGE_CLASSIFIED_WAITING,
            CustomerOpportunity::STAGE_EXECUTION,
            CustomerOpportunity::STAGE_FINANCIAL,
        ])->isNotEmpty();

        $relevant = $item['universal_supplier'] ?? false;

        if ($matchedChannel || (($item['participation_sensitive'] ?? false) && $inParticipation)) {
            $relevant = true;
        }

        $item['relevant'] = $relevant;
        $item['personalized_reason'] = match (true) {
            ! $customer => 'Complete o cadastro empresarial para ampliar a personalização deste alerta.',
            $matchedChannel => 'Sua empresa possui oportunidade vinculada a um dos canais afetados por esta mudança.',
            ($item['participation_sensitive'] ?? false) && $inParticipation => 'Sua empresa possui oportunidade em fase que exige atenção a esta regra.',
            $relevant => 'Regra aplicável de forma geral a fornecedores que participam de contratações públicas.',
            default => 'Monitoramento preventivo: ainda não identificamos impacto direto no seu portfólio atual.',
        };

        return $item;
    }

    private function priorityWeight(string $priority): int
    {
        return match ($priority) {
            'critical' => 0,
            'high' => 1,
            'medium' => 2,
            default => 3,
        };
    }

    private function catalog(): Collection
    {
        return collect([
            [
                'id' => 'tcu-staffing-feasibility-2026-09',
                'title' => 'Equipe abaixo da estimativa exige demonstração de exequibilidade',
                'fact' => 'O TCU reforçou que quantitativo inferior ao estimado não autoriza desclassificação automática; produtividade, metodologia e atendimento dos resultados precisam ser avaliados.',
                'analysis' => 'Estruturas apoiadas por automação e IA podem ser competitivas, mas precisam comprovar capacidade de entrega e contingência.',
                'action' => 'Revisar equipe proposta, produtividade, metodologia, SLA e plano de contingência.',
                'priority' => 'high',
                'impact_class' => 'alter_rule',
                'kind' => 'risk',
                'source_label' => 'TCU',
                'source_url' => 'https://pesquisa.apps.tcu.gov.br/',
                'channels' => [Opportunity::CHANNEL_LICITACAO],
                'universal_supplier' => true,
                'participation_sensitive' => true,
            ],
            [
                'id' => 'tcu-zero-cost-2026-09',
                'title' => 'Custo zero em item indispensável exige justificativa',
                'fact' => 'Itens indispensáveis com custo zero exigem demonstração da origem econômica do custo; não há aceitação ou rejeição automática.',
                'analysis' => 'Ativos próprios, custos absorvidos ou renúncia de remuneração precisam ser rastreáveis para sustentar a exequibilidade.',
                'action' => 'Identificar rubricas com custo zero e anexar justificativa e evidência correspondente.',
                'priority' => 'high',
                'impact_class' => 'alter_data',
                'kind' => 'risk',
                'source_label' => 'TCU',
                'source_url' => 'https://pesquisa.apps.tcu.gov.br/',
                'channels' => [Opportunity::CHANNEL_LICITACAO],
                'universal_supplier' => true,
                'participation_sensitive' => true,
            ],
            [
                'id' => 'tcu-economic-group-conflict-2026-09',
                'title' => 'Conflito de interesse pode alcançar empresas do mesmo grupo econômico',
                'fact' => 'A análise preventiva de impedimento pode alcançar relações societárias relevantes, não apenas o CNPJ participante.',
                'analysis' => 'Parcerias, coligadas, controladoras e apoio técnico ao órgão precisam ser verificados antes da participação.',
                'action' => 'Mapear grupo econômico e vínculos com consultorias ou apoio técnico relacionado ao órgão contratante.',
                'priority' => 'critical',
                'impact_class' => 'alter_rule',
                'kind' => 'risk',
                'source_label' => 'TCU',
                'source_url' => 'https://pesquisa.apps.tcu.gov.br/',
                'channels' => [Opportunity::CHANNEL_LICITACAO],
                'universal_supplier' => true,
                'participation_sensitive' => true,
            ],
            [
                'id' => 'sicx-2026',
                'title' => 'Sicx cria novo canal de compras padronizadas',
                'fact' => 'O Sicx passou a integrar o marco das compras públicas para bens e serviços comuns padronizados, com credenciamento e ofertas comparáveis.',
                'analysis' => 'Produtos com escopo, SLA, unidade de fornecimento e preço padronizados podem ganhar um novo canal comercial.',
                'action' => 'Preparar catálogo Sicx-ready, sem ativar integração automática antes de contrato oficial de dados.',
                'priority' => 'medium',
                'impact_class' => 'monitor',
                'kind' => 'opportunity',
                'source_label' => 'Planalto',
                'source_url' => 'https://www.planalto.gov.br/',
                'channels' => [Opportunity::CHANNEL_SICX],
                'universal_supplier' => true,
                'participation_sensitive' => false,
            ],
            [
                'id' => 'irp-pncp-2026',
                'title' => 'IRP amplia sinais antecipados de demanda pública',
                'fact' => 'IRPs passaram a aparecer no PNCP como sinais anteriores à contratação consolidada.',
                'analysis' => 'O acompanhamento antecipado pode melhorar preparação documental, precificação, parceria e capacidade de atendimento.',
                'action' => 'Monitorar IRPs e manter ingestão automática bloqueada até contrato oficial de API ser validado.',
                'priority' => 'medium',
                'impact_class' => 'monitor',
                'kind' => 'opportunity',
                'source_label' => 'PNCP',
                'source_url' => 'https://pncp.gov.br/',
                'channels' => [Opportunity::CHANNEL_IRP],
                'universal_supplier' => true,
                'participation_sensitive' => false,
            ],
        ]);
    }
}
