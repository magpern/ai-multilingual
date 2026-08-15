# P0 Admitted Term/Archive Shape Evidence Map

**Milestone:** Localized URL Operator Completion P0  
**Version:** 1.5.1 · TARGET 8 · Migration NONE  

| Admitted shape / taxonomy family | Lifecycle available? | Operator surface | Implementation family | Materially different? | Acceptance evidence |
|---|---|---|---|---|---|
| `term_archive` / `category` | Yes (SlugCandidateService + publish_term_route) | Term edit Localized URLs panel + TermSlugController REST | Hierarchical core taxonomy | Path may include hierarchy | `P0TermSlugOperatorRestTest` (category) |
| `term_archive` / `post_tag` | Yes | Same panel/REST | Flat core taxonomy | Flat path leaf | `P0TermSlugOperatorRestTest` (post_tag) |
| `term_archive` / `product_cat` | Yes when Woo present | Same | Woo hierarchical catalog | Woo category base | Family covered when Woo available; same TermSlugController |
| `term_archive` / `product_tag` | Yes when Woo present | Same | Woo flat catalog | Flat product tag | Same family as post_tag path builder via term services |
| `term_archive` / `pa_*` | Yes when Woo attributes present | Same | Attribute value taxonomies | Same term_archive publish path | Same TermSlugController; AdmittedTaxonomies gate |
| `page_hierarchical` (post shape) | Yes (existing post REST) | Translator Workspace LocalizedSlugPanel | Post/page Workspace slug REST | Hierarchical pages | Workspace UI + existing Mseo1/post REST |
| `product_category_permalink` | Yes (product post REST) | Workspace LocalizedSlugPanel on products | Woo product path builder | Product paths | Workspace UI + existing product publish_route |

**AC2 rule:** Every currently admitted term/archive shape exposing the lifecycle is administrator-operable via the term edit panel (no raw REST/CLI required). Automated tests exercise one representative per implementation-equivalent family (`category`, `post_tag`) plus post/product Workspace flows.

**Unsupported taxonomies** (not in AdmittedTaxonomies) are rejected by TermSlugController with `aiml_term_taxonomy_unsupported`.
