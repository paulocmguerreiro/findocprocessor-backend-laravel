# System Spec — 06: Configuração

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## .env (variáveis planeadas)

```env
APP_NAME=FinDocProcessor
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=findocprocessor
# DB_USERNAME=root
# DB_PASSWORD=

CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_CACHE_DB=1
# REDIS_PASSWORD=secret  # obrigatório em produção

QUEUE_CONNECTION=redis

ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-opus-4-7

FILESYSTEM_INBOX_PATH=inbox/
FILESYSTEM_PROCESSED_PATH=processed/
FILESYSTEM_TEMP_PATH=temp/
FILESYSTEM_MAX_FILE_SIZE=52428800
FILESYSTEM_ALLOWED_EXTENSIONS=.pdf,.png,.jpg,.jpeg
```

_Valores definitivos pendentes de implementação._
