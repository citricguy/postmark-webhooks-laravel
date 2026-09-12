#!/usr/bin/env bash
set -euo pipefail

task_php="${PHP_BINARY:-php}"
task_composer="${COMPOSER_BINARY:-$(command -v composer)}"
task_root="$(cd "$(dirname "$0")/.." && pwd)"
task_laravel="${1:-13}"
task_resolution="${2:-latest}"
task_directory="$("$task_php" "$task_root/tools/prepare-matrix.php" "$task_laravel" "$task_resolution")"
task_flags=(--prefer-stable --prefer-dist --no-interaction --no-progress)
if [[ "$task_resolution" == "lowest" ]]; then
    task_flags+=(--prefer-lowest)
fi

"$task_php" "$task_composer" update --working-dir="$task_directory" "${task_flags[@]}"
"$task_php" "$task_composer" validate --strict --working-dir="$task_directory"
"$task_php" "$task_composer" check-platform-reqs --working-dir="$task_directory"
"$task_php" "$task_composer" audit --locked --working-dir="$task_directory"
"$task_php" "$task_composer" test --working-dir="$task_directory"
"$task_php" "$task_composer" test:consumer --working-dir="$task_directory"
