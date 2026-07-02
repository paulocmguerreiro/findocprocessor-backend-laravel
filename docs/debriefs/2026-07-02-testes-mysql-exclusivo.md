# Debrief — Issue #77: Testes MySQL exclusivo + Preflight + Collation

**Data:** 2026-07-02
**Slug:** `testes-mysql-exclusivo`
**Branch:** `feat/testes-mysql-exclusivo`
**Issue:** [#77](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/77)

---

## O que foi implementado

Migração completa do ambiente de testes de SQLite `:memory:` para MySQL exclusivo,
alinhando CI e desenvolvimento local com o motor de produção. Sete tarefas concluídas:

| Tarefa | Ficheiros |
|---|---|
| T1 — FKs `restrictOnDelete` consolidadas | `create_documentos_table.php`; 3 migrations apagadas |
| T2 — `phpunit.xml` → MySQL; sem `phpunit.mysql.xml` | `phpunit.xml`, `phpunit.mysql.xml` (apagado), `composer.json` |
| T3 — `bin/test-preflight.sh` | `bin/test-preflight.sh` (novo) |
| T4 — `docker/mysql/init.sql`: collation + GRANT global | `docker/mysql/init.sql` |
| T5 — CI: serviço MySQL 8.4 + GRANT paralelo + docker-parity simplificado | `.github/workflows/ci.yml` |
| T6 — `Dockerfile`: remover `pdo_sqlite` | `Dockerfile` |
| T7 — Docs: limpar referências SQLite | 6 ficheiros `docs/system_spec/` + `README.md` |

---

## Decisões tomadas

### D1 — Consolidação das migrations de FK na original

As três migrations correctivas (`update_fk_constraints_entidades`, `update_fk_constraint_categoria`,
`enforce_restrict_entidades_fk`) foram eliminadas e `restrictOnDelete()` aplicado directamente
em `create_documentos_table`. Possível porque a app não está em produção — não há dados reais
a preservar nem histórico de deploy a respeitar.

**Por quê:** ter três migrations de "correcção" da mesma migration original é ruído de
história de desenvolvimento, não infra real. Uma migration de criação limpa é mais legível
e testável.

### D2 — GRANT `ALL ON *.*` para `findoc` no Docker

O utilizador `findoc` passou de `GRANT ALL ON findocprocessor_testing.*` para `GRANT ALL ON *.*`.
Necessário para que o Pest paralelo crie automaticamente as BDs temporárias
`findocprocessor_testing_test_N` por worker sem configuração adicional.

**Por quê:** dentro do container Docker de dev/CI o scope alargado é aceitável — `findoc`
não é exposto fora do container e não é um utilizador de produção.

### D3 — Preflight via `/dev/tcp` sem dependência de `nc`

`bin/test-preflight.sh` usa `bash -c "echo > /dev/tcp/${host}/${port}"` em vez de `nc -z`.
`nc` não está disponível em todas as imagens Alpine sem instalação adicional; `/dev/tcp`
é uma funcionalidade built-in do bash, sem dependências externas.

### D4 — `docker-parity` simplificado para boot apenas

O job `docker-parity` passou de `migrate:status` para `php artisan about --only=Environment`.
`migrate:status` exigia que as migrations corressem com sucesso — qualquer FK mal definida
bloqueava o CI com uma mensagem pouco clara. `about --only=Environment` valida apenas que
a app arranca e liga à BD, deixando a verificação real de migrations para `build-and-test`
(que corre `composer test` com `--coverage` e `--parallel`).

### D5 — Collation `utf8mb4_0900_ai_ci`

Actualizada de `utf8mb4_unicode_ci` (Unicode 4.0) para `utf8mb4_0900_ai_ci` (Unicode 9.0),
padrão do MySQL 8.0+. Melhora ordenação de caracteres especiais (inclui pt-PT). Sem impacto
em dados existentes (volume recriado).

---

## O que correu diferente do plano

### Volume MySQL com init.sql antigo (não previsto)

O volume Docker local foi inicializado com o `init.sql` antigo (sem `MYSQL_USER`/`MYSQL_PASSWORD`
nas env vars do container), pelo que o utilizador `findoc` e a BD `findocprocessor_testing`
não existiam. Foi necessário aplicar manualmente:

```bash
docker exec mysql mysql -uroot -psecret -e "
CREATE USER IF NOT EXISTS 'findoc'@'%' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON *.* TO 'findoc'@'%' WITH GRANT OPTION;
CREATE DATABASE IF NOT EXISTS findocprocessor_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
FLUSH PRIVILEGES;
"
```

Para instalações limpas (volume novo), `docker compose down -v && docker compose up -d` é suficiente.
Este workaround está documentado em `docs/system_spec/04-infra/ambiente-docker.md`.

### `down()` das migrations de FK não foi revertido

O brief mencionava reverter `down()` para `nullOnDelete()`. As três migrations foram
**eliminadas** em vez de revertidas — o `down()` de `create_documentos_table` mantém
`Schema::dropIfExists('documentos')`, que é o correcto para uma migration de criação.
Não é necessário reverter comportamento numa migration que simplesmente cria a tabela.

---

## Resultados

- `composer test` (local, MySQL, paralelo): **724 testes, 100% coverage, 100% types** ✅
- `grep -ri "sqlite" database/ phpunit*.xml composer.json Dockerfile .github/ docs/system_spec/ README.md` → zero resultados relevantes ✅
- Única menção residual: comentário interno da migration Spatie (`create_permission_tables.php:42`) — código publicado pelo package, não configuração do projecto

---

## Aprendizagens

### Paridade de driver é um requisito de infra, não uma boa prática

A divergência SQLite/MySQL mascarou um bug real durante várias issues: `forceDelete()` dentro
de uma `DB::transaction()` em SQLite não lançava `QueryException` ao violar uma FK `RESTRICT`
(o SQLite difere a verificação para o commit, que estava dentro da transação, e a excepção
escapava ao `catch` da Action). Em MySQL o comportamento é imediato — o bug seria detectado
no primeiro teste.

A lição: adoptar o driver de produção nos testes não é apenas "mais correcto" — é a única
forma de garantir que a suite testa o mesmo código que vai a produção. O custo de migrar
(≈2× mais lento por teste por causa do overhead TCP vs memória) é pago uma vez; o custo
de detectar bugs de driver em produção é ilimitado.

### GRANT para paralelo é infra, não um workaround

O Pest paralelo cria BDs temporárias por worker (`_test_1`, `_test_2`, …). Não é um
comportamento configurável — é como o Laravel implementa o isolamento entre workers.
Dar `GRANT ALL ON *.*` ao utilizador de teste dentro do container é a solução correcta
(não um workaround): o utilizador não sai do container, e o container de dev/CI não
é um ambiente de produção.

### `/dev/tcp` como alternativa portável a `nc`

Para verificar conectividade TCP em bash sem dependências externas, `bash -c "echo > /dev/tcp/host/port"`
é suficiente — funciona em Alpine, Debian, macOS e qualquer ambiente com bash. `nc` (netcat)
não está instalado por defeito em Alpine sem `apk add netcat-openbsd`.

### Vertical Slice e infra de testes

Esta issue não tocou em nenhuma feature slice — foi puramente infra. A lição de Vertical
Slice aqui é indirecta: a organização por caso de uso facilita a migração de driver porque
não há lógica de query espalhada por camadas — as Actions invocam o ORM directamente ou via
Repository, e a suite testa o comportamento, não a implementação. A migração SQLite→MySQL
não exigiu alterar uma única linha de código de domínio.
