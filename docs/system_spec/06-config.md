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

5 discos `local` adicionados para o ciclo de vida dos documentos (Issue #45):

```php
'entrada'    => ['driver' => 'local', 'root' => storage_path('app/entrada'),    'throw' => false],
'enviado'    => ['driver' => 'local', 'root' => storage_path('app/enviado'),    'throw' => false],
'processado' => ['driver' => 'local', 'root' => storage_path('app/processado'), 'throw' => false],
'erro'       => ['driver' => 'local', 'root' => storage_path('app/erro'),       'throw' => false],
'perigoso'   => ['driver' => 'local', 'root' => storage_path('app/perigoso'),   'throw' => false],
```

Mapeamento estado → disco: `Pendente`/`AguardaEnvio` → `entrada`; `Enviado`/`AguardaResposta` → `enviado`; `Processado` → `processado`; `Erro` → `erro`; `Perigoso` → `perigoso`.

O campo `disco_storage` na tabela `documentos` armazena o nome do disco activo. A movimentação de ficheiros entre discos (ao transitar de estado) é responsabilidade das Actions de transição (Issue #57).

Os 5 discos de ciclo de vida (e `storage/app/private/`) estão no `.gitignore` — documentos carregados nunca podem ser commitados.

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
