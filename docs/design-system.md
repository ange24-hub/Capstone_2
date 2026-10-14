# RBIM interface design system

The municipal, barangay and resident workspaces share one visual language. Existing routes, permissions, data, forms, and document formats remain authoritative.

## Foundation

`resources/css/civic-system.css` contains the shared tokens and basic components. `resources/css/civic-workflows.css` applies those tokens to the application shell and individual workflows. The layout declares the cascade order before loading any stylesheet: `print-overrides`, `civic`, `workspace-headers`, `theme`, `base`, `components`, `utilities`. Important screen declarations in `civic` take precedence over legacy theme rules; print overrides remain first. Existing feature styles retain responsibility for layout and interaction. All visual overrides are screen-only.

- Identity: navy `#142d4e`, blue primary actions `#2457a7`.
- Surfaces: white cards and page headers on neutral `#f4f6f8`; subtle panels `#f8fafc`.
- Text: `#172b45` headings, `#40516a` body, `#5c6d82` supporting copy.
- States: green for successful/completed work, amber for pending work, red for errors or destructive actions. Always retain a text label.
- Typography: local Segoe UI/system fonts; 24–28 px page headings, 17 px section headings, 14 px body and controls, 12–13 px supporting text.
- Spacing: 4, 8, 12, 16, 20, 24, 32 px. Cards and controls use 8 px corners. Shadows are subtle and do not indicate interactivity.

## Shared patterns

- Keep role-specific navigation, active-page indication, the municipality seal, office context, profile access, and sign-out visible in the established shell.
- Page headers use a plain white surface, a short description, and existing task actions. Breadcrumbs appear on pages below the role dashboards. Avoid promotional banners in operational screens.
- Metric cards use a consistent label/value/detail hierarchy and tabular numbers. Colors communicate state, not invented categories.
- Use primary buttons for the main action; outlined buttons for alternatives; red buttons for destructive actions. Preserve confirmation dialogs.
- Forms keep existing labels, field names, validation, and submitted values. Controls have at least 42 px height; mobile text fields use 16 px text.
- Tables retain all columns and actions, with horizontal scrolling inside their existing wrappers. Document previews, source RBI sheets and map controls are excluded from general data styling.
- Empty, success, validation and pending states use the same surfaces and spacing across modules.
- Use `<x-status-badge :status="$record->status">` with the record's existing label. Pending/review states use amber, approved/completed/paid states green, processing/ready/submitted states blue, and rejected/cancelled states red. Document and payment states have separate labels.
- Use `<x-summary-card>` for numerical indicators, `<x-empty-state>` for empty content, and `<x-form-section>` for related form fields. These components render existing data and do not query the database.
- Keyboard focus has a visible 3 px outline. Navigation targets are at least 44 px high. Reduced-motion preferences disable animation.

## Maintenance

Reuse existing Blade components and shared semantic classes. Extend the shared tokens and component rules instead of adding page-specific color overrides. Avoid styling raw document tables or introducing UI that implies unavailable functionality. Verify changes at desktop and mobile sizes for each role, including long tables and form validation.

Legacy themes remain available for their feature layouts. Their scoped color variables inherit the civic palette, and the older `--to-*` variables map to the shared tokens. This prevents role-specific styles from silently changing the canvas, controls, or card colors.

`public/js/workspace-ui.js` adds accessible names to implicit form labels, marks genuinely required fields, and makes wide table wrappers keyboard accessible. It preserves field names, submitted values, request methods, CSRF tokens, and validation. The existing sidebar script owns collapse and the mobile drawer. The assistant owns its conversation controls, mobile dialog focus, network retry, session/permission messages, and reset confirmation.

The offline survey retains its self-contained styling and IndexedDB behavior so it works without loading online CSS. Saved records are explicitly described as local until synchronization succeeds.

Official RBI previews and PDF/Excel/Word exports keep their document formatting, Times New Roman 11 point settings (with the existing PDF Times fallback), white backgrounds, plain text location headers, and black table borders. The screen refresh does not modify report generators, document partials, routes, controllers, models, database logic, or permissions.
