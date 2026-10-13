# AssessorGov IA 2.0 — gate de alinhamento pré-busca

Data: 2026-10-13. Referência: issue #4. **Busca real bloqueada até homologação.**

## Base verificada
- Branch `feature/radar-multicanal-govtech` em `dd06bc823651453e7549fd37a675da28de1ddff0`, limpa e sincronizada com origin.
- Serviços existentes: Radar PNCP, Cultura, TransfereGov, Inteligência Gov, compliance, assinaturas/Asaas.
- Testes existentes: Radar/PNCP Taiaçu, compliance, tenancy, billing, TransfereGov, activity log.
- PR #3 de matching por item em draft, separado da branch de base.
- Dockerfile e `docker-compose.hml.yml` presentes, porém runtime HML ainda não comprovado.
- `docs/AUDITORIA_TECNICA_RC05.md` é histórico de julho, não status atual.

## Bloqueadores P0 e critérios verificáveis
1. **Reconciliação sem regressão**: revisar diffs e CI dos PRs #1–#3, preservar branch/commit e confirmar schema e testes antes de integrar. Sem merge automático.
2. **Elegibilidade**: distinguir compatibilidade comercial, requisitos por item, comprovação documental e aptidão. Sem documentação, status `pendente_de_evidencia`, nunca `apto`.
3. **Segregação de clientes**: validar `customer_id`, RBAC e acesso a evidências, inclusive em API, fila e relatórios.
4. **Fontes**: validar URL oficial, prazo, alterações, idempotência e deduplicação. Uma publicação encerrada não pode gerar alerta de oportunidade vigente.
5. **Notificações**: eventos idempotentes, alertas gerais no painel e matching individual, preferências, consentimento, trilha de entrega e testes sem envios externos.
6. **Piloto Vitrine IA Pro**: criar tenant apenas na HML, com cadastro idempotente; validar dados jurídicos, CNAEs, atividades, documentação e autorização antes de classificar habilitação.
7. **HML e Cockpit**: verificar isolamento, banco, migrations somente em banco descartável de testes, filas, scheduler, autenticação, health, backup e rollback. Sem deploy ou cutover nesta etapa.
8. **Gate de liberação**: cenário sintético e fixture Taiaçu devem comprovar ingestão simulada → fonte validada → matching por item → pendências → alerta simulado, com testes verdes e aprovação explícita.

## Não executar nesta etapa
- Buscas/ingestões reais em fontes externas.
- Inscrições, propostas, e-mails ou push externos.
- Merge dos PRs, migrations externas, deploy em produção ou reset/clean de árvore Git.

## Próximo trabalho de código
Inspecionar o classificador do PR #3, trazer mudanças necessárias por PR independente com testes, corrigir inconsistências de tenancy/evidência e executar CI antes da HML.

## Revalidação de segurança — 13/10/2026

Base inspecionada: PR #5, commit 24182a95a3eb3a00fb56d1d3dae2ab6e54ef4dc8.
CI anterior confirmado: run 38427994899, tests/style/routes concluídos com sucesso.
Esse resultado não certifica runtime, cadastro jurídico nem habilitação.

### Correções verificáveis nesta revisão
- Ações de triagem recarregam o usuário persistido, exigem status active, mesmo customer_id e permissão radar.screen.
- Permissão atribuída no seeder a super-admin, admin-cliente e assessor; consulta permanece sem escrita. Nenhum seeder foi executado na VPS.
- Evidência sem data de upload ou posterior à revisão fica excluída do perfil revisado. Evidência anterior permanece pending: compliance não comprova validade documental/aptidão.
- Persistência exige oportunidade ativa, prazo futuro, registro de conferência da fonte, tipo official, URL válida e vínculo explícito item.opportunity_id. Ausência impede compatible_for_analysis.
- Gate central nos clientes PNCP, TransfereGov e Cultura bloqueia buscas fora de testing enquanto homologation_approved e real_search_enabled não forem ambos true. Ambos permanecem false em configuração versionada.
- Em testing, chamadas usam fakes/fixtures e preventStrayRequests: não existe bypass para HTTP real.
- Cenário Vitrine IA Pro — SYNTHETIC HML em banco descartável exercita triagem, incompatibilidade, documento vencido, requisitos não revisados, escopo e prazo. Não cria cliente na VPS nem inventa CNPJ/CNAE real.
- Propostas e notificações comerciais continuam false; testes verificam ausência de notificações. Nenhuma inscrição/proposta executada.

### Comparação com issue #4

| Critério | Evidência / limite |
| --- | --- |
| Isolamento multiempresa | Query do persister por customer_id + lock; testes existentes de evidência/download/triagem cruzada preservados. Não certifica relatórios e filas ainda não homologados. |
| Autenticação/permissões | Rotas web auth; revisão exige compliance.review; novas ações exigem radar.screen e usuário ativo persistido. Triagem ainda sem rota pública. Login e outras rotas não têm política global comprovada de suspensão; bloqueador antes de ativar. |
| Documentos/evidências | Provider server-side, validade conservadora, ME/EPP e requisitos por item nos testes. Cadastro jurídico, CNAEs, validade e cadeia de revisão do piloto real pendentes. A marca temporal não substitui vínculo imutável revisão-documento/hash. |
| Fonte/prazo | Persistência adiciona bloqueios; ainda faltam revisão oficial imutável, política de atualização, alterações/retificações e vínculo server-side dos requisitos por item. source_type e source_checked_at isolados não provam autenticidade. |
| Notificações | Nesta triagem nunca são permitidas; consentimento, preferências, idempotência, trilha de entrega e simulação completa de integração ainda pendentes. |
| Marketing/Social | Contrato de integração e prevenção de publicação duplicada não demonstrados pelo PR. |
| Reconciliação | Sem merge/reset/reconciliação; PRs #1–#3 não certificados por esta revalidação do #5. |
| Aceite sintético | Testes isolados não equivalem a implantação HML nem aceite ponta a ponta do Cockpit. |

### Dependências operacionais observadas (somente leitura)
- Manifest assessorgov-ia: branch main, compose vazio, allowed_keys vazio, nenhum domínio HML/produção.
- Workspace real: feature/radar-multicanal-govtech, árvore limpa. Divergência de branch declarada vs checkout.
- project_docker_status não retornou serviços no escopo cadastrado; não prova inexistência de containers fora dele.
- runtime_config_status: runtime_keys_not_configured.
- Compose versionado existe, mas usa APP_ENV production, URL cultura.hml, conexão de filas em banco e não declara workers/scheduler. Não foi implantado.
- Cockpit: cockpit_app e cockpit_web healthy. Cartão AssessorGov IA: development, integração pending, branch/admin_url null, sem SSO.
- Probe público /cockpit recusado pelo conector: hostname_not_allowed; acesso externo não validado.
- Banco, filas, scheduler, backup/restore/rollback, login/SSO e health do AssessorGov não comprovados. Não houve leitura de secrets.

**Decisão: gate operacional NÃO APROVADO.** HML isolada, cadastro real e aceite continuam obrigatórios.
Sem buscas reais, envio externo, migrations externas, deploy, merge ou cutover.
A suíte de CI do novo commit deve ser verde antes de qualquer avaliação para HML.
