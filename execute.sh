#!/bin/bash
# execute.sh — Shell command wrapper cho CloudPad.
#
# Phase 16: Hardened version.
# - Reject empty commands
# - Log all executions to syslog
# - Reject known dangerous patterns (rm -rf /, etc.)
# - Run với timeout để ngăn long-running commands treo server

set -euo pipefail

if [ "$#" -lt 1 ]; then
    echo "Usage: $0 <command> [arguments...]" >&2
    exit 1
fi

COMMAND="$1"
shift
ARGS="$@"

# --- Safety check: reject blank command ---
if [ -z "$COMMAND" ]; then
    echo "[execute.sh] ERROR: Empty command rejected" >&2
    exit 1
fi

# --- Safety check: reject catastrophic patterns ---
FULL_CMD="$COMMAND $ARGS"
if echo "$FULL_CMD" | grep -qE 'rm\s+-[rRf]*f[rR]?\s+/\s*$|rm\s+-[rRf]*f[rR]?\s+/\*'; then
    echo "[execute.sh] ERROR: Dangerous command pattern rejected: $FULL_CMD" >&2
    logger -t cloudpad-execute "BLOCKED dangerous command: $FULL_CMD"
    exit 1
fi

# --- Log execution ---
logger -t cloudpad-execute "exec: $FULL_CMD" 2>/dev/null || true

# --- Execute with timeout (30s default) ---
TIMEOUT="${CLOUDPAD_EXEC_TIMEOUT:-30}"
timeout "$TIMEOUT" $COMMAND $ARGS
EXIT_CODE=$?

if [ $EXIT_CODE -eq 124 ]; then
    echo "[execute.sh] ERROR: Command timed out after ${TIMEOUT}s: $FULL_CMD" >&2
    exit 124
fi

exit $EXIT_CODE
