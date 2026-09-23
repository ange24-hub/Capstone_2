# RBIM interface design system

The municipal, barangay and resident workspaces share one visual language. Existing routes, permissions, data, forms, and document formats remain authoritative.

## Foundation

`resources/css/civic-system.css` contains the shared tokens and component appearance. Its `civic` layer is declared first: important declarations in that layer take precedence over the existing important Tailwind and compatibility rules. Existing styles retain responsibility for page layout. All visual overrides are screen-only so printed reports retain their formatting.

- Identity: navy `#142d4e`, blue primary actions `#2457a7`.
- Surfaces: white cards on light blue `#e8eff8`; subtle panels `#eef4fc` and blue-tinted page headers.
- Text: `#172b45` headings, `#40516a` body, `#5c6d82` supporting copy.
- States: green for successful/completed work, amber for pending work, red for errors or destructive actions. Always retain a text label.
- Typography: local Segoe UI/system fonts; 24–30 px page headings, 17 px section headings, 14 px body and controls, 12–13 px supporting text.
- Spacing: 4, 8, 12, 16, 20, 24, 32 px. Cards use 12 px corners; controls use 8 px corners. Shadows are subtle and do not indicate interactivity.

## Shared patterns

- Keep role-specific navigation, active-page indication, the municipality seal, office context, profile access, and sign-out visible in the established shell.
- Page headers use a soft blue gradient and blue edge accent, a short description, and existing task actions. Avoid promotional banners in operational screens.
- Metric cards use a consistent label/value/detail hierarchy and tabular numbers. Colors communicate state, not invented categories.
- Use primary buttons for the main action; outlined buttons for alternatives; red buttons for destructive actions. Preserve confirmation dialogs.
- Forms keep existing labels, field names, validation, and submitted values. Controls have at least 42 px height; mobile text fields use 16 px text.
- Tables retain all columns and actions, with horizontal scrolling inside their existing wrappers. Document previews, source RBI sheets and map controls are excluded from general data styling.
- Empty, success, validation and pending states use the same surfaces and spacing across modules.
- Use `<x-status-badge :status="$record->status">` with the record's existing label for reusable textual state indicators. It changes presentation only.
- Keyboard focus has a visible 3 px outline. Navigation targets are at least 44 px high. Reduced-motion preferences disable animation.

## Maintenance

Reuse existing Blade components and shared semantic classes. Extend the shared tokens and component rules instead of adding page-specific color overrides. Avoid styling raw document tables or introducing UI that implies unavailable functionality. Verify changes at desktop and mobile sizes for each role, including long tables and form validation.
