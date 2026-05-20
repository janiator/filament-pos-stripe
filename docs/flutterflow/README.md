# POSitiv (FlutterFlow)

FlutterFlow work for the POS app uses the **FlutterFlow AI workspace** and MCP — not copy-paste snippets in this folder anymore.

## Workspace

| Item | Path |
|------|------|
| **Working directory** | [`positiv_flutterflow_ai/`](../positiv_flutterflow_ai/) (from pos-stripe repo root) |
| **Cloud project id** | `pointofsale-xrlz5i` |
| **MCP server** | `user-flutterflow-positiv` |
| **Desktop export** | `~/Library/Application Support/io.flutterflow.prod.mac/p_o_sitiv` |

## Workflow

1. **Refresh cloud context** before editing:
   ```bash
   cd positiv_flutterflow_ai
   flutterflow ai refresh-context pointofsale-xrlz5i
   ```
2. **Edit custom code** in FlutterFlow Designer, or edit the local export under `generated_code/` after refresh.
3. **Push wiring / upserts** via MCP `validate` then `run` on a script in `positiv_flutterflow_ai/dsl/`.
4. **Pull in Desktop** — Sync / download latest custom code.
5. **Fix export quirks** after Desktop download:
   ```bash
   positiv_flutterflow_ai/scripts/fix_positiv_custom_code_exports.sh
   ```

## Source of truth

- **Cloud project** — authoritative UI, action wiring, and custom code.
- **`positiv_flutterflow_ai/generated_code/`** — local snapshot after `refresh-context`; DSL upserts read from here.
- **`positiv_flutterflow_ai/dsl/`** — version-controlled push scripts (tracked in git).

See also `.cursor/rules/flutterflow-mcp-required.mdc` and `.cursor/rules/multi-repo-workspace.mdc`.
