# A.6 — Identity + Workspace contract (N1)

**Status:** Frozen for implementation

| Contract | Value |
|---|---|
| Identity family | Existing post field only — **no** new family |
| Segment key / field | `post_title` (`Extractor::FIELD_TITLE`) |
| `source_type` | `Store::SOURCE_POST` (`post`) |
| `source_id` | `nav_menu_item` post ID |
| `source_subtype` | `nav_menu_item` |
| PluginIdentity | **Unused** for N1 |
| `p:` keys | **None** for N1 |
| Path / fuzzy identity | Forbidden |
| Store / schema | Unchanged; TARGET **6** |
| Workspace types | `post`, `page`, `product`, **`nav_menu_item`** |
| Workspace workflow | Existing Store/Review/TM/Glossary/Jobs path only |
| List label | `Menu item: {title}` for `nav_menu_item` |
