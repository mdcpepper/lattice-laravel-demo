#!/usr/bin/env bash
set -euo pipefail

: "${LATTICE_EXT_REPO:=mdcpepper/lattice}"
: "${LATTICE_EXT_TAG:=latest}"

api_base="https://api.github.com/repos/${LATTICE_EXT_REPO}"
headers=(-H "Accept: application/vnd.github+json")

if [[ -n "${GITHUB_TOKEN:-}" ]]; then
  headers+=(-H "Authorization: Bearer ${GITHUB_TOKEN}")
fi

php_version="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
php_thread_safety="$(php -r 'echo (defined("PHP_ZTS") && PHP_ZTS) ? "zts" : "nts";')"
extension_dir="$(php -r 'echo ini_get("extension_dir");')"

if [[ -z "${extension_dir}" ]]; then
  echo "Failed to determine PHP extension_dir."

  exit 1
fi

if [[ "${LATTICE_EXT_TAG}" == "latest" ]]; then
  release_url="${api_base}/releases/latest"
else
  release_url="${api_base}/releases/tags/${LATTICE_EXT_TAG}"
fi

release_json="$(curl -fsSL "${headers[@]}" "${release_url}")"

escaped_php_version="${php_version//./\\.}"
asset_suffix=""

if [[ "${php_thread_safety}" == "zts" ]]; then
  asset_suffix="-zts"
fi

asset_pattern="^lattice-[A-Za-z0-9._-]+-linux-php${escaped_php_version}${asset_suffix}\\.so$"

asset_name="$(
  printf '%s' "${release_json}" \
    | jq -r '.assets[]?.name' \
    | grep -E "${asset_pattern}" \
    | head -n1 || true
)"

if [[ -z "${asset_name}" ]]; then
  echo "No release asset found for PHP ${php_version} (${php_thread_safety}) in ${LATTICE_EXT_REPO} (${LATTICE_EXT_TAG})."
  echo "Expected pattern: lattice-<tag>-linux-php${php_version}${asset_suffix}.so"
  echo "Allowed characters in <tag>: A-Z a-z 0-9 . _ -"

  exit 1
fi

if ! [[ "${asset_name}" =~ ^lattice-[A-Za-z0-9._-]+-linux-php${php_version}${asset_suffix}\.so$ ]]; then
  echo "Unsafe asset name: ${asset_name}"
  exit 1
fi

asset_url="$(
  printf '%s' "${release_json}" \
    | jq -r --arg name "${asset_name}" '.assets[] | select(.name == $name) | .browser_download_url'
)"

sha_name="${asset_name}.sha256"
sha_url="$(
  printf '%s' "${release_json}" \
    | jq -r --arg name "${sha_name}" '.assets[] | select(.name == $name) | .browser_download_url // empty'
)"

tmp_dir="$(mktemp -d)"
tmp_so="${tmp_dir}/lattice.so"
tmp_sha="${tmp_dir}/lattice.so.sha256"

cleanup() {
  rm -rf -- "${tmp_dir}"
}

trap cleanup EXIT

curl -fsSL -o "${tmp_so}" "${asset_url}"

if [[ -n "${sha_url}" ]]; then
  curl -fsSL -o "${tmp_sha}" "${sha_url}"

  expected_hash="$(awk '{print $1}' "${tmp_sha}")"
  actual_hash="$(sha256sum "${tmp_so}" | awk '{print $1}')"

  if [[ "${expected_hash}" != "${actual_hash}" ]]; then
    echo "SHA256 mismatch for ${asset_name}"

    exit 1
  fi
fi

install -D -m 0644 "${tmp_so}" "${extension_dir}/liblattice.so"
echo "extension=liblattice.so" > /usr/local/etc/php/conf.d/zz-lattice.ini

echo "Installed ${asset_name} to ${extension_dir}/liblattice.so"
