#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")" && pwd)"
DEMO_DIR="$PROJECT_ROOT/.demo"
PHP_LOG="$DEMO_DIR/php-server.log"
ML_LOG="$DEMO_DIR/ml-service.log"
PHP_PID_FILE="$DEMO_DIR/php-server.pid"
ML_PID_FILE="$DEMO_DIR/ml-service.pid"
APP_URL="http://localhost:8000"
ML_HEALTH_URL="http://127.0.0.1:5000/openapi.json"

mkdir -p "$DEMO_DIR" "$PROJECT_ROOT/php-webapp/sessions" "$PROJECT_ROOT/php-webapp/uploads" "$PROJECT_ROOT/php-webapp/logs"

is_up() {
    local url="$1"
    curl -fsS "$url" >/dev/null 2>&1
}

start_php() {
    if is_up "$APP_URL"; then
        echo "PHP app already running at $APP_URL"
        return
    fi

    echo "Starting PHP app at $APP_URL"
    nohup php -S localhost:8000 -t "$PROJECT_ROOT/php-webapp" >"$PHP_LOG" 2>&1 &
    echo $! > "$PHP_PID_FILE"
}

start_ml() {
    if is_up "$ML_HEALTH_URL"; then
        echo "ML service already running at http://127.0.0.1:5000"
        return
    fi

    echo "Starting ML service at http://127.0.0.1:5000"
    (
        cd "$PROJECT_ROOT/ml-service"
        nohup "$PROJECT_ROOT/ml-service/.venv/bin/python" "$PROJECT_ROOT/ml-service/main.py" >"$ML_LOG" 2>&1 &
        echo $! > "$ML_PID_FILE"
    )
}

wait_for_url() {
    local url="$1"
    local name="$2"

    for _ in {1..20}; do
        if is_up "$url"; then
            echo "$name is ready: $url"
            return 0
        fi
        sleep 1
    done

    echo "$name failed to start. Check logs in $DEMO_DIR"
    return 1
}

start_php
start_ml
wait_for_url "$APP_URL" "PHP app"
wait_for_url "$ML_HEALTH_URL" "ML service"

echo
echo "Demo ready"
echo "App:    $APP_URL"
echo "Health: $APP_URL/health.php"
echo "ML:     http://127.0.0.1:5000"
