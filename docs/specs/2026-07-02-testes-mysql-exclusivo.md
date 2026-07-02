# Spec — Issue #77: Testes MySQL exclusivo + Preflight + Collation

**Data:** 2026-07-02
**Slug:** `testes-mysql-exclusivo`
**Brief:** `docs/briefs/2026-07-02-testes-mysql-exclusivo.md`

---

## Contratos por camada

### T1 — `database/migrations/2026_06_25_174831_create_documentos_table.php`

Os três FKs passam de `nullOnDelete()` para `restrictOnDelete()`:

```php
$table->foreignUuid('id_fornecedor')->nullable()->constrained('entidades')->restrictOnDelete()->comment('...');
$table->foreignUuid('id_cliente')->nullable()->constrained('entidades')->restrictOnDelete()->comment('...');
$table->foreignUuid('id_categoria')->nullable()->constrained('categorias_documento')->restrictOnDelete()->comment('...');
```

O `down()` mantém `Schema::dropIfExists('documentos')` — inalterado.

Migrations a eliminar (ficheiros apagados):
- `2026_06_30_135904_update_fk_constraints_entidades_in_documentos.php`
- `2026_06_30_144621_update_fk_constraint_categoria_in_documentos.php`
- `2026_07_01_140147_enforce_restrict_entidades_fk_in_documentos.php`

---

### T2 — `phpunit.xml`

```xml
<env name="DB_CONNECTION" value="mysql" force="true"/>
<env name="DB_DATABASE"   value="findocprocessor_testing" force="true"/>
```

Linhas a remover:
- `<env name="DB_DATABASE" value=":memory:" .../>` (substituída pela acima)
- `<env name="DB_URL" value="" force="true"/>` (não necessário em MySQL)

`phpunit.mysql.xml` — ficheiro eliminado.

`composer.json`:
- Remover script `"test:mysql"` (redunda com `composer test`)
- Adicionar script `"test:preflight": "bash bin/test-preflight.sh"`
- Encadear `@test:preflight` **antes** de `@test:lint` no script `test`

---

### T3 — `bin/test-preflight.sh`

Script bash que valida MySQL e Redis antes de correr a suite.
Usa `/dev/tcp` (disponível em bash, sem dependência de `nc`).

```bash
#!/usr/bin/env bash
set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${REDIS_PORT:-6379}"

check_port() {
    local host="$1" port="$2" label="$3"
    if ! bash -c "echo > /dev/tcp/${host}/${port}" 2>/dev/null; then
        echo "❌  ${label} não está a responder em ${host}:${port}"
        echo "    Arranca o Docker: docker compose up -d"
        exit 1
    fi
}

check_port "$DB_HOST"    "$DB_PORT"    "MySQL"
check_port "$REDIS_HOST" "$REDIS_PORT" "Redis"
```

Executável: `chmod +x bin/test-preflight.sh`

---

### T4 — `docker/mysql/init.sql`

```sql
-- Executado pelo MySQL apenas na primeira inicialização do volume de dados.
CREATE DATABASE IF NOT EXISTS findocprocessor_testing
    CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

-- GRANT global: necessário para o paralelo (Laravel cria findocprocessor_testing_test_N)
GRANT ALL PRIVILEGES ON *.* TO 'findoc'@'%';
FLUSH PRIVILEGES;
```

Collation alterada: `utf8mb4_unicode_ci` → `utf8mb4_0900_ai_ci`.
`GRANT ALL ON *.* ` substitui o `GRANT ALL ON findocprocessor_testing.*` anterior
(o utilizador `findoc` precisa de criar BDs para o paralelo).

---

### T5 — `.github/workflows/ci.yml`

#### Job `build-and-test`

Adicionar serviço MySQL:

```yaml
services:
  redis:
    # ... igual ao actual ...

  mysql:
    image: mysql:8.4
    env:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: findocprocessor_testing
      MYSQL_USER: findoc
      MYSQL_PASSWORD: secret
    ports:
      - 3306:3306
    options: >-
      --health-cmd "mysqladmin ping -h 127.0.0.1 -usecret || exit 1"
      --health-interval 10s
      --health-timeout 5s
      --health-retries 10
```

Step `Setup PHP 8.5`: remover `pdo_sqlite` das extensões; adicionar `pdo_mysql` se não estiver.

Step `Quality pipeline`: substituir as env vars:
```yaml
env:
  DB_CONNECTION: mysql
  DB_HOST: 127.0.0.1
  DB_PORT: 3306
  DB_DATABASE: findocprocessor_testing
  DB_USERNAME: findoc
  DB_PASSWORD: secret
  REDIS_HOST: localhost
  REDIS_PORT: 6379
```

O `test:preflight` vai correr dentro do `composer test` — não é necessário step separado.
O `GRANT ALL` para criação das BDs de paralelo é feito via `MYSQL_USER` no container do CI
(o `findoc` user é criado com acesso à BD `findocprocessor_testing`; o GRANT adicional de
`CREATE` global é dado via init script; em CI o serviço MySQL não lê o `docker/mysql/init.sql`,
pelo que o GRANT é executado num step de setup separado):

```yaml
- name: Setup MySQL grants (paralelo)
  run: |
    mysql -h 127.0.0.1 -uroot -psecret -e \
      "GRANT ALL PRIVILEGES ON *.* TO 'findoc'@'%'; FLUSH PRIVILEGES;"
```

#### Job `docker-parity`

Simplificado para validar apenas build/boot — sem `migrate:status`:

```yaml
docker-parity:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4

    - name: Build and boot stack
      run: docker compose up -d --build

    - name: Verificar arranque da app
      run: |
        timeout 150 bash -c \
          'until docker compose exec -T app php artisan about --only=Environment > /dev/null 2>&1; \
           do sleep 3; done'

    - name: Logs em caso de falha
      if: failure()
      run: docker compose logs

    - name: Tear down
      if: always()
      run: docker compose down -v
```

---

### T6 — `Dockerfile`

Remover `pdo_sqlite` da linha `install-php-extensions`:

```dockerfile
# Antes:
RUN ... install-php-extensions pdo_mysql pdo_sqlite bcmath intl zip opcache pcntl redis pcov

# Depois:
RUN ... install-php-extensions pdo_mysql bcmath intl zip opcache pcntl redis pcov
```

Actualizar comentário que referencia SQLite (linha com `# pdo_sqlite → testes`).

---

### T7 — Documentação

| Ficheiro | O que fazer |
|---|---|
| `docs/system_spec/04-infra/ambiente-docker.md` | Reescrever secção "Estratégia de paridade" — eliminar dualidade; descrever MySQL-only + paralelo |
| `docs/system_spec/06-config.md` | Remover bloco `DB_CONNECTION=sqlite`/`DB_DATABASE=:memory:` do `.env` exemplo; descomentar MySQL |
| `docs/system_spec/02-shared/soft-delete.md` | Remover ressalvas SQLite (L49 e L60) |
| `docs/system_spec/02-shared/padroes-acoes.md` | Verificar e remover refs SQLite se existirem |
| `docs/system_spec/01-features/utilizador.md` | Verificar e remover refs SQLite se existirem |
| `docs/system_spec/07-testing.md` | Actualizar referência ao ambiente de teste |
| `README.md` | Remover instruções/menções a SQLite |

---

## Critérios de aceitação

- `grep -ri "sqlite" database/ phpunit*.xml composer.json Dockerfile .github/ docs/ README.md` → zero resultados
- `composer test` em local (com Docker a correr) → suite verde, 100% coverage, 100% types
- `composer test` sem Docker → falha imediatamente com mensagem clara sobre MySQL/Redis
- CI `build-and-test` → verde em MySQL (suite paralela)
- CI `docker-parity` → verde (boot apenas)
- Migrations sem `if (driver === 'sqlite')` — nenhuma referência em nenhuma migration

---

## Notas de implementação

- A alteração de collation no `init.sql` só tem efeito em volumes novos; quem já
  tenha volume terá de fazer `docker compose down -v && docker compose up -d`.
- O MySQL 8.4 já usa `utf8mb4_0900_ai_ci` como collation de servidor por defeito
  (confirmado no container local); o `init.sql` especifica-a explicitamente para garantia.
- `composer test` **não** deve ser executado localmente sem Docker — o preflight
  falha cedo e com mensagem clara. Dentro do container (`docker compose exec app
  composer test`) continua a funcionar como antes.
