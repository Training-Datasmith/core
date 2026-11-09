#!/usr/bin/env bash
set -eu
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq >&2
apt-get install -y -qq unzip git libzip-dev >&2
docker-php-ext-install zip >/dev/null 2>&1
rm -rf /tmp/w && cp -a /work /tmp/w && cd /tmp/w
SETUP=/tmp/composer-setup.php
SIG=/tmp/composer-setup.sig
curl -fsSL -A fuel-core-tests -o "$SETUP" https://getcomposer.org/installer
curl -fsSL -A fuel-core-tests -o "$SIG" https://composer.github.io/installer.sig
expected=$(tr -d '[:space:]' < "$SIG")
actual=$(php -r "echo hash_file('sha384', '$SETUP');")
if [ "$actual" != "$expected" ]; then
	echo "ERROR: Composer installer checksum mismatch" >&2
	exit 1
fi
php "$SETUP" --install-dir=/tmp --filename=composer.phar >&2
rm -f "$SETUP" "$SIG"
if [ -n "$PLATFORM_PHP" ]; then
	php /tmp/composer.phar config platform.php "$PLATFORM_PHP"
fi
php /tmp/composer.phar update --no-interaction >&2
php /tmp/composer.phar show phpseclib/phpseclib 2>/dev/null | grep -qE '3\.0\.(5[89]|[6-9][0-9])|3\.[1-9]' \
	|| { echo "ERROR: phpseclib must be >= 3.0.58" >&2; exit 1; }
if [ "$RUN_AUDIT" = "1" ]; then
	php /tmp/composer.phar audit >&2
fi
tar -C /tmp/w -cf - vendor
