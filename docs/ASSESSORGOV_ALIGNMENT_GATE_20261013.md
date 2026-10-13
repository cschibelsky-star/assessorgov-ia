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
