# Manuscript alignment

Reviewed source: `SB_ISPGM_manuscript_revised_online_payment.docx`, supplied by the user, 10 September 2026. The source document was read, not modified. This is a requirements comparison, not verification of its literature citations.

## Architectural requirements

The Scope and Limitations and Figure 3.3 descriptions specify local AI processing with no external AI APIs or cloud language models. An internal loopback HTTP API between Laravel and a local model is compatible with this requirement. Online payment is a separate permitted external integration; it does not authorize sending registry data to an external AI service.

## Implementation sequence and acceptance criteria

| Module | Current position | Next acceptance criterion |
| --- | --- | --- |
| Population and migration reports | Database summaries and formal PDF exports exist. | Reconcile sampled outputs with source records and have staff review the document format. Keep incomplete encoding visible. |
| On-premise assistant | Scoped report answers and a local narrative adapter are implemented. Ollama and qwen2.5:1.5b are installed for the local pilot. Query routing uses keywords, not general natural-language understanding. | Continue staff evaluation of supported questions, role boundaries, output meaning, and response times; see local-ai-operations.md. |
| Retrieval-based assistant | Selected aggregate report facts can be supplied to the local model. | Validate supported intents and retrieval coverage. Do not describe the current keyword router as a complete RAG system. |
| AI behavioral analysis | Removed as a standalone feature because its indicators overlapped existing workflows. | Revise the manuscript scope accordingly; use existing reports and dashboards for recorded activity. See behavioral-analysis.md. |
| Predictive analytics | Historical migration summaries exist; forecasting is not enabled. | Audit dated history and completeness; define targets; evaluate local Python/Scikit-learn models against a baseline using chronological holdout data and report errors/limitations. Missing barangay data is not zero. |
| Governance activity / census-related outputs | Requires a separate output and source-field audit. | Specify tables, reporting periods, terminology and staff validation. Current registry snapshots must not be represented as a complete census. |
| Online payments | Identified as manuscript scope; implementation not audited in this review. | Select the provider and validate fee/exemption rules, signed callbacks, duplicate handling, and request/payment statuses in a test environment. |

## Definitions still needed

- Resident concerns: identify the actual incident/service/request categories recorded in the system. Do not infer concerns from age, disability, residence or migration alone.
- Governance indicators: agree on numerator, denominator, time window, missing-data handling and review process before choosing thresholds.
- Possible root causes: pattern association does not establish causation. Require corroborating records and authorized staff review; otherwise label the output as a hypothesis.
- Suggested interventions: use staff-approved guidance tied to defined indicators, with source evidence and human review.
- Seasonality: requires repeated comparable historical periods; current-year monthly totals alone cannot establish a seasonal pattern.

The manuscript names Bootstrap 5, while the user has requested Tailwind CSS v4 in the application. Update that manuscript technology description when documenting the final implementation. Preserve the user's navy branding.

## Local assistant configuration

`config/local_ai.php` reads these optional environment settings:

```dotenv
LOCAL_AI_ENABLED=false
LOCAL_AI_URL=http://127.0.0.1:11434
LOCAL_AI_MODEL=qwen2.5:1.5b
LOCAL_AI_TIMEOUT=25
```

Use a local-only runtime (`OLLAMA_NO_CLOUD=1`) bound to `127.0.0.1:11434`. The configured model passed limited synthetic pilot checks, not production validation. The adapter accepts only literal loopback URLs, disallows redirects/proxies, rejects cloud model identifiers and remote model metadata, and sends a server-verified textual observation rather than resident names, raw questions, or numerical totals. Laravel supplies the exact counts and limitation notes. Failed or invalid generation falls back to the database summary. These checks cannot prove semantic correctness; human evaluation remains necessary.

The optional narrative does not train a model, forecast future population, validate causes, or implement the complete behavioral-analysis module.
