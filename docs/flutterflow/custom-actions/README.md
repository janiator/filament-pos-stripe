# Custom actions (canonical Dart)

Files here are **body-only**: implementation and any extra imports (e.g. `dart:convert`, `package:http/http.dart`). They **must not** include FlutterFlow’s boilerplate:

- `// Automatic FlutterFlow imports`
- The schema/theme/util imports FlutterFlow injects
- `// Begin custom action code` / `// DO NOT REMOVE OR MODIFY THE CODE ABOVE!`

FlutterFlow adds that block in the designer and on export. Putting it in this repo (or in the payload to the FlutterFlow AI API) causes **duplicate imports** in the project.

When pasting into FlutterFlow manually, paste only into the **custom code** region **below** FlutterFlow’s fixed header—never replace the whole file with a copy that includes the automatic imports.

Upsert scripts under `positiv_flutterflow_ai/dsl/` call `customActionCodeForFlutterFlowApi()` so any legacy file that still contains the marker is trimmed before push.

Deferred resume helpers: `get_deferred_resume_context.dart`, `clear_deferred_resume_context.dart`, `serialize_cart_for_complete_deferred.dart` — push with `positiv_flutterflow_ai/dsl/upsert_deferred_resume_helpers.dart` (or FlutterFlow Positiv MCP **`run`** on that file). Optional **pos** banner + on-load wiring: `positiv_flutterflow_ai/dsl/wire_pos_deferred_resume_banner.dart`. Sync **`completePosPurchase`** updates: `positiv_flutterflow_ai/dsl/update_complete_pos_purchase.dart`. See `DEFERRED_PAYMENTS_FRONTEND.md`.
