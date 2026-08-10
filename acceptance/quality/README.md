# TQ.0 Manual Quality Acceptance

**Not CI.** These scripts require live OpenAI credentials via WordPress Settings / vault.

## Scripts

| Script | Purpose |
|---|---|
| `generate.php` | Live-generate C1.0 with persist-path parity; writes evidence pack |
| `llm-judge.php` | Optional Class C advisory judge (stub) |

## generate.php

Run from WordPress (wp eval-file), mirroring `acceptance/rc/v1-openai-rc.php`:

```bash
cd apps/wordpress && docker compose run --rm wpcli \
  wp eval-file /path/to/acceptance/quality/generate.php \
  -- /opt/biopentra/dev/ai-multilingual/tests/quality/baselines/_staging-v1.1.0/
```

Prerequisites:

- AI enabled, provider `openai`, model set, encrypted key present
- Never prints API keys, Authorization headers, or raw prompts

Output pack shape matches `tests/quality/baselines/baseline-v1.1.0/` (manifest, generations.jsonl, scores after scoring).

## llm-judge.php

Optional Class C advisory scoring. Records `judge_version` in output. **Cannot** clear Class A deterministic failures.

## Promotion

After human review, promote staging output to official `tests/quality/baselines/baseline-v1.1.0/` with fingerprints frozen.
