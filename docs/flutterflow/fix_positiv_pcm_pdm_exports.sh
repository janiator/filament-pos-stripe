#!/usr/bin/env bash
# FlutterFlow desktop sometimes exports custom-class files as `pcm_internal_library`
# and `pdm_internal_library` without the `.dart` suffix, while thin widgets import
# `pcm_internal_library.dart` / `pdm_internal_library.dart`. Run after each download.
set -euo pipefail
ROOT="${POSITIV_FLUTTER_EXPORT_ROOT:-$HOME/Library/Application Support/io.flutterflow.prod.mac/p_o_sitiv}"
CC="$ROOT/lib/custom_code"
for base in pcm_internal_library pdm_internal_library; do
  if [[ -f "$CC/$base" ]] && [[ ! -f "$CC/$base.dart" ]]; then
    mv "$CC/$base" "$CC/$base.dart"
    echo "Renamed $base -> $base.dart"
  elif [[ -f "$CC/$base.dart" ]]; then
    echo "OK: $base.dart already present"
  else
    echo "WARN: neither $CC/$base nor $CC/$base.dart exists" >&2
  fi
done
