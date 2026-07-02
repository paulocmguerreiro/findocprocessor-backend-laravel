# Plano — Issue #77: Testes MySQL exclusivo + Preflight + Collation

**Data:** 2026-07-02
**Slug:** `testes-mysql-exclusivo`
**Spec:** `docs/specs/2026-07-02-testes-mysql-exclusivo.md`

---

## Tarefas

### T1 — Consolidar migrations FK em `create_documentos_table`
- Editar `database/migrations/2026_06_25_174831_create_documentos_table.php`:
  - `id_fornecedor`: `nullOnDelete()` → `restrictOnDelete()`
  - `id_cliente`: `nullOnDelete()` → `restrictOnDelete()`
  - `id_categoria`: `nullOnDelete()` → `restrictOnDelete()`
- Apagar os três ficheiros de migration:
  - `database/migrations/2026_06_30_135904_update_fk_constraints_entidades_in_documentos.php`
  - `database/migrations/2026_06_30_144621_update_fk_constraint_categoria_in_documentos.php`
  - `database/migrations/2026_07_01_140147_enforce_restrict_entidades_fk_in_documentos.php`

### T2 — `phpunit.xml` → MySQL; eliminar `phpunit.mysql.xml`
- Editar `phpunit.xml`:
  - Substituir `DB_CONNECTION=sqlite` por `DB_CONNECTION=mysql`
  - Substituir `DB_DATABASE=:memory:` por `DB_DATABASE=findocprocessor_testing`
  - Remover linha `DB_URL` (não necessário em MySQL)
- Apagar `phpunit.mysql.xml`
- Editar `composer.json`:
  - Remover entrada `"test:mysql"`
  - Adicionar `"test:preflight": "bash bin/test-preflight.sh"`
  - Adicionar `"@test:preflight"` como primeiro item do script `test`

### T3 — `bin/test-preflight.sh`
- Criar `bin/test-preflight.sh` com verificação `/dev/tcp` de MySQL e Redis
- `chmod +x bin/test-preflight.sh`

### T4 — `docker/mysql/init.sql`: collation + GRANT global
- Alterar `utf8mb4_unicode_ci` → `utf8mb4_0900_ai_ci`
- Substituir `GRANT ALL PRIVILEGES ON findocprocessor_testing.*` por `GRANT ALL PRIVILEGES ON *.*`
  (necessário para Laravel criar `findocprocessor_testing_test_N` no paralelo)

### T5 — CI: serviço MySQL + GRANT paralelo + simplificar `docker-parity`
- `.github/workflows/ci.yml`:
  - `build-and-test`: adicionar serviço `mysql:8.4` com health check
  - `build-and-test`: remover `pdo_sqlite` das extensões PHP
  - `build-and-test`: substituir env vars SQLite por MySQL no step `Quality pipeline`
  - `build-and-test`: adicionar step `Setup MySQL grants (paralelo)` antes de `Quality pipeline`
  - `docker-parity`: simplificar — substituir `migrate:status` por `php artisan about --only=Environment`

### T6 — `Dockerfile`: remover `pdo_sqlite`
- Remover `pdo_sqlite` da linha `install-php-extensions`
- Actualizar comentário que refere SQLite

### T7 — Docs: limpar referências SQLite
- `docs/system_spec/04-infra/ambiente-docker.md`: reescrever secção "Estratégia de paridade" — MySQL-only + paralelo; remover tabela SQLite/MySQL
- `docs/system_spec/06-config.md`: descomentar MySQL; remover SQLite do `.env` exemplo
- `docs/system_spec/02-shared/soft-delete.md`: remover ressalvas SQLite (L49 e L60)
- `docs/system_spec/02-shared/padroes-acoes.md`: verificar e remover refs SQLite
- `docs/system_spec/01-features/utilizador.md`: verificar e remover refs SQLite
- `docs/system_spec/07-testing.md`: actualizar referência ao ambiente de teste (MySQL-only)
- `README.md`: remover instruções/menções a SQLite

---

## Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7
```

Todas as tarefas são independentes entre si após T1 (que limpa as migrations).
T2 e T3 devem ser feitas juntas — o `phpunit.xml` passa a exigir MySQL, e o
preflight é o guard que explica o erro quando não está disponível.

---

## Verificação final

```bash
# 1. Grep: zero refs SQLite em código/config/CI/docs
grep -ri "sqlite" database/ phpunit*.xml composer.json Dockerfile .github/ docs/ README.md

# 2. Suite completa local (requer Docker com MySQL + Redis a correr)
composer test

# 3. Verificar que preflight falha correctamente (sem Docker)
docker stop mysql && composer test || docker start mysql
```
