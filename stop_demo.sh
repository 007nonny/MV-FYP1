#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")" && pwd)"
DEMO_DIR="$PROJECT_ROOT/.demo"

stop_from_pid_file() {
    local pid_file="$1"
    local name="$2"

    if [[ -f "$pid_file" ]]; then
        local pid
        pid="$(cat "$pid_file")"
        if kill -0 "$pid" >/dev/null 2>&1; then
            kill "$pid"
            echo "Stopped $name (PID $pid)"
        fi
        rm -f "$pid_file"
    fi
}

stop_from_pid_file "$DEMO_DIR/php-server.pid" "PHP app"
stop_from_pid_file "$DEMO_DIR/ml-service.pid" "ML service"
