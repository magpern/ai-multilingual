# TSC.1 Implementation Baseline

**Branch:** `feature/tsc1-first-class-taxonomy-terms`  
**Created from:** `main` @ `2b2a2169134292cc132c0b42325a8e04988a7cd4`  
**Frozen plan:** [TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md](TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md)  
**Freeze merge:** `1fcf8d2e3088b09174526643e13a2d8ccf5cb2d4`  
**Planning freeze review:** PASS  
**ADR:** [0021-first-class-taxonomy-term-identity-and-lazy-adoption.md](../adr/0021-first-class-taxonomy-term-identity-and-lazy-adoption.md) (**Accepted**)  
**Version at start:** **1.3.0**  
**Migrator::TARGET:** **7**  
**Schema:** STATE A — no migration  

## Scope

| Item | Value |
|---|---|
| TT matrix | TT1–TT40 |
| AC count | AC1–AC58 (contiguous) |
| WP ladder | TSC1.0 → TSC1.8 |
| Implementation status at baseline | NOT STARTED |

## Hard exclusions

Public surface/taxonomy admission APIs; attribute taxonomy labels as SOURCE_TERM; product-local attributes; broad `get_term` mutation; second Store; second Jobs/publication policy; durable alias table; TARGET/version bump; eager catalog migration; TSC.2+ surfaces; Elementor/Woo config observers (TSC.3/5).

## STOP conditions

Schema/TARGET required; adoption non-transactional; lifecycle unsafe; axis/adopt race unsolvable; `get_term` mutation necessary; second Store/policy; permanent dual-write; unbounded migration; resolver write/lock; attribute labels as SOURCE_TERM.

## Expected validation gates

PHPCS; unit; integration; PluginGuard; quality; baseline verification; build/ZIP; focused TSC.1 tests; bounded browser acceptance (non-CI).

## Exact next step after baseline

Implement TSC1.0–TSC1.8 on this branch; consolidated validation; independent review; PR; merge; closure. Do not start TSC.2.
