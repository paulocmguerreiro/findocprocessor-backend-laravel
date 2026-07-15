#!/bin/sh
set -eu

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${REDIS_PORT:-6379}"

check_port() {
    host="$1" port="$2" label="$3"
    if ! nc -z -w1 "${host}" "${port}" 2>/dev/null; then
        echo "❌  ${label} não está a responder em ${host}:${port}"
        echo "    Arranca o Docker: docker compose up -d"
        exit 1
    fi
}

# Detecta containers Docker (mysql/redis/clamav) a correr FORA do projecto
# docker-parity (compose.yaml) em simultâneo com este. O ClamAV em particular
# carrega assinaturas em RAM — duas instâncias em simultâneo (a do
# docker-parity + uma standalone usada para testes no host) podem exceder o
# limite de memória da VM do Docker Desktop e causar OOM kill (exit 137).
check_docker_conflict() {
    command -v docker >/dev/null 2>&1 || return 0

    projeto_ids=$(docker compose ps -q 2>/dev/null) || return 0
    [ -z "${projeto_ids}" ] && return 0

    outros_ids=""
    for id in $(docker ps -q 2>/dev/null); do
        pertence=0
        for pid in ${projeto_ids}; do
            [ "$id" = "$pid" ] && pertence=1 && break
        done
        [ "${pertence}" -eq 0 ] && outros_ids="${outros_ids} ${id}"
    done
    [ -z "${outros_ids}" ] && return 0

    conflito_ids=""
    for id in ${outros_ids}; do
        imagem=$(docker inspect --format '{{.Config.Image}}' "${id}" 2>/dev/null)
        case "${imagem}" in
            *clamav*|*mysql*|*redis*) conflito_ids="${conflito_ids} ${id}" ;;
        esac
    done
    [ -z "${conflito_ids}" ] && return 0

    echo "⚠️  Containers fora do docker-parity a correr em simultâneo (risco de OOM, sobretudo ClamAV):"
    for id in ${conflito_ids}; do
        docker inspect --format '    - {{.Name}} ({{.Config.Image}})' "${id}" | sed 's#(/#(#'
    done

    if [ -t 0 ]; then
        printf "Parar estes containers agora? [y/N] "
        read -r resposta
        case "${resposta}" in
            [Yy]*)
                docker stop ${conflito_ids} >/dev/null
                echo "✅  Containers parados."
                ;;
            *)
                echo "    A continuar sem parar — risco de OOM se a RAM do Docker Desktop for insuficiente."
                ;;
        esac
    else
        echo "    Execução não-interactiva — não parado automaticamente."
        echo "    Pára manualmente com: docker stop${conflito_ids}"
    fi
}

check_port "$DB_HOST"    "$DB_PORT"    "MySQL"
check_port "$REDIS_HOST" "$REDIS_PORT" "Redis"
check_docker_conflict
