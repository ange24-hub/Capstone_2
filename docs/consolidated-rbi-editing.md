# Edit consolidated residents through RBI Forms

Barangay consolidated/source-active tables show an Edit action and disable their inline input controls. Edit opens the existing RBI Forms route with `edit_resident`, in registered-resident edit mode. This works for imported residents without requiring or creating a monthly report.

The editor saves the same Inhabitant record, then returns to Consolidated filtered by its surname. Its fields include the RBI demographics, dropdowns, occupation text, family/individual numbers, ethnicity and contact number. Household assignment, migration/residence status and source metadata are preserved. This is a current registered-resident correction flow; it does not rewrite historical monthly report snapshots or create report/registry duplicates.

Both opening and saving require an approved barangay account assigned to the resident's barangay and an active resident. Save validates the submitted fields, locks the record, rechecks access/status and checks the record timestamp against the form version to detect stale forms. Only validated resident fields are updated. Other records are untouched.

RbiResidentEditTest covers the standard and Biasong table layouts, opening imported records, saving and redisplaying corrections, retaining custom dropdown values and source remarks, preventing unrelated household/status changes, duplicate prevention, invalid dates, stale versions, cross-barangay access and resident-role restrictions.
