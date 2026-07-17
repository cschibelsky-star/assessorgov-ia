# Auditoria Técnica — AssessorGov IA RC05

Data: 16/07/2026

## Estado encontrado

- Repositório: `cschibelsky-star/assessorgov-ia`
- Branch padrão: `main`
- Visibilidade: pública
- Permissões do conector: administração e escrita confirmadas
- Conteúdo inicial: apenas `README.md` com o título do projeto
- Código de aplicação: inexistente
- Framework, dependências, banco, migrations, testes e CI/CD: inexistentes

## Diagnóstico

O repositório estava em estado inicial e, portanto, a RC05 deve ser construída como uma nova fundação Laravel, sem sobrescrever código legado.

## Arquitetura aprovada para a fundação

- PHP 8.3+
- Laravel 12
- Laravel Sanctum para autenticação por token/API
- Laravel Breeze como base de autenticação web
- Spatie Laravel Permission para perfis e permissões
- Spatie Laravel Activitylog para auditoria
- MySQL como banco padrão, configurável por ambiente
- UUID para entidades de negócio sensíveis
- Separação por domínios: Auth, Customers, Profiles, Plans, Billing, ArtificialIntelligence, Documents e Admin

## Fluxo comercial previsto

Cliente → Cadastro → Escolha do Plano → Pagamento Asaas → Webhook → Ativação automática → Login → Dashboard

## Regras de implantação

- Nenhuma alteração direta na `main`
- Desenvolvimento na branch `develop/rc05-foundation`
- Segredos nunca versionados
- Commits pequenos e organizados
- Evolução incremental e documentada

## Primeira entrega realizada

- Definição de dependências em `composer.json`
- Modelo de configuração `.env.example`
- Modelo autenticável `User` com Sanctum e RBAC
- Modelo de domínio `Customer` com UUID e trilha de auditoria

## Próxima etapa técnica

Criar o esqueleto executável completo do Laravel, migrations iniciais, modelos de planos e assinaturas, seeders de perfis/permissões, rotas, controllers, middleware, dashboard base e testes de autenticação e autorização.
