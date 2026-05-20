#!/usr/bin/env bash
# FlutterFlow desktop sometimes exports custom-class files without the `.dart` suffix
# while imports reference `*.dart`. Run after each Desktop download / export-code sync.
set -euo pipefail
ROOT="${POSITIV_FLUTTER_EXPORT_ROOT:-$HOME/Library/Application Support/io.flutterflow.prod.mac/p_o_sitiv}"
CC="$ROOT/lib/custom_code"
for base in pcm_internal_library pdm_internal_library stripe_terminal_background_connect; do
  if [[ -f "$CC/$base" ]] && [[ ! -f "$CC/$base.dart" ]]; then
    mv "$CC/$base" "$CC/$base.dart"
    echo "Renamed $base -> $base.dart"
  elif [[ -f "$CC/$base.dart" ]]; then
    echo "OK: $base.dart already present"
  else
    echo "WARN: neither $CC/$base nor $CC/$base.dart exists" >&2
  fi
done
