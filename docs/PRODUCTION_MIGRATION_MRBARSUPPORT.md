# MR BAR Production Migration - mrbarsupport.com

Target document root: `/domains/mrbarsupport.com/public_html`

## What moves

1. Upload the complete application package to the target document root.
2. Copy the active runtime database from the test host:
   - Prefer `storage/runtime-data.php` when it exists.
   - Otherwise copy `storage/data.php`.
3. Copy user-generated media directories when present:
   - `storage/employee-media/`
   - `storage/pr-media/`
   - `storage/attendance-evidence/`
   - `storage/customer-web-media/`
   - `storage/portal-media/`
   - `uploads/`
4. Do not copy sessions, temporary POS staging files, or cache files.

## Required permissions

- Directories `storage/` and `uploads/`: `0755` or `0775` depending on the host PHP owner.
- Active database file: `0664` when the host requires group write.
- Application PHP files: `0644`.
- Keep `storage/.htaccess` in place. It blocks direct public access to private runtime data.

## Cutover order

1. Put the test system into a short maintenance window and stop new check-ins/imports.
2. Download a timestamped backup of the active database and media folders.
3. Upload the full application package to the new domain root.
4. Upload the latest active database and media folders last.
5. Point DNS for `mrbarsupport.com` and `www.mrbarsupport.com` to the production host.
6. Enable SSL for both hostnames and redirect HTTP to HTTPS in the hosting panel.
7. Sign in as Super Admin and open `/preflight.php`.
8. Only when a schema update is explicitly required, use `/migrate.php`. v1.48.45 itself requires no schema migration.

## Acceptance checks

- Login, logout, trusted device PIN, and session timeout.
- Active Shop switcher and strict branch isolation.
- Employee Center, schedules, attendance, leave approval, payroll, and no-show penalties.
- Customer portal, reservation, floor plan, public media, and privacy consent.
- Sales Table authenticated flow and signed public QR flow.
- POS bill-detail import, menu-sales import, processing, commission, PR/Sales drinks, save report, export, and Super Admin clear-report confirmations.
- Camera/GPS check-in on a real mobile device over HTTPS.
- PWA install/start URL and icons on the root domain.

## Rollback

Keep the test domain unchanged until production acceptance passes. To roll back, restore the timestamped database/media backup and return DNS to the previous target. Never merge two independently active database files.
