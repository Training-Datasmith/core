#!/usr/bin/env bash
# Core test runner: digest-pinned PHP CLI images, checksummed Composer installer.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"

if [ "$USE_SUDO_DOCKER" = "1" ]; then
	DOCKER="sudo docker"
else
	DOCKER="docker"
fi

BUILD_IMAGE="docker.io/library/php@sha256:604de2f0cba9e9dde16cf717c2f03b0948c99949bdf7c11cdf76326e0da7d2ea"
PHP56_IMAGE="docker.io/library/php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143"
PHP72_IMAGE="docker.io/library/php@sha256:42ffbc0798e4449bbd1e14fc4dcb87774aa1ad1900a09ef6a965bc0880aa2161"

PHPUNIT_5727_SHA384="30b1e2d435bddcd37178c96946ceaac1cbb0583ce2149d0fc59e5e1036cea24a5428e374f1fb39aa0c1d3e004fe44344"
PHPUNIT_7520_SHA384="079d70e7e6496c7f50ce3040613fc85fa2331b0309170e4e8a23fc512795228b7ad7c924de29e6b7dccc0513fceda405"

if ! command -v $DOCKER >/dev/null 2>&1; then
	echo "ERROR: docker not found (set USE_SUDO_DOCKER=1 if docker needs sudo)" >&2
	exit 127
fi

curl -fsSL -A 'fuel-core-tests' \
	'https://packagist.org/api/security-advisories/?packages[]=phpseclib/phpseclib' \
	>/dev/null || {
	echo "ERROR: could not fetch Packagist phpseclib advisories" >&2
	exit 1
}

assert_phpunit_log() {
	local log="$1"
	if grep -q 'Warning:' "$log"; then
		echo "ERROR: PHPUnit output contained Warning:" >&2
		return 1
	fi
	if ! grep -E '^OK \([0-9]+ tests?, [0-9]+ assertions?\)$' "$log" >/dev/null; then
		echo "ERROR: missing full-line OK (N tests, M assertions)" >&2
		return 1
	fi
}

assert_smoke_log() {
	local log="$1"
	if grep -q 'Warning:' "$log"; then
		echo "ERROR: smoke output contained Warning:" >&2
		return 1
	fi
	if grep -q 'Deprecated:' "$log"; then
		echo "ERROR: smoke output contained Deprecated:" >&2
		return 1
	fi
	if ! grep -q '^SMOKE OK$' "$log"; then
		echo "ERROR: smoke did not print SMOKE OK" >&2
		return 1
	fi
}

docker_composer_vendor() {
	local platform_php="$1"
	local run_audit="$2"

	$DOCKER run --rm \
		-v "$REPO_ROOT:/work:ro" \
		-v "$REPO_ROOT/tests/docker-composer-vendor-inner.sh:/inner.sh:ro" \
		-e "PLATFORM_PHP=$platform_php" \
		-e "RUN_AUDIT=$run_audit" \
		"$BUILD_IMAGE" \
		bash /inner.sh
}

build_vendor_tar() {
	local platform_php="$1"
	local run_audit="$2"
	local out="$3"
	docker_composer_vendor "$platform_php" "$run_audit" >"$out"
}

verify_monolog_installers() {
	local expect_monolog_1="$1"
	local vendor_tar="$2"
	$DOCKER run --rm \
		-v "$vendor_tar:/vendor.tar:ro" \
		-e "EXPECT_MONOLOG_1=$expect_monolog_1" \
		"$BUILD_IMAGE" \
		bash -c '
		set -eu
		mkdir -p /tmp/v
		tar -xf /vendor.tar -C /tmp/v
		php -r '\''
		$expect1 = (int) getenv("EXPECT_MONOLOG_1");
		$j = json_decode(file_get_contents("/tmp/v/vendor/composer/installed.json"), true);
		$packages = isset($j["packages"]) ? $j["packages"] : $j;
		foreach ($packages as $p) {
			if ($p["name"] === "monolog/monolog") {
				$mono = $p["version"];
				if ($expect1 && !preg_match("/^v?1\\./", $mono)) {
					fwrite(STDERR, "expected monolog 1.x got $mono\n");
					exit(1);
				}
				if (!$expect1 && !preg_match("/^v?3\\./", $mono) && version_compare(ltrim($mono, "v"), "2.3.4", "<")) {
					fwrite(STDERR, "expected monolog >= 2.3.4 or 3.x got $mono\n");
					exit(1);
				}
			}
			if ($p["name"] === "composer/installers") {
				$inst = $p["version"];
				if ($expect1 && !preg_match("/^v?1\\./", $inst)) {
					fwrite(STDERR, "expected installers 1.x got $inst\n");
					exit(1);
				}
				if (!$expect1 && version_compare(ltrim($inst, "v"), "2.3.0", "<")) {
					fwrite(STDERR, "expected installers >= 2.3 got $inst\n");
					exit(1);
				}
			}
		}
		'\''
	'
}

run_phpunit_phar() {
	local label="$1"
	local image="$2"
	local phar_url="$3"
	local phar_sha="$4"
	local vendor_tar="$5"
	local log
	log=$(mktemp)

	echo "========== phpunit $label =========="

	$DOCKER run --rm \
		-v "$REPO_ROOT:/work:ro" \
		-v "$vendor_tar:/vendor.tar:ro" \
		-v "$REPO_ROOT/tests/docker-phpunit-phar-inner.sh:/inner.sh:ro" \
		-e "PHPUNIT_URL=$phar_url" \
		-e "PHPUNIT_SHA384=$phar_sha" \
		-e "VENDOR_TAR=/vendor.tar" \
		"$image" \
		bash /inner.sh 2>&1 | tee "$log"

	assert_phpunit_log "$log"
	rm -f "$log"
}

run_smoke_php82() {
	local vendor_tar="$1"
	local log
	log=$(mktemp)

	echo "========== smoke php82 =========="

	$DOCKER run --rm \
		-v "$REPO_ROOT:/work:ro" \
		-v "$vendor_tar:/vendor.tar:ro" \
		-v "$REPO_ROOT/tests/docker-smoke-php82-inner.sh:/inner.sh:ro" \
		-e "VENDOR_TAR=/vendor.tar" \
		"$BUILD_IMAGE" \
		bash /inner.sh 2>&1 | tee "$log"

	assert_smoke_log "$log"
	rm -f "$log"
}

main() {
	echo "Official php:5.6-cli and php:7.2-cli images use EOL Debian without working apt; vendor is built on php:8.2-cli with composer platform.php, then PHPUnit runs on the target PHP version."
	echo "Composer vendor tarballs stay in the container and on temp files; the repo vendor/ tree is not modified."

	VENDOR56=$(mktemp)
	VENDOR72=$(mktemp)
	VENDOR82=$(mktemp)
	trap 'rm -f "$VENDOR56" "$VENDOR72" "$VENDOR82"' EXIT

	echo "========== composer solve php56 =========="
	build_vendor_tar "5.6.40" "0" "$VENDOR56"
	verify_monolog_installers 1 "$VENDOR56"
	run_phpunit_phar php56 "$PHP56_IMAGE" \
		"https://phar.phpunit.de/phpunit-5.7.27.phar" \
		"$PHPUNIT_5727_SHA384" \
		"$VENDOR56"

	echo "========== composer solve php72 =========="
	build_vendor_tar "7.2.34" "1" "$VENDOR72"
	verify_monolog_installers 0 "$VENDOR72"
	run_phpunit_phar php72 "$PHP72_IMAGE" \
		"https://phar.phpunit.de/phpunit-7.5.20.phar" \
		"$PHPUNIT_7520_SHA384" \
		"$VENDOR72"

	echo "========== composer solve php82 =========="
	build_vendor_tar "" "1" "$VENDOR82"
	verify_monolog_installers 0 "$VENDOR82"
	run_smoke_php82 "$VENDOR82"

	echo "All matrix runs passed (PHP 5.6/7.2 PHPUnit; PHP 8.2 smoke)."
}

main "$@"
