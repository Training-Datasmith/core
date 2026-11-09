#!/usr/bin/env bash
set -eu
rm -rf /tmp/w && cp -a /work /tmp/w && cd /tmp/w
curl -fsSL -A fuel-core-tests -o /tmp/phpunit.phar "$PHPUNIT_URL"
actual=$(php -r "echo hash_file('sha384', '/tmp/phpunit.phar');")
if [ "$actual" != "$PHPUNIT_SHA384" ]; then
	echo "ERROR: PHPUnit phar checksum mismatch" >&2
	exit 1
fi
LOG=/tmp/phpunit.log
set +e
php /tmp/phpunit.phar -c phpunit.core.xml --colors=never >"$LOG" 2>&1
ec=$?
set -e
cat "$LOG"
exit $ec
