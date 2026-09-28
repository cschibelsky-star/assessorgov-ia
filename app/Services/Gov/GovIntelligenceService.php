<?php

namespace App\Services\Gov;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\GovComplianceItem;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

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

        $weeklyItems = $items->filter(
            fn (array $item): bool => ! empty($item['published_at'])
                && $item['published_at'] >= now()->subDays(7)->toDateString(),
        );

        return [
            'customer' => $customer,
            'items' => $items,
            'stats' => [
                'new_changes' => $weeklyItems->count(),
                'requires_action' => $items->whereIn('impact_class', [
                    'alter_rule',
                    'alter_data',
                    'alter_ux',
                    'alter_channel',
                    'commercial_action',
                    'alter_checklist',
                ])->count(),
                'opportunities' => $items->where('kind', 'opportunity')->count(),
                'monitoring' => $items->where('impact_class', 'monitor')->count(),
            ],
            'priorities' => ($weeklyItems->isNotEmpty() ? $weeklyItems : $items)
                ->whereIn('priority', ['critical', 'high'])
                ->take(3)
                ->values(),
        ];
    }

    public function findItem(string $itemId): array
    {
        $item = $this->catalog()->firstWhere('id', $itemId);

        if (! $item) {
            throw new InvalidArgumentException('Gov Intelligence item not found.');
        }

        return $item;
    }

    public function applyToCustomer(User $user, string $itemId): array
    {
        $customer = $user->customer;

        if (! $customer) {
            return [
                'status' => 'customer_required',
                'applied' => 0,
                'item' => $this->findItem($itemId),
            ];
        }

        $item = $this->findItem($itemId);

        $query = CustomerOpportunity::query()
            ->with('opportunity:id,channel,title')
            ->where('customer_id', $customer->getKey());

        $customerOpportunities = (clone $query)
            ->whereHas('opportunity', fn ($q) => $q->whereIn('channel', $item['channels'] ?? []))
            ->get();

        if ($customerOpportunities->isEmpty() && ($item['universal_supplier'] ?? false)) {
            $customerOpportunities = $query->get();
        }

        if ($customerOpportunities->isNotEmpty()) {
            $complianceItem = GovComplianceItem::query()->updateOrCreate(
                [
                    'customer_id' => $customer->getKey(),
                    'item_key' => $itemId,
                ],
                [
                    'title' => $item['title'],
                    'action' => $item['action'],
                    'priority' => $item['priority'],
                    'impact_class' => $item['impact_class'],
                    'target' => $item['action_target'],
                    'applied_at' => now(),
                    'applied_by_user_id' => $user->getKey(),
                ],
            );

            $complianceItem->customerOpportunities()->syncWithoutDetaching(
                $customerOpportunities->modelKeys(),
            );
        }

        return [
            'status' => $customerOpportunities->isEmpty() ? 'no_linked_opportunity' : 'applied',
            'applied' => $customerOpportunities->count(),
            'item' => $item,
        ];
    }

    public function complianceForUser(User $user): array
    {
        $customer = $user->customer;

        if (! $customer) {
            return [
                'customer' => null,
                'actions' => collect(),
                'pending' => 0,
                'submitted' => 0,
                'in_review' => 0,
                'conformant' => 0,
            ];
        }

        $actions = GovComplianceItem::query()
            ->with([
                'customerOpportunities.opportunity:id,title,channel',
                'latestEvidence',
            ])
            ->where('customer_id', $customer->getKey())
            ->get()
            ->map(function (GovComplianceItem $item): array {
                return [
                    'id' => $item->item_key,
                    'title' => $item->title,
                    'action' => $item->action,
                    'priority' => $item->priority,
                    'impact_class' => $item->impact_class,
                    'target' => $item->target,
                    'status' => $item->status,
                    'note' => $item->note,
                    'evidence' => $item->latestEvidence ? [
                        'disk' => $item->latestEvidence->disk,
                        'path' => $item->latestEvidence->path,
                        'original_name' => $item->latestEvidence->original_name,
                        'mime_type' => $item->latestEvidence->mime_type,
                        'sha256' => $item->latestEvidence->sha256,
                        'size_bytes' => $item->latestEvidence->size_bytes,
                        'uploaded_at' => $item->latestEvidence->uploaded_at?->toIso8601String(),
                        'uploaded_by_user_id' => $item->latestEvidence->uploaded_by_user_id,
                    ] : null,
                    'opportunities' => $item->customerOpportunities
                        ->map(fn (CustomerOpportunity $row): array => [
                            'id' => $row->opportunity_id,
                            'title' => $row->opportunity?->title,
                            'channel' => $row->opportunity?->channel,
                            'stage' => $row->stage,
                            'customer_opportunity_id' => $row->getKey(),
                        ])
                        ->unique('id')
                        ->values(),
                ];
            })
            ->sortBy(fn (array $action): int => $this->priorityWeight($action['priority'] ?? 'medium'))
            ->values();

        return [
            'customer' => $customer,
            'actions' => $actions,
            'pending' => $actions->where('status', 'pending')->count(),
            'submitted' => $actions->where('status', 'submitted')->count(),
            'in_review' => $actions->where('status', 'in_review')->count(),
            'conformant' => $actions->where('status', 'conformant')->count(),
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
                'id' => 'tcu-spreadsheet-diligence-2026-09',
                'title' => 'Vício sanável em planilha exige diligência antes da desclassificação',
                'fact' => 'O Boletim de Jurisprudência 601, publicado em 21/9/2026, destacou o Acórdão 4668/2026-Segunda Câmara, julgado em 1º/9/2026, no qual o TCU considerou irregular a desclassificação sumária por vícios sanáveis sem diligência e reiterou que a correção não pode elevar o preço global originalmente ofertado.',
                'analysis' => 'O sistema deve distinguir saneamento de erro material de reformulação da proposta e preservar a rastreabilidade entre versões.',
                'action' => 'Abrir diligência auditável, preservar a planilha original e a corrigida e bloquear correção que aumente o valor global.',
                'action_target' => 'planilha-diligencia',
                'priority' => 'high',
                'impact_class' => 'alter_rule',
                'kind' => 'risk',
                'source_label' => 'TCU · Boletim 601 / Acórdão 4668/2026',
                'source_url' => 'https://portal.tcu.gov.br/jurisprudencia',
                'published_at' => '2026-09-21',
                'channels' => [Opportunity::CHANNEL_LICITACAO],
                'universal_supplier' => true,
                'participation_sensitive' => true,
            ],
            [
                'id' => 'tcu-unusual-financial-index-2026-09',
                'title' => 'Índice econômico-financeiro não usual exige justificativa técnica',
                'fact' => 'Em 23/9/2026, o TCU divulgou o Acórdão 2552/2026-Plenário, que considerou irregular a exigência de Liquidez Imediata mínima de 0,8 sem demonstração de necessidade, pertinência e proporcionalidade.',
                'analysis' => 'Índices não usuais devem gerar alerta pré-edital porque podem restringir a competição sem correlação demonstrada com o risco contratual.',
                'action' => 'Identificar índices econômico-financeiros não usuais e sinalizar ausência de justificativa técnica antes da decisão de participar, pedir esclarecimento ou impugnar.',
                'action_target' => 'qualificacao-economico-financeira',
                'priority' => 'high',
                'impact_class' => 'alter_rule',
                'kind' => 'risk',
                'source_label' => 'TCU · Acórdão 2552/2026',
                'source_url' => 'https://portal.tcu.gov.br/imprensa/noticias/secao-das-sessoes',
                'published_at' => '2026-09-23',
                'channels' => [Opportunity::CHANNEL_LICITACAO],
                'universal_supplier' => true,
                'participation_sensitive' => true,
            ],
            [
                'id' => 'compras-app-alerts-2026-09',
                'title' => 'Compras.gov.br passou a publicar processos também no aplicativo',
                'fact' => 'O Comunicado 40/26, de 24/9/2026, informou que processos de contratação passaram a ser publicados também no aplicativo Compras.gov.br, mantendo PNCP e Diário Oficial como canais oficiais.',
                'analysis' => 'O aplicativo reduz o tempo de descoberta, mas deve ser tratado como canal auxiliar; o PNCP continua sendo a referência estruturada para ingestão e deduplicação.',
                'action' => 'Usar alertas do aplicativo como sinal auxiliar e deduplicar cada oportunidade pelo identificador oficial da contratação.',
                'action_target' => 'compras-app-alert',
                'priority' => 'medium',
                'impact_class' => 'alter_channel',
                'kind' => 'opportunity',
                'source_label' => 'Compras.gov.br · Comunicado 40/26',
                'source_url' => 'https://www.gov.br/compras/pt-br/acesso-a-informacao/comunicados/comunicados_2026/no-40-26-processos-de-contratacao-publicados-tambem-no-aplicativo-do-compras-gov.br',
                'published_at' => '2026-09-24',
                'channels' => [Opportunity::CHANNEL_LICITACAO],
                'universal_supplier' => true,
                'participation_sensitive' => false,
            ],
            [
                'id' => 'contratos-nfe-xml-2026-09',
                'title' => 'Contratos.gov.br passou a importar XML de NF-e no Instrumento de Cobrança',
                'fact' => 'O Comunicado 41/26, de 24/9/2026, disponibilizou a importação do XML da NF-e no Instrumento de Cobrança, com parametrização por CNPJ, município e UF e conferência humana obrigatória.',
                'analysis' => 'A funcionalidade aproxima contrato, execução e faturamento e abre espaço para validação automática de divergências sem eliminar a conferência humana.',
                'action' => 'Preparar validação pré-faturamento de XML contra contrato, itens, quantidades, valores e tributos antes do envio.',
                'action_target' => 'nfe-xml-contratos',
                'priority' => 'medium',
                'impact_class' => 'alter_data',
                'kind' => 'opportunity',
                'source_label' => 'Contratos.gov.br · Comunicado 41/26',
                'source_url' => 'https://www.gov.br/compras/pt-br/acesso-a-informacao/comunicados/comunicados_2026/no-41-26-importacao-de-arquivo-xml-para-o-instrumento-de-cobranca-no-sistema-contratos-gov.br',
                'published_at' => '2026-09-24',
                'channels' => [Opportunity::CHANNEL_LICITACAO],
                'universal_supplier' => false,
                'participation_sensitive' => true,
            ],
            [
                'id' => 'compras-publicidade-lei-12232-2026-09',
                'title' => 'Compras.gov.br passou a registrar concorrências presenciais de publicidade',
                'fact' => 'O Comunicado 39/26, de 23/9/2026, passou a permitir o registro de contratações de publicidade por concorrência presencial sob a Lei 12.232/2010, com publicação automática do edital e do resultado no PNCP.',
                'analysis' => 'O Radar precisa distinguir contratação de agência de publicidade de software, mídia digital e produção audiovisual para reduzir falsos positivos.',
                'action' => 'Classificar oportunidades da Lei 12.232/2010 separadamente e considerar a possibilidade de múltiplas agências vencedoras.',
                'action_target' => 'publicidade-lei-12232',
                'priority' => 'medium',
                'impact_class' => 'alter_channel',
                'kind' => 'opportunity',
                'source_label' => 'Compras.gov.br · Comunicado 39/26',
                'source_url' => 'https://www.gov.br/compras/pt-br/acesso-a-informacao/comunicados/comunicados_2026/39-26-2013-registro-de-contratacoes-de-servicos-de-publicidade-lei-no-12-232-2010-no-compras-gov.br',
                'published_at' => '2026-09-23',
                'channels' => [Opportunity::CHANNEL_LICITACAO],
                'universal_supplier' => false,
                'participation_sensitive' => false,
            ],
            [
                'id' => 'serpro-tax-intelligence-subsidy-2026-09',
                'title' => 'Serpro abriu tomada de subsídios para Plataforma de Inteligência Tributária',
                'fact' => 'Em 22/9/2026, o Serpro abriu tomada de subsídios para estruturar uma futura Plataforma de Inteligência Tributária voltada prioritariamente a municípios, administrações tributárias e consórcios públicos.',
                'analysis' => 'É uma oportunidade pré-contratual para influenciar requisitos e modelo de parceria; não equivale a edital ou garantia de contratação.',
                'action' => 'Preparar nota técnica das capacidades aderentes da Vitrine IA Pro e decidir participação antes dos prazos do chamamento.',
                'action_target' => 'serpro-inteligencia-tributaria',
                'priority' => 'high',
                'impact_class' => 'commercial_action',
                'kind' => 'opportunity',
                'source_label' => 'Serpro · Tomada de Subsídios',
                'source_url' => 'https://chamamentos.serpro.gov.br/editais/13',
                'published_at' => '2026-09-22',
                'deadline_at' => '2026-10-13',
                'channels' => [Opportunity::CHANNEL_LICITACAO],
                'universal_supplier' => true,
                'participation_sensitive' => false,
            ],
            [
                'id' => 'regularize-govbr-2026-09',
                'title' => 'Responsáveis empresariais passaram a acessar o Regularize com GOV.BR',
                'fact' => 'Em 23/9/2026, o Serpro informou que responsáveis registrados perante a Receita Federal passaram a acessar o Regularize da empresa com conta GOV.BR prata ou ouro, mantendo as formas de acesso já existentes.',
                'analysis' => 'O checklist de prontidão do fornecedor pode validar acesso e responsabilidade cadastral sem armazenar credenciais GOV.BR.',
                'action' => 'Adicionar ao checklist a confirmação de acesso ao Regularize e do responsável autorizado, sem coletar senha, token ou credencial GOV.BR.',
                'action_target' => 'regularize-govbr',
                'priority' => 'medium',
                'impact_class' => 'alter_checklist',
                'kind' => 'risk',
                'source_label' => 'Serpro · Regularize',
                'source_url' => 'https://www7.serpro.gov.br/menu/noticias/noticias-2026/novo-acesso-portal-regularize',
                'published_at' => '2026-09-23',
                'channels' => [Opportunity::CHANNEL_LICITACAO],
                'universal_supplier' => true,
                'participation_sensitive' => false,
            ],
            [
                'id' => 'simples-ibs-cbs-option-2026-09',
                'title' => 'Janela de opção do Simples para IBS e CBS termina em 30 de setembro',
                'fact' => 'A janela informada pelo Serpro para opção pelo recolhimento de IBS e CBS pelo regime regular para empresas do Simples Nacional vai de 1º a 30/9/2026.',
                'analysis' => 'A decisão tributária pode alterar premissas de formação de preço e deve ser refletida nos controles comerciais e de proposta quando aplicável.',
                'action' => 'Sinalizar o prazo ao responsável fiscal e revisar as premissas tributárias usadas na formação de preço após a decisão empresarial.',
                'action_target' => 'simples-ibs-cbs',
                'priority' => 'high',
                'impact_class' => 'alter_checklist',
                'kind' => 'risk',
                'source_label' => 'Serpro · IBS/CBS no Simples',
                'source_url' => 'https://www7.serpro.gov.br/menu/noticias/noticias-2026/simples-no-regime-regular',
                'published_at' => '2026-09-01',
                'deadline_at' => '2026-09-30',
                'channels' => [Opportunity::CHANNEL_LICITACAO],
                'universal_supplier' => true,
                'participation_sensitive' => true,
            ],
            [
                'id' => 'tcu-staffing-feasibility-2026-09',
                'title' => 'Equipe abaixo da estimativa exige demonstração de exequibilidade',
                'fact' => 'O TCU reforçou que quantitativo inferior ao estimado não autoriza desclassificação automática; produtividade, metodologia e atendimento dos resultados precisam ser avaliados.',
                'analysis' => 'Estruturas apoiadas por automação e IA podem ser competitivas, mas precisam comprovar capacidade de entrega e contingência.',
                'action' => 'Revisar equipe proposta, produtividade, metodologia, SLA e plano de contingência.',
                'action_target' => 'exequibilidade',
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
                'action_target' => 'custos',
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
                'action_target' => 'grupo-economico',
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
                'action_target' => 'sicx-ready',
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
                'action_target' => 'irp-monitor',
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