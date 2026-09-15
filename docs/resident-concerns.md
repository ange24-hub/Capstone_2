# Smart Resident Concern Management

## Access and workflow

- Approved residents with an assigned barangay open **My Concerns**, submit a title, category, description, optional location and one optional PDF/JPG/PNG (5 MB maximum), then track the reference and post follow-up information.
- Approved secretaries open **Resident Concerns** for their own barangay. They review, correct the category, change status and record an update visible to the resident. An optional separate internal note is visible only to authorized barangay staff. Every human action is retained in the timeline with its author, category/status when changed and timestamp.
- Municipal accounts open **Concern Insights** for aggregate category/status counts over the last 30, 90 or 365 calendar days, optionally filtered by barangay. They cannot open individual concerns or download attachments. Summary dates refer to submission dates and counts use current status, not status at submission or a time-to-resolution measure. Closed and resolved are distinct.
- Both this module and Forecast Readiness remain available. Email remains deferred.

Statuses: submitted → under review → in progress/resolved/closed. Staff can close a submitted item with a reason in the required public message. Resolved/closed items can be reopened to under review. Same-status updates are permitted. Residents cannot change status/category or edit another resident's submission; follow-ups preserve the original description. Closed items reject resident follow-ups until staff reopen them. Version checks prevent older staff edits from overwriting newer activity.

This is a service-concern inbox, not an emergency-dispatch or formal adjudication system. Categories do not determine urgency, fault, eligibility or resident risk. Counts represent submitted concerns, not verified incident rates. No official response deadlines or intervention rules are claimed.

## Local machine learning assistance

Staff choose **Suggest category** to send only the concern's title and description to the configured local Ollama model. Account email, resident ID, location field, attachments and internal notes are not included. Free text can itself contain personal details, so inference is restricted to a literal `http://127.0.0.1:PORT` endpoint, with redirects/proxies disabled. The model must be present in `/api/tags` and must not be marked as cloud/remote. There is no external fallback, email or prompt logging.

The existing `config/local_ai.php` settings are reused. The pretrained local language model performs classification; **no concern-specific model has been trained or evaluated**. The output must be JSON containing one category from the application's allowlist. Only the suggestion, model name and time are saved; the actual category and status remain unchanged. Staff review and explicitly select a category before saving an action. Failure or malformed output leaves manual handling available. No numeric confidence or accuracy claim is displayed.

Before production reliance, evaluate English/Cebuano examples and ambiguous cases with staff, record classification errors and retain human review. Valid JSON is not evidence that the classification is correct.

## Storage and deployment

Only new `resident_concerns` and `concern_updates` tables are added by `2026_09_13_100000_create_resident_concerns_tables.php`. Existing RBI, inhabitants, households, migration and document-request records are not modified. Keep the migration with the feature when deploying.

Uploads live on the private local disk under `concern-attachments/`, outside `storage/app/public`. A random stored filename and authenticated download route are used; do not expose this directory through a web-server alias. Downloads require current barangay/owner permission and send private no-store and nosniff headers. The resident ID and barangay are assigned server-side, not accepted from the submitted form. Forms use CSRF and mutation endpoints are role-checked. Submission, replies and AI calls have rate limits.

## Verification

`ResidentConcernTest` covers forged scope/status fields, private file access, role/owner isolation, restricted municipal summaries, public/internal notes, transition rules, version conflicts, follow-ups, reopening, invalid submissions, date filters and human confirmation of AI output. `LocalConcernClassifierTest` verifies external/cloud rejection and unavailable model fallback. Assistant catalog checks cover the new concern shortcut.
