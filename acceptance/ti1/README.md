# TI.1 Acceptance Notes

**Branch:** `feature/ti1-persist-path-structural-safety`
**Method:** Deterministic fake/scripted providers (no live OpenAI for invalid cases)

## Valid output

| Case | Result |
|---|---|
| Scripted valid translate → Store write | PASS (`PersistPathStructuralSafetyTest::test_valid_translation_persists`) |
| SV decimal `1.5` → `1,5` persists after TS7 narrow | PASS |
| Jobs EchoAIProvider success path (existing suite) | Covered by JobsItemProcessor / worker suites |

## Invalid output (no Store write)

| Case | Code | Result |
|---|---|---|
| Empty translated string | `empty_target` | PASS |
| Missing segment key | `aiml_ai_invalid_response` | PASS |
| Placeholder loss | `placeholder_mismatch` | PASS + prior row preserved |
| Forbidden markup / URL loss | `forbidden_markup` / `url_mismatch` | PASS |
| Jobs empty → FAILED terminal | `empty_target` permanent | PASS |
| Approved conflict gate unchanged | `skipped_conflict` | PASS |
| Human row preserved on reject | status unchanged | PASS |

## Live OpenAI

Not required for TI.1 invalid-case proof. Optional happy-path only; not in CI.
