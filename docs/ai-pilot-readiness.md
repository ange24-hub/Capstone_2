# SB-ISPGM local AI pilot readiness

Audit date: 2026-09-08. Source: read-only aggregate queries against the application's configured database. Counts describe encoded records, not verified population or complete reporting coverage. No resident-level data was exported.

## Initial coverage

Start with the 22 barangays with inhabitant records. Add remaining barangays after encoding and review; eligibility does not establish completeness.

| Barangay | Inhabitant records | Household records |
| --- | ---: | ---: |
| Biasong | 214 | 62 |
| Cabascan | 507 | 109 |
| Camansi | 466 | 96 |
| Canlupao | 885 | 212 |
| Carnaga | 468 | 107 |
| Cawayan | 839 | 212 |
| Higosoan | 401 | 116 |
| Hinagtikan | 329 | 74 |
| Hinapo | 625 | 167 |
| Hugpa | 326 | 82 |
| Iniguihan | 432 | 117 |
| Looc | 1043 | 224 |
| Luan | 222 | 59 |
| Mag-ata | 395 | 102 |
| Maslog | 975 | 231 |
| Punong | 571 | 153 |
| Rizal | 692 | 173 |
| San Agustin | 657 | 168 |
| San Antonio | 782 | 208 |
| San Isidro | 1340 | 347 |
| San Miguel | 540 | 129 |
| San Roque | 368 | 111 |

No inhabitant or household records found for Anahawan, Banday, Bogo, Cambite, Maanyag, Mapgap, and Tinago. Display these as not yet encoded, rather than zero population.

## Findings that constrain analytics

- The migration table contains one event, in Canlupao for September 2026. It does not yet support forecasting or seasonal conclusions.
- No submitted monthly RBI reports were found through the report owner's barangay relationship. Encoded registry records and submitted reports are separate forms of coverage.
- New-inhabitant and deceased records exist, but their dates, completeness, and links to active records still require review. Do not automatically treat new-inhabitant rows as migration events or add them to registry totals: they may already be linked to active inhabitants.
- No dedicated incident or complaint model/table definition was found in the reviewed application models and migrations. Incident-based indicators require a defined source and workflow.
- Current resident counts are snapshots. They do not establish historical population growth.
- A month without recorded migration events is not necessarily a confirmed zero-movement month. Reporting completeness must be tracked before treating missing months as zeros in forecasting.

## First implementation scope

1. Display pilot coverage and distinguish encoded, partially encoded, and verified complete data. Completeness requires staff confirmation for a defined reporting period; a positive record count alone is insufficient.
2. Provide authorized current registry and household summaries for pilot barangays, with explicit coverage and source dates. Municipal summaries must say they cover available records only.
3. Connect a locally hosted language model through Laravel for natural-language questions and narrative explanations. Database queries and calculations remain server-controlled and role-scoped. Resident records must not be sent to a cloud AI provider.
4. Produce PDF reports from verified query results and local narrative output, including reporting scope and limitations.
5. Enable forecasts only after dated historical records and reporting completeness support evaluation against held-out observations and a simple baseline. Until then display insufficient data for forecasting.

Governance risk thresholds, intervention guidelines, and incident categories remain proposed work requiring domain review. Do not infer individual behavioral risk from demographics or present correlations as proven root causes.

## Remaining prerequisites

- Inspect application-server RAM, CPU, GPU/VRAM and deployment location before choosing a local model.
- Review missing dates, duplicate identities and cross-table links before publishing analytical counts as verified figures.
- Agree on indicator definitions and staff-approved intervention guidance before implementing governance recommendations.

This document records readiness and pilot scope. It does not claim that local AI, PDF generation through the assistant, or forecasting is already implemented.
