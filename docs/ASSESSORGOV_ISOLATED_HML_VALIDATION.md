# AssessorGov 2.0 — validação isolada na VPS

Esta etapa executa a suíte do PR #5 em um container sem rede, portas, volumes compartilhados ou banco externo.
O banco SQLite em memória existe somente durante os testes. RefreshDatabase cria o schema descartável da suíte.
O APP_KEY da imagem é uma fixture pública de testes; esta imagem nunca deve atender usuários nem ser reutilizada em runtime HML/produção.

## Contrato de execução
- Projeto dedicado: assessorgov-safety-hml-20261013.
- Workspace: /srv/projects/assessorgov-safety-hml-20261013.
- Branch: audit/assessorgov-alignment-20261013. Conferir SHA antes do build.
- Compose: docker-compose.safety-validation.yml.
- Serviço: safety_validation.
- Build requer acesso aos registries e Composer; execução dos testes usa network_mode none.
- Critério: container exit 0, suíte verde, estilo verde e rotas válidas.
- PNCP_LIVE_ACCEPTANCE=0; sem segredos reais ou integrações comerciais.
- Nenhuma migration em banco persistente, tenant real, notificação ou proposta.

## Dependências para HML interativa
Este runner não é a aplicação administrativa e não deve ser cadastrado como URL apta no Cockpit.
Antes da HML interativa: runtime com ambiente homologation, chave exclusiva gerada server-side,
banco/armazenamento próprios, configuração segura de e-mail/notificações, filas/scheduler controlados,
cadastro sintético auditado, autenticação/SSO, backup/restore/rollback e rota criada pelo provisionador oficial.
O cartão permanece em desenvolvimento até haver URL e health funcional comprovados.
Buscas reais continuam bloqueadas. Cadastro jurídico/CNAEs/documentação reais exigem confirmação.
