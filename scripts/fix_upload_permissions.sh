#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_root="$(cd "${script_dir}/.." && pwd)"
upload_dir="${project_root}/assets/uploads"

mkdir -p "${upload_dir}"
chmod 0775 "${upload_dir}"
find "${upload_dir}" -type d -exec chmod 0775 {} +
find "${upload_dir}" -type f -exec chmod 0664 {} +

echo "Permission folder upload siap: ${upload_dir}"
