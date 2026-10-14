# Frontend design system

The shared Laravel layout loads Tailwind v4 through Vite. The current civic design uses navy `#142d4e`, blue actions `#2457a7`, white cards, and a neutral `#f4f6f8` canvas. See [the shared design system](design-system.md) for tokens, component usage, cascade order, workflow behavior, and document exclusions.

## Screen conventions

- Use `rounded-xl border border-slate-200 bg-white shadow-sm` for cards, with `p-5 sm:p-6` spacing.
- Use navy primary actions, outlined secondary actions and red destructive actions. Keep labels explicit.
- Use visible focus outlines, labelled controls, readable contrast and touch-friendly buttons. The mobile navigation traps focus, closes with Escape, and makes the covered workspace inert.
- Use one-column forms on phones and two-column forms where space permits. Wide registry tables scroll within their table containers.
- Use the same shared templates for all barangays. Preserve role-specific navigation and every existing form field and Blade expression.

## Gradual migration

This pass updates the shared shell and 27 Blade views across dashboards, registry, forms, profile, reports, directory, approvals, registration and public services. Existing class hooks remain where legacy CSS or JavaScript relies on them. Shared compatibility rules in `resources/css/app.css` use Tailwind utilities while these hooks are gradually retired.

Tailwind utilities currently use the important modifier because legacy stylesheets include high-specificity and important rules. Do not remove the legacy stylesheets wholesale: they still supply document layouts and feature-specific UI behavior. Screen compatibility rules are scoped to `.municipal-ui` and screen media.

Standalone PDF Blade views and the official RBI document partials retain their document formatting and do not load Tailwind.

Build with `npm run build` (`npm.cmd run build` on Windows where PowerShell script execution is disabled). No controllers, models, database queries, migrations or routes were changed during this frontend pass.
