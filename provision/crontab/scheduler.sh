#!/bin/bash

set -euo pipefail

exec ./artisan schedule:run --no-ansi --no-interaction
