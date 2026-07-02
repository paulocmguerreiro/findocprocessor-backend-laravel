# Brief — Issue #77: Testes MySQL exclusivo + Preflight + Collation

**Data:** 2026-07-02
**Slug:** `testes-mysql-exclusivo`
**Branch:** `feat/testes-mysql-exclusivo`
**Issue:** [#77](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/77)
**Tipo:** chore/infra — sem alteração de comportamento de features

---

## Contexto

Durante a Issue #71 (Entidade Restaurar) descobriu-se que migrations com
`if (DB::getDriverName() === 'sqlite') return` criavam uma divergência silenciosa:
em SQLite os FKs `documentos.id_fornecedor`/`id_cliente` continuavam `ON DELETE SET NULL`
enquanto em MySQL/prod eram `RESTRICT`. O ramo soft-delete do Padrão B ficava
**intestável** em SQLite e um bug real (`forceDelete()` não lançava `QueryException`
em SQLite) só se manifestava em produção.

Esta issue elimina a classe inteira de bugs ao adoptar **MySQL exclusivamente** nos
testes, alinhando o ambiente de CI com produção. A app não está em produção, pelo que
as migrations podem ser reescritas livremente.

---

## Estado actual

| Ficheiro | Estado |
|---|---|
| `phpunit.xml` | `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `force="true"` |
| `phpunit.mysql.xml` | `DB_CONNECTION=mysql`, `DB_DATABASE=findocprocessor_testing`, sem paralelo |
| `composer test` | usa `phpunit.xml` (SQLite); paralelo + coverage em SQLite |
| `composer test:mysql` | usa `phpunit.mysql.xml`; série, local apenas |
| CI `build-and-test` | corre `composer test` (SQLite); instala `pdo_sqlite` |
| CI `docker-parity` | apenas `migrate:status` contra MySQL — não corre a suite |
| `docker/mysql/init.sql` | cria `findocprocessor_testing` com `utf8mb4_unicode_ci` |
| Migrations #70, #71, #72 | têm `if (sqlite) return` — comportamento divergente |

---

## Decisões de implementação

### Collation (recomendação)

Manter `mysql:8.4`. A collation actual `utf8mb4_unicode_ci` (Unicode 4.0) deve ser
actualizada para **`utf8mb4_0900_ai_ci`** — a collation padrão do MySQL 8.0+, baseada
em Unicode 9.0, com melhor ordenação e suporte a caracteres especiais (inclui pt-PT).
Não é necessário mudar para MariaDB — o ganho não justifica a troca de stack.

> **Checkpoint A: aguarda confirmação** — se a preferência for MariaDB +
> `utf8mb4_uca1400_ai_ci`, o âmbito da imagem Docker muda.

### Paralelo com MySQL

`composer test:coverage` usa `--parallel`. Laravel cria automaticamente
`findocprocessor_testing_test_1..N` por worker. O utilizador `findoc` precisa
de `CREATE` privilege global (ou em `findocprocessor_testing_test_%`).

Solução: actualizar `docker/mysql/init.sql` para conceder
`GRANT ALL PRIVILEGES ON *.* TO 'findoc'@'%'` (dentro do container de dev/CI
o risco é aceitável — o utilizador já tinha ALL em `findocprocessor.*`).

No CI: o serviço MySQL do GitHub Actions usa o utilizador `root` por defeito nas env
vars; o `findoc` user é criado por init via script ou env do serviço.

### Consolidação de migrations

As três migrations de FK podem ser colapsadas na migration `create_documentos_table`
original, porque prod não existe. Resultado:

- `create_documentos_table` passa a usar `restrictOnDelete()` directamente
- As migrations #70, #71, #72 de FK são eliminadas
- `down()` volta a `nullOnDelete()` (comportamento original do create)

Isto remove a necessidade de `if (sqlite)` em qualquer migration futura, pois
`restrictOnDelete()` funciona em ambos os drivers (SQLite reconstrói a tabela internamente).

---

## Âmbito de implementação

### T1 — Consolidar migrations de FK (sem `if sqlite`)
- Alterar `create_documentos_table`: `nullOnDelete()` → `restrictOnDelete()` nos três FKs
- Eliminar `update_fk_constraints_entidades_in_documentos.php`
- Eliminar `update_fk_constraint_categoria_in_documentos.php`
- Eliminar `enforce_restrict_entidades_fk_in_documentos.php`

### T2 — `phpunit.xml` → MySQL exclusivo
- Substituir `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` por MySQL
- Remover `DB_URL` (já é `force="true"`)
- `DB_DATABASE=findocprocessor_testing` (igual ao actual `phpunit.mysql.xml`)
- Manter `--parallel` no `test:coverage`
- Apagar `phpunit.mysql.xml` (fica redundante)

### T3 — Preflight Docker/Redis/MySQL
- Novo `bin/test-preflight.sh`: verifica MySQL (porta `${DB_HOST:-127.0.0.1}:${DB_PORT:-3306}`)
  e Redis (`${REDIS_HOST:-127.0.0.1}:${REDIS_PORT:-6379}`) com `nc -z` ou `/dev/tcp`
- Falha com mensagem clara: `"MySQL não está a responder — arranca o Docker: docker compose up -d"`
- Encadear como `@test:preflight` no início do script `test` em `composer.json`

### T4 — MySQL init: collation + privilégios paralelo
- `docker/mysql/init.sql`: `utf8mb4_unicode_ci` → `utf8mb4_0900_ai_ci`
- Adicionar `GRANT ALL PRIVILEGES ON *.* TO 'findoc'@'%'; FLUSH PRIVILEGES;`
  (substitui os `GRANT ... ON findocprocessor_testing.*`)
- Criar também as BDs de paralelo em init é desnecessário — Laravel cria-as automaticamente
  com o privilege acima

### T5 — CI: adicionar serviço MySQL ao `build-and-test`
- Adicionar `mysql:8.4` como service com `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, etc.
- Remover `pdo_sqlite` das extensões; remover `DB_CONNECTION=sqlite` das env vars do step
- Adicionar `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_DATABASE`, credenciais
- Criar a BD de teste e as BDs de paralelo (ou conceder GRANT para criação automática)
- Reavaliar `docker-parity`: manter apenas validação de build/boot (já coberto pelo CI agora)
- Remover `composer test:mysql` do `composer.json` (redunda com `composer test`)

### T6 — Dockerfile: remover `pdo_sqlite`
- Remover `pdo_sqlite` da linha `install-php-extensions`
- Actualizar comentário que menciona SQLite

### T7 — Docs: remover referências SQLite
- `docs/system_spec/04-infra/ambiente-docker.md`: reescrever — eliminar dualidade
  SQLite/MySQL; descrever MySQL-only com paralelo
- `docs/system_spec/06-config.md`: remover `DB_CONNECTION=sqlite` do `.env` exemplo
- `docs/system_spec/02-shared/soft-delete.md`: remover ressalvas SQLite (L49, L60)
- `docs/system_spec/02-shared/padroes-acoes.md`: verificar e limpar
- `docs/system_spec/01-features/utilizador.md`: verificar e limpar
- `docs/system_spec/07-testing.md`: actualizar referência ao ambiente de teste
- `README.md`: remover instruções SQLite

---

## Impacto técnico

| Área | Alteração |
|---|---|
| `phpunit.xml` | SQLite → MySQL; `phpunit.mysql.xml` eliminado |
| `database/migrations/` | 3 migrations de FK eliminadas; `create_documentos_table` actualizado |
| `bin/test-preflight.sh` | Novo script (preflight) |
| `composer.json` | `test:preflight` encadeado; `test:mysql` removido |
| `docker/mysql/init.sql` | Collation + GRANT global |
| `Dockerfile` | `pdo_sqlite` removido |
| `.github/workflows/ci.yml` | Serviço MySQL; remove SQLite; reavaliar `docker-parity` |
| `docs/system_spec/` | 5-6 ficheiros actualizados |

---

## Riscos identificados

1. **CI +8-9s de suite paralela + ~10-20s arranque MySQL service** — já medido na issue;
   aceitável dado que elimina classe de bugs de divergência de driver.
2. **`GRANT ALL ON *.*`** — scope alargado para o utilizador `findoc` dentro do container
   de dev/CI. Aceitável: este user não é exposto fora do container; não é um utilizador de prod.
3. **`--recreate-databases` pode ser necessário** na primeira execução paralela em MySQL
   quando as BDs de worker não existem ainda (Laravel cria-as automaticamente se o user tiver
   `CREATE` — T4 resolve isso).
4. **Init SQL só corre na primeira inicialização do volume** — a alteração de collation
   e GRANT só tem efeito em volumes novos. Quem já tiver volume terá de fazer
   `docker compose down -v && docker compose up -d`.
5. **`test:mysql` script e `phpunit.mysql.xml` eliminados** — scripts extenos que dependam
   destes ficam quebrados; sem impacto esperado (uso interno apenas).

---

## Questões em aberto

1. **Collation** — recomendação acima (`utf8mb4_0900_ai_ci`). Se a decisão for MariaDB,
   o T4, T5 e Dockerfile mudam de imagem. **Aguarda confirmação no Checkpoint A.**
2. **`docker-parity` no CI** — a suite já vai correr em MySQL; o job torna-se parcialmente
   redundante. Proposta: manter apenas o step de build/boot (sem `migrate:status` separado
   porque `build-and-test` já corre migrations). **Aguarda confirmação no Checkpoint A.**

---

## Aprendizagem esperada

Esta issue demonstra que a paridade de driver entre testes e produção é um requisito de
infra, não apenas uma boa prática: a divergência SQLite/MySQL mascarou um bug real de FK
durante várias issues. O custo de migrar para MySQL (≈2x mais lento) é pago uma vez; o
custo de detectar bugs de driver em produção é ilimitado.
