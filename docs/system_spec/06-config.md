# System Spec — 06: Configuração

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## .env (variáveis planeadas)

```env
APP_NAME=FinDocProcessor
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=findocprocessor
DB_USERNAME=findoc
DB_PASSWORD=secret

CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_CACHE_DB=1
# REDIS_PASSWORD=secret  # obrigatório em produção

QUEUE_CONNECTION=redis

# Expiração de tokens Sanctum em minutos (default 480 = 8h). Ver 01-features/auth.md.
SANCTUM_TOKEN_EXPIRATION=480

ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-opus-4-7

# Extração por IA — pipeline PdfParser → OCR → LLM local → LLM cloud.
# Comentar/esvaziar qualquer var de uma camada desliga essa camada (fail-safe).
# LLM_*_PROVIDER identifica o provider Prism (Prism\Prism\Enums\Provider — 'ollama',
# 'anthropic', 'openai', 'openrouter', ...); trocar de provider é só esta var.
LLM_LOCAL_PROVIDER=ollama
LLM_LOCAL_URL=
LLM_LOCAL_MODEL=
LLM_CLOUD_PROVIDER=anthropic
LLM_CLOUD_URL=
LLM_CLOUD_MODEL=
LLM_CLOUD_KEY=

# Pipeline — limiar (minutos) para considerar um Documento "preso" num estado
# transitório, varrido pelo ReconciliarFicheirosJob.
PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS=15

# Scan de malware — ClamAV self-hosted. Vazio desliga a camada
# (fail-safe, mesmo padrão de LLM_LOCAL_*/LLM_CLOUD_*).
CLAMAV_HOST=
CLAMAV_PORT=
CLAMAV_TIMEOUT_SEGUNDOS=5

FILESYSTEM_INBOX_PATH=inbox/
FILESYSTEM_PROCESSED_PATH=processed/
FILESYSTEM_TEMP_PATH=temp/
# Limite implementado no upload: 10 MB (max:10240 KB no ReceberUploadDocumentoRequest).
FILESYSTEM_MAX_FILE_SIZE=10485760
FILESYSTEM_ALLOWED_EXTENSIONS=.pdf,.png,.jpg,.jpeg
```

_Valores definitivos pendentes de implementação._

---

## Storage — Discos de ficheiros (`config/filesystems.php`)

5 discos `local` adicionados para o ciclo de vida dos documentos:

```php
'entrada'    => ['driver' => 'local', 'root' => storage_path('app/entrada'),    'throw' => false],
'enviado'    => ['driver' => 'local', 'root' => storage_path('app/enviado'),    'throw' => false],
'processado' => ['driver' => 'local', 'root' => storage_path('app/processado'), 'throw' => false],
'erro'       => ['driver' => 'local', 'root' => storage_path('app/erro'),       'throw' => false],
'perigoso'   => ['driver' => 'local', 'root' => storage_path('app/perigoso'),   'throw' => false],
```

Mapeamento estado → disco: `Pendente`/`AnaliseMalware`/`AnaliseTexto`/`AnaliseOcr` → `entrada`; `AnaliseIaLocal`/`AnaliseCloud` → `enviado`; `Processado` → `processado`; `Erro` → `erro`; `Perigoso` → `perigoso`.

O campo `disco_storage` na tabela `documentos` armazena o nome do disco activo. A movimentação de ficheiros entre discos (ao transitar de estado) é responsabilidade das Actions de transição.

Os 5 discos de ciclo de vida (e `storage/app/private/`) estão no `.gitignore` — documentos carregados nunca podem ser commitados.

---

## `config/extracao.php` e `config/prism.php` — pipeline de extração

`config/extracao.php` — parâmetros do pipeline; `local`/`cloud` agrupam
`provider`/`modelo`/`url`[/`key`, só cloud]/`activa` por camada (`activa` derivada da presença das
env vars `LLM_*` dessa camada, sem flag dedicado; config incompleta ⇒ camada inactiva, fail-safe).
Chaves expostas aqui — em vez de `env()` directo em `ClienteExtracaoIAPrism` — para que o cliente
nunca chame `env()` fora de ficheiro de config:

```php
'threshold_caracteres' => 50,
'ttl_lease' => env('EXTRACAO_TTL_LEASE', 300), // segundos — afinado na #98
'max_tentativas' => 3,
'local' => [
    'provider' => env('LLM_LOCAL_PROVIDER', 'ollama'),
    'modelo' => env('LLM_LOCAL_MODEL'),
    'url' => env('LLM_LOCAL_URL', 'http://localhost:11434/v1'),
    'activa' => filled(env('LLM_LOCAL_URL')) && filled(env('LLM_LOCAL_MODEL')),
],
'cloud' => [
    'provider' => env('LLM_CLOUD_PROVIDER', 'anthropic'),
    'modelo' => env('LLM_CLOUD_MODEL'),
    'url' => env('LLM_CLOUD_URL'),
    'key' => env('LLM_CLOUD_KEY', ''),
    'activa' => filled(env('LLM_CLOUD_URL')) && filled(env('LLM_CLOUD_MODEL')) && filled(env('LLM_CLOUD_KEY')),
],
'ocr' => [
    'dpi' => 300,
    'linguas' => ['por', 'eng'],
],
```

`provider` é o nome de `Prism\Prism\Enums\Provider` (ex.: `ollama`, `anthropic`, `openai`,
`openrouter`) — `ClienteExtracaoIAPrism` resolve com `Provider::from($config['provider'])` e passa
`url`/`api_key` como override a `Prism::structured()->using()`. Trocar de provider/modelo é só
`.env`, sem alterações de código. Ver `04-infra/extracao-ia.md` para o detalhe do cliente.

`ocr.dpi`/`ocr.linguas` — parâmetros de `ExtractorOcr` (rasterização `imagick` + reconhecimento
`thiagoalessio/tesseract_ocr`), sem env var própria (valores fixos, não esperados como
configuráveis por ambiente). Ver `04-infra/extracao-texto.md` para o contrato dos extractores de
texto.

`config/prism.php` (publicado via `vendor:publish --tag=prism-config`) — providers do Prism
usam as suas env vars nativas (`OPENAI_URL`/`OPENAI_API_KEY`, `OLLAMA_URL`, `ANTHROPIC_API_KEY`,
...), **não** acopladas a `LLM_LOCAL_*`/`LLM_CLOUD_*` — `ClienteExtracaoIAPrism` já passa
`url`/`api_key` como override por chamada a partir de `config('extracao.local'|'cloud')`.

> **`config:cache` congela as flags no build.** `local.activa`/`cloud.activa` derivam de
> `filled(env(...))` em tempo de load do ficheiro de config — com `config:cache` activo, o valor
> fica fixo até ao próximo `config:clear`. O `docker/entrypoint.sh` **não** corre
> `config:cache`/`config:clear` automaticamente; alterar qualquer var `LLM_*`
> exige `php artisan config:clear` manual (ou reiniciar o container, que não
> tem config cacheada por omissão neste projecto).

---

## `config/pipeline.php` — concorrência do pipeline e scan de malware

```php
'reconciliacao_limiar_minutos' => (int) env('PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS', 15),

// 'host' vazio ou 'port' zero desligam a camada (fail-safe) — porta 0 nunca é válida.
'malware' => [
    'host' => env('CLAMAV_HOST', ''),
    'port' => (int) env('CLAMAV_PORT', 0),
    'timeout_segundos' => (int) env('CLAMAV_TIMEOUT_SEGUNDOS', 5),
],
```

`reconciliacao_limiar_minutos` — limiar (minutos) usado pelo scope `Documento::documentosPresos()`
para identificar documentos parados num estado transitório há mais tempo que uma transição normal
demora — consumido por `ReconciliarFicheirosJob` (`04-infra/queue-jobs.md`).

`malware` — parâmetros de `ClamAvAnalisadorMalware` (bind em `AppServiceProvider`, lidos via
`config()->string()`/`config()->integer()`). `host` vazio ou `port` `0` são a sentinela de "camada
desligada" — evita `?string`/`?int` (accessores tipados do Laravel exigem `string`/`int`, não
`null`). `StreamMaxLength` do `clamd` (default 25 MB na imagem oficial) deve ser ≥ ao limite de
upload actual (`FILESYSTEM_MAX_FILE_SIZE` = 10 MB) — sem configuração adicional necessária no lado
da app; se excedido, o INSTREAM falha e conta como falha do scan (`FalhaAnaliseMalwareException`),
nunca como "não configurado". Ver `04-infra/malware.md` para o contrato `AnalisadorMalware`.

**Dependência de cache partilhado (`redis`):** `Schedule::job(...)->onOneServer()` (usado por
`ReconciliarFicheirosJob`) e o futuro `WithoutOverlapping` por documento (issue do orquestrador)
dependem de um cache store com locks atómicos partilhados entre processos — `config/cache.php` já usa
`redis` como default (`CACHE_STORE=redis`, `.env.example`). Em qualquer ambiente onde o cache não seja
partilhado entre workers (ex.: `array`/`file` num único processo local), `onOneServer()`/
`WithoutOverlapping` deixam de proteger entre processos reais — confirmar sempre `CACHE_STORE=redis`
(ou outro store partilhado) em produção.

---

## Comando `verificar:producao` — checklist de prontidão para produção

`App\Console\Commands\VerificarProducaoCommand` valida a configuração crítica de segurança e devolve **exit code 1** se alguma verificação chumbar. Destina-se a correr no deploy (entrypoint do Docker ou passo de CI) para bloquear arranques com configuração insegura.

```bash
php artisan verificar:producao
```

| Verificação                | Regra                                                            |
| -------------------------- | ---------------------------------------------------------------- |
| `APP_DEBUG` desactivado    | `config('app.debug') === false`                                  |
| `APP_ENV` é production     | `config('app.env') === 'production'`                             |
| `APP_KEY` definida         | não vazia                                                        |
| `APP_URL` usa HTTPS        | começa por `https://`                                            |
| Expiração tokens Sanctum   | `sanctum.expiration` entre 1 e 480 minutos (`null`/0 = eternos → falha) |
| CORS restrito              | sem `*`, `localhost`, `127.0.0.1` nem lista vazia                |
| Base de dados              | `database.default !== 'sqlite'`                                  |
| Redis com password         | `database.redis.default.password` string não vazia               |

Testes: `tests/Feature/Console/VerificarProducaoCommandTest.php` (cenário todo-verde + cada verificação degradada isoladamente).
