# A.SEOd Evidence — Twitter Card Analysis

**Source:** Rank Math 1.0.275 `includes/opengraph/class-twitter.php`

---

## Emission pipeline

Action `rank_math/opengraph/twitter`:

| Priority | Method | Tag |
|---|---|---|
| 1 | `use_facebook` | Switches meta prefix (does **not** suppress tags) |
| 5 | `type` | `twitter:card` |
| 10 | `title` | `twitter:title` |
| 11 | `description` | `twitter:description` |
| 30 | `image` | `twitter:image` |

Per-tag filters: `rank_math/opengraph/twitter/{prop}` (e.g. `twitter_title`).

Disable switch: `rank_math/opengraph/twitter_card` → `false` skips entire Twitter class.  
Card type filter: `rank_math/opengraph/twitter/card_type`.

---

## “Use Facebook” behavior

- Meta key: `twitter_use_facebook` (DB `rank_math_twitter_use_facebook`)
- **Default `true`** when meta missing
- When truthy: `$this->prefix = 'facebook'` so title/desc/image **reads** use Facebook keys / Paper fallback
- Twitter tags are **still emitted** with mirrored values

Live: `twitter:title` ≡ `og:title`, `twitter:description` ≡ `og:description`, `twitter:image` ≡ `og:image`, `twitter:card` = `summary_large_image`.

---

## Identity implication

When `twitter_use_facebook` is true (default), Twitter textual values share the Facebook → Paper → A.SEOc SEO identity path.  
Do **not** create a second Store identity for the same semantic value.  
Explicit `rank_math_twitter_title|description` are separate Rank Math fields (see admission matrix for partial treatment).

---

## Card type

`twitter:card` is configuration / machine choice (`summary`, `summary_large_image`, player, app). Not a multilingual string. Rank Math remains sole owner; AIML must not invent card-type translations.
