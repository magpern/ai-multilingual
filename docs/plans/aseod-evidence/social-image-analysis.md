# A.SEOd Evidence — Social Image Analysis

**Sources:** Rank Math `includes/opengraph/class-image.php`, Facebook/Twitter image methods, Woo gallery hooks

## Resolution order (simplified)

1. Explicit Rank Math social image meta (`facebook_image` / id)
2. Featured image
3. Content images (Rank Math may scan content — foreign owner behavior)
4. Defaults / Woo category thumb

## Multilingual assessment

| Aspect | Translatable? | Safe AIML approach |
|---|---|---|
| Image binary / attachment ID | No | Do not create language-specific image persistence |
| Image URL | Same asset across languages typically | Leave Rank Math; ensure absolute URL via owner |
| Image alt text | Possibly in attachment meta | Must not mutate Media Library; no annexation this wave |
| Dimensions / MIME | Machine | Untouched |
| Woo gallery extras | Machine / media | Untouched |

## Disposition recommendation

**SD4 / SD9 → Deferred.** No evidence of a safe official seam for language-correct social images without Media Library mutation, new persistence, or inventing per-language image identity. Prefer candidate-local deferral over redesign.
