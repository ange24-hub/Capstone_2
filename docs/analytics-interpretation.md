# Analytics interpretation and review alerts

Migration Trends includes a read-only interpretation panel. Approved municipal staff can filter all barangays; barangay secretaries receive only their assigned barangay's observations and household alerts. Residents cannot access this staff page. Forecast access remains municipal-only.

## Calculations and sources

- Monthly counts are the same values used in the existing graph. Arrivals count classified RBI entries by first-save month; departures count migration events by movement date. Their difference is not a verified population change because the date bases differ.
- Comparisons use the last two completed calendar months within the selected year, not the last two nonzero months. Current/future months are excluded. Percentage change is `(latest - previous) / previous * 100`; a zero denominator produces no percentage.
- Zero counted records do not prove zero actual migration. Unequal reporting coverage and bulk encoding can explain apparent increases. Tagged demo events and demo profiles are excluded from trends, recent events, migration report summaries and analytics checks. The demo records remain stored; no records are deleted by this filtering.
- Household review flags identify at least five resident profiles encoded into one household on one stored calendar date in the selected year. The threshold is an explicit review rule, not a trained anomaly detector. Valid large households, shared placeholder household numbers and source imports can trigger it. The panel shows the ten largest groups plus the total flagged groups.
- Current transferred/moved-out profiles without any dated departure record are counted separately across all years. No movement dates are generated to fill these gaps.

## Rule-based interpretation (No ML)

The migration-trends panel uses PHP calculations and explicit review rules only. It does not call Ollama or any external API. The statistical forecast dashboard uses Weighted Moving Average or a disclosed population baseline and a Cebuano rule-based explanation; see `docs/statistical-migration-forecasting.md` for its sources, formulas and evaluation protocol.

Population reports and Consolidated count active registered residents excluding tagged migration demo profiles. Registered residents living elsewhere remain included; transferred/inactive profiles stay stored but are excluded from the current population count.

## Defense explanation

Ang system mokwenta sa statistics gikan sa database ug mopatin-aw pinaagi sa PHP rules. Ang household alerts para sa staff review, dili automatic proof sa fake records. Lahi ang interpretation sa existing records ug ang WMA forecast sa future migration. Walay machine learning sa bag-ong forecasting ug analytics flow.
