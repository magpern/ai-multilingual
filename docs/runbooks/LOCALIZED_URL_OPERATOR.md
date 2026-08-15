# Localized URL Operator Runbook

**Audience:** WordPress administrators  
**Plugin version:** 1.5.1  

## Enable Localized URLs

1. Open **AI Multilingual → Settings**.  
2. Under **Localized URLs**, use **Enable localized URLs** (or **Retry activation** if Failed).  
3. Wait until State shows **On**. While **Activating**, visitors may still see source-slug URLs.  
4. Review **Capability admission** and **Hierarchy reindex / frontier** on the same Settings screen.

CLI is optional diagnostics only — not required for ordinary operation.

## Posts, pages, and products

1. Open **AI Multilingual → Translator Workspace**.  
2. Select the content and target language.  
3. In **Localized URL slug**:
   - **Generate** a candidate from the translated title, or type a manual candidate and **Save**.  
   - **Publish route** when ready.  
   - If you see a collision message: edit the candidate, **Clear**, or choose another slug, then publish again.  
4. Confirm the **Effective path** and **Sync** state.

## Terms (categories, tags, product categories, …)

1. Edit the term in **Posts → Categories** (or the matching taxonomy screen).  
2. Use the **Localized URLs** panel:
   - Generate / save / clear candidate  
   - Publish route  
   - Recover from collisions the same way as posts  

Unsupported taxonomies do not show this panel.

## Reading status

| Message | Meaning |
|---|---|
| Not admitted yet | Capability verification not finished (not yet processed) |
| Pending frontier | Hierarchy rematerialization still running |
| Degraded / Failed | Processing problem — not “unsupported” |
| Unsupported taxonomy | Shape not offered for localized routes on this site |

## Advanced

Engineers may use `wp aiml localized-urls status|capabilities|reindex-status` for diagnostics. Merchants should not need these for OC1–OC8 workflows.
