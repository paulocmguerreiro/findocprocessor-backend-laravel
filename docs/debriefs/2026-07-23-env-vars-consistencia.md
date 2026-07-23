# Debrief: Consistência de variáveis de ambiente (README / .env.example / system_spec)

**Issue:** — (manutenção de documentação, sem issue dedicada)
**Branch:** docs/env-vars-consistencia
**Data:** 2026-07-23
**Commits:** 1 commit

## O que foi implementado

Alinhamento das variáveis de ambiente documentadas em três fontes (`README.md`,
`.env.example`, `docs/system_spec/06-config.md`), fundamentado no que os ficheiros
`config/*.php` realmente lêem (`grep` de `env('...')` sobre `config/`), não num diff
doc-a-doc:

- Acrescentadas ao `06-config.md` três vars de segurança que faltavam apesar de serem
  lidas e validadas: `CORS_ALLOWED_ORIGINS` (`config/cors.php`), `ADMIN_EMAIL` e
  `ADMIN_INITIAL_PASSWORD` (`config/app.php`) — as três são verificadas pelo comando
  `verificar:producao`.
- Documentadas no README as duas vars sem *fail-safe* que um deploy tem de definir
  (`CORS_ALLOWED_ORIGINS` e `ADMIN_INITIAL_PASSWORD` — sem esta, o seed do admin é
  ignorado em produção).
- Removidas de `.env.example` e `06-config.md` as vars `FILESYSTEM_*_PATH`,
  `FILESYSTEM_MAX_FILE_SIZE` e `FILESYSTEM_ALLOWED_EXTENSIONS` — nenhum `config/`
  as lê; o limite de upload (50 MB) e as extensões estão codificados em
  `ReceberUploadDocumentoRequest` (`max:51200` + `mimetypes:`).
- `.env.example`: `DB_CONNECTION=mysql` por default (paridade com Docker/produção e com
  `verificar:producao`, que chumba `sqlite`), `SESSION_DRIVER=redis`, `+LOG_DAILY_DAYS`.

## Ficheiros alterados

| Ficheiro | Tipo de alteração | Notas |
| -------- | ----------------- | ----- |
| `.env.example` | alterado | DB default mysql; SESSION_DRIVER redis; LOG_DAILY_DAYS; remoção FILESYSTEM_* mortas |
| `README.md` | alterado | Nota das duas vars deploy-críticas; removida linha FILESYSTEM_* da tabela |
| `docs/system_spec/06-config.md` | alterado | +CORS/ADMIN no bloco .env; remoção FILESYSTEM_* + nota; correcção de referência stale na secção ClamAV |
| `.env` | alterado (não versionado) | LOG_DAILY_DAYS; remoção FILESYSTEM_* — gitignored, fora do commit |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| Remover as `FILESYSTEM_*_PATH/MAX/ALLOWED` das 3 fontes | Marcá-las como "não implementadas" | Nenhum `config/` as consome; mantê-las documentadas induz em erro — o limite real vive no FormRequest |
| `.env.example` com `DB_CONNECTION=mysql` activo | Manter `sqlite` como arranque rápido | Decisão estrutural: parte das migrações não é 100% compatível com SQLite — o arranque-rápido em SQLite quebraria de facto. Reforça a paridade prod/Docker/`verificar:producao` (que chumba `sqlite`) e a direcção MySQL-only |
| Documentar CORS/ADMIN fora da tabela de extracção/pipeline do README | Alargar a tabela *fail-safe* para as incluir | Não são *fail-safe* (a sua ausência não desliga camada, quebra o deploy) — nota separada é mais honesta |
| Fundamentar em `grep env()` sobre `config/` | Diff doc-a-doc entre os três ficheiros | Só o consumo real distingue var viva de var-fantasma |

## Desvios ao Plano

Não houve plano formal (alteração de manutenção iniciada a pedido directo, sem
`/planeia-issue`). O âmbito foi acordado com o utilizador via duas perguntas de decisão
(remoção das vars-fantasma; default MySQL).

## Aprendizagens

O ponto que ficou mais nítido não é de Vertical Slice mas da **disciplina da camada de
configuração**: neste projecto `env()` só é chamado dentro de `config/*.php` e o resto do
código lê via `config()->string()/integer()`. Isso torna o `grep "env('...'"` sobre
`config/` a **fonte de verdade** sobre que env vars estão realmente vivas — qualquer var
num `.env`/`.env.example`/spec que não apareça nesse grep é decorativa. Também reforçou o
padrão *fail-safe* das camadas de extracção/malware (flags `activa` derivadas de
`filled(env(...))`), que é distinto das vars operacionais sem *fail-safe* (`CORS`/`ADMIN`)
cuja ausência não desliga nada — parte o arranque. Documentar os dois grupos misturados era
o que gerava a confusão original.

## SYSTEM_SPEC a actualizar

- `docs/system_spec/06-config.md` — já actualizado neste commit (é o próprio alvo da alteração):
  +`CORS_ALLOWED_ORIGINS`/`ADMIN_EMAIL`/`ADMIN_INITIAL_PASSWORD`, remoção das `FILESYSTEM_*`
  não-lidas e correcção da referência stela na secção ClamAV. Sem outros ficheiros de spec
  afectados (nenhuma Action, Model, rota ou enum tocado).

## Verificação final
- [x] Linter a verde (`composer test` — Pint + Rector dry-run)
- [x] Testes a verde (`composer test` no Docker — Larastan 9, type-coverage 100%, cobertura 100%)
- [x] Nenhum dado sensível em logs
- [x] Nenhum segredo em código (`.env` fora do commit; `ADMIN_INITIAL_PASSWORD`/`LLM_CLOUD_KEY` vazios no exemplo)
