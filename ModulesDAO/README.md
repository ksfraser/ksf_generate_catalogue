# ksf_ModulesDAO

Cross-platform DAO abstraction for KS Fraser modules.

## Goal

Provide small, composable interfaces + adapters so modules can read/write data via:

- Generic DB tables (PDO)
- WordPress (options/settings APIs)
- SuiteCRM (Administration settings)
- FrontAccounting (sys prefs or DB tables)
- File-backed key/value stores (INI/JSON/XML/CSV/YAML)
- CSV (tabular record store)
- XML (record/document store)

This is scaffolding intended to be expanded in the dedicated repo:
https://github.com/ksfraser/ksf_ModulesDAO
