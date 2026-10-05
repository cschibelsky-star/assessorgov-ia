# Aceitação PNCP — Taiaçu

Executada em 05/10/2026 sobre o PR #1, a partir de `c7d7cc397a62b6202d0e64c794e8536476f8b70b`.

## Fontes oficiais e consulta

- Publicação: https://pncp.gov.br/app/editais/44544690000115/2026/136
- Detalhe JSON: https://pncp.gov.br/api/consulta/v1/orgaos/44544690000115/compras/2026/136
- Descoberta pelo cliente do Radar: `/api/consulta/v1/contratacoes/publicacao`, com `dataInicial=20261002`, `dataFinal=20261002`, `codigoModalidadeContratacao=6`, `cnpj=44544690000115`, `pagina=1`, `tamanhoPagina=50`.
- Snapshot integral da resposta de descoberta, obtido por GET em 05/10/2026: `tests/Fixtures/pncp/taiacu-publicacao.json`. Não é uma resposta sintética.

A descoberta retornou uma contratação e uma página. O ingestor passou o registro pelo adapter, normalizador e upsert em SQLite em memória, com as migrations executadas exclusivamente nesse banco de teste.

## Comparação canônica

| Campo | Resultado validado |
| --- | --- |
| Controle / external_id | 44544690000115-1-000136/2026 |
| Fonte / canal | pncp:contratacoes / licitacao |
| Título | PNCP 21/2026 |
| Órgão | MUNICIPIO DE TAIACU |
| Município / UF / jurisdição | Taiaçu / SP / Municipal/SP |
| Processo | 713, conforme `processo` na API; sem acrescentar `/2026` não retornado pela fonte |
| Modalidade | 6 — Pregão - Eletrônico |
| Valor estimado | R$ 84.882,67 |
| Publicação | 02/10/2026 14:19:00 |
| Abertura | 05/10/2026 08:00:00 |
| Encerramento | 16/10/2026 08:59:00 |
| Objeto | Texto integral da fonte, equipamentos para a sala de informática da EMEB Professor Wilson Antônio Gonçalves |
| URL de origem | `linkSistemaOrigem` do Portal de Compras Públicas, preservado |
| Status canônico | review |

As datas acima reproduzem os horários sem offset retornados pela API. O teste compara os componentes de data e hora; não comprova conversão de fuso horário.

## Correção e evidências

O adapter lia apenas `numeroProcesso`, deixando o processo nulo. Agora usa `processo` e mantém `numeroProcesso` como fallback. Filtros, modalidade, paginação, normalização e chave de upsert não precisaram de alteração.

- Teste real opt-in: **2 testes / 67 assertions**, sem falhas. Inclui o replay da resposta capturada e a consulta real do detalhe, seguida de duas ingestões reais pela API de descoberta.
- Cada ingestão real: 1 registro recebido, 1 upsert, nenhum erro. Segunda ingestão: mesmo ID, apenas 1 oportunidade no banco.
- Suíte padrão: **23 testes / 125 assertions / 1 skipped** (consulta real opt-in). O replay oficial sempre roda na suíte, sem acesso à rede.
- Regressão confirmada: ao restaurar temporariamente o mapeamento original, o teste capturado falha com `null` em vez de `713`.
- Pint nos dois arquivos PHP alterados: aprovado. `artisan route:list`: aprovado, 27 rotas.
- Runtime local: PHP 8.3.6, SQLite em memória. Dependências resolvidas via Composer porque o repositório não possui lock versionado; o lock temporário não integra esta alteração.

Para repetir o teste real em ambiente preparado:

```bash
PNCP_LIVE_ACCEPTANCE=1 php vendor/bin/phpunit --filter PncpTaiacuAcceptanceTest
```

Não houve envio de proposta, gravação no PNCP, alteração de dados da HML/produção, merge ou deploy.
