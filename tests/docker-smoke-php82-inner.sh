#!/usr/bin/env bash
set -eu
rm -rf /tmp/w && cp -a /work /tmp/w && cd /tmp/w
tar -xf "$VENDOR_TAR" -C /tmp/w
LOG=/tmp/smoke.log
set +e
php tests/smoke_core.php >"$LOG" 2>&1
ec=$?
set -e
cat "$LOG"
exit $ec
