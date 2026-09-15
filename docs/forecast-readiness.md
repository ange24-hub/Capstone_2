# Forecast readiness

Staff can open Reports & insights → Forecast Readiness, or ask the local assistant to open forecast readiness.

The page audits the 24 completed calendar months immediately before the current month. It reads movement dates, types and barangay IDs, and submitted RBI reporting-month metadata. It does not read names, purposes, signature images or form rows, modify records, send mail, or call an external AI service.

Secretaries are restricted to their assigned barangay; their RBI submission counts use their own forms. Municipal users can inspect available municipal records or filter by barangay. Multiple forms in a month count once in the coverage table and individually in monthly form totals. Current/future reporting months and future submission timestamps are excluded. Migration counts are events, not unique residents.

No-record months are shown as unverified, never as confirmed zero movement. An RBI submission does not prove complete migration reporting. The 24-month display window is an audit window, not a model training sufficiency criterion. All forecasting remains disabled: no completeness source, comparable population time series or evaluated forecasting model exists yet.

Next dependencies: staff-approved monthly completeness tracking; comparable historical population snapshots; local model evaluation on chronological holdout data against a baseline; uncertainty and staff review. These remain unfinished. No ML accuracy or population growth claim is made by this page.

The municipal dashboard's existing entry-balance calculation is now labelled as a recorded average rather than a next-report prediction. Its underlying calculation and stored data are unchanged.
