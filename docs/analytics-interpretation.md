# Analytics interpretation and review alerts

Migration Trends includes a read-only interpretation panel. Approved municipal staff can filter all barangays; barangay secretaries receive only their assigned barangay's observations and household alerts. Residents cannot access this staff page. Forecast access remains municipal-only.

## Calculations and sources

- Monthly counts are the same values used in the existing graph. Arrivals count classified RBI entries by first-save month; departures count migration events by movement date. Their difference is not a verified population change because the date bases differ.
- Comparisons use the last two completed calendar months within the selected year, not the last two nonzero months. Current/future months are excluded. Percentage change is `(latest - previous) / previous * 100`; a zero denominator produces no percentage.
- Zero counted records do not prove zero actual migration. Unequal reporting coverage and bulk encoding can explain apparent increases. Tagged demo events and demo profiles are excluded from trends, recent events, migration report summaries and analytics checks. The demo records remain stored; no records are deleted by this filtering.
- Household review flags identify at least five resident profiles encoded into one household on one stored calendar date in the selected year. The threshold is an explicit review rule, not a trained anomaly detector. Valid large households, shared placeholder household numbers and source imports can trigger it. The panel shows the ten largest groups plus the total flagged groups.
- Current transferred/moved-out profiles without any dated departure record are counted separately across all years. No movement dates are generated to fill these gaps.

## Actual AI contribution

Population reports and the Consolidated list count active registered residents excluding tagged migration demo profiles. Registered residents living elsewhere remain included; transferred and inactive profiles remain stored but are excluded from the current registered population. Report exports and assistant population summaries reuse this counting basis.

When the existing `ASSISTANT_AI_ENABLED` configuration is enabled and its local Ollama model is available, the model chooses one or two observation IDs to prioritize. Every displayed sentence, number and percentage comes from application calculations. Model output is strictly checked against existing IDs; free-form text, unknown IDs and invalid responses are rejected. All observations remain visible regardless of the model's choices.

The model receives aggregate observation text only, without resident names, household numbers, addresses, remarks or document requests. Existing local-only endpoint restrictions remain in place. Responses are cached for five minutes by scope, year, facts and model configuration; changed facts generate a new key. If inference is disabled, unavailable or invalid, the page explicitly displays **Automated statistical summary · rule-based checks**, rather than claiming AI ran.

## Defense explanation

“Ang system mokwenta sa report statistics gikan sa database. Kung available ang local AI, motabang kini pagpili sa verified observations nga angay unahon sa staff. Ang household alerts kay klarong review rules: dili kini automatic proof sa fake record. Ang staff gihapon ang mo-verify sa RBI source. Dili kami mo-infer nga trabaho o clearance ang hinungdan sa migration kung walay verified evidence. Kini nga feature nagpatin-aw sa existing records; lahi kini sa SVR future prediction.”

For a demonstration, select a year and barangay, reconcile counts with the monthly table, explain the percentage formula and the recording-date limitation, then verify one flagged household against its source. Only describe AI as active when the panel actually shows **AI-assisted focus**. Automated tests simulate model availability, invalid output and outages; they are not proof that the production model is running or that its priorities improve staff decisions.
