# ADR-0024 — Anonymous-language cache contract

## Status
Accepted.

## Context
The deployment target sits behind a full-page reverse-proxy cache whose key is
`scheme | host | request_uri | currency_bucket`. Language is not an explicit
dimension of that key. That is only safe if this plugin never makes an
anonymous, cookie-less visitor's rendered language depend on anything other
than `host + request_uri` — otherwise two visitors requesting the identical
cache key could legitimately receive different HTML, and the reverse proxy
would serve one visitor's language to the other.

Milestone 1 built the router this way deliberately (`LanguageResolver.php`,
ADR-0002): URL prefix, then the default language, with no cookie and no
`Accept-Language` sniffing, specifically so responses stay cacheable at the
edge (`LanguageResolver.php:24-27`, `docs/HOOKS.md`). That reasoning was
recorded as an internal cacheability note, not as a contract with a specific
external cache architecture. This ADR makes the contract explicit and durable,
independent of which reverse proxy is deployed in front of it, so a future
change to language resolution cannot quietly break it.

This ADR was written after an external review confirmed the current
implementation satisfies it (`Router::resolve()`, `LanguageResolver::resolve()`):
resolution reads only the request path; `current_user_can()` gates preview
languages but never chooses *which* language renders for an anonymous,
unauthenticated visitor; no code path in `src/` reads `$_COOKIE`, a session, or
`HTTP_ACCEPT_LANGUAGE`, or performs geo/IP-based selection
(`PluginGuardTest::test_no_cookie_is_set`, `RoutingTest::test_routing_sets_no_cookie`).

## Decision
**Anonymous language resolution must remain URL/host-authoritative.**

For an anonymous, cacheable request, `host + request_uri` must be sufficient
to determine the rendered language. AI Multilingual must not cause the same
anonymous URL to legitimately render different language variants based on
visitor-specific state.

Introducing a language cookie, `Accept-Language` negotiation, geo/location-based
language selection, or any other same-URL visitor-specific language selection
is a **cache-contract change** and requires explicit cache-compatibility review
(with whoever owns the reverse-proxy cache architecture at the time) before
release — not just a routing or translation-scope decision made inside this
repository.

Localized URLs (`/sv/...`, ADR-0002, ADR-0023) satisfy this contract today:
every language other than the default gets a distinct URL, the default
language always owns the unprefixed root, and nothing downstream re-derives
language from anything but that URL. Because URL isolation already holds,
language does not currently need to be an additional dimension of the
reverse-proxy cache key — `request_uri` already carries it.

## Scope
This ADR concerns **language isolation only**. It does not declare that every
WooCommerce or WordPress response is cacheable, and it changes nothing about
what already must stay uncached: cart, session, nonces, login state, and other
per-visitor personalization remain the responsibility of whatever cache/bypass
policy the surrounding infrastructure defines, exactly as they would without
this plugin installed. This plugin does not decide that policy and does not
implement it.

This plugin also does not acquire any responsibility for operating the
reverse-proxy cache. It must not directly control, configure, or purge an
external HTTP/edge cache as part of satisfying this contract — the internal
object-cache invalidation this plugin already performs (`ADR-0012`,
`src/Cache/Cache.php`, `src/Rollout/Cache/`) is a separate, internal concern
and stays that way. Nothing here obliges this plugin to talk to nginx, a CDN,
or any other edge layer.

## Consequences
- Language resolution changes (`src/Language/LanguageResolver.php`,
  `src/Routing/Router.php`, and anything else that can set
  `LanguageContext::current()`) must be checked against this contract before
  merge, in addition to their existing routing/SEO correctness checks.
- A regression test at the `Router` boundary (`RoutingTest`) exists
  specifically to make an accidental violation — a cookie, header, or
  geo-derived override reaching the same URL — fail CI rather than surface as
  a cache-poisoning bug in production.
- If a future milestone genuinely needs visitor-specific anonymous language
  selection (the historical, unshipped "language cookie" line under the old
  M4 WooCommerce milestone in `docs/ROADMAP.md` is the most likely candidate),
  that milestone must reopen this ADR and resolve one of: encode the selection
  into the URL instead, add a cache-key dimension in coordination with
  whoever owns the cache architecture, or scope the feature to authenticated
  visitors only (who are already outside anonymous caching).
