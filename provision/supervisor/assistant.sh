#!/usr/bin/env bash

set -euo pipefail

export WORKER_CONNECTION="${WORKER_CONNECTION:-assistant}"
export WORKER_QUEUE="${WORKER_QUEUE:-assistant}"
export WORKER_TRIES="${WORKER_TRIES:-1}"
export WORKER_TIMEOUT="${WORKER_TIMEOUT:-150}"

exec "$(dirname "$0")/default.sh"
