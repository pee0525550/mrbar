# Audit Follow-Up v1.49.17

## Scope
Continues the previous P1 storage repair without a database schema migration.
This release covers authentication state, replay-safe customer bookings,
deposit enforcement, notification persistence and reservation responsiveness.
It does not claim that every audit item across the project is resolved.

## Verification
- PHP regression suite: 28 scripts, including auth and reservation request tests.
- JavaScript regression suite: 5 scripts, including notification persistence.
- Isolated HTTP/browser tests using synthetic data and no LINE/API credentials:
  duplicate customer POST and receipt refresh create one record; pending deposits
  cannot be seated through Reservations or Operations; missing LINE CSRF rejects;
  acknowledgement persists after reload; PIN failure counters persist to disk and
  correct PIN cannot bypass a lock; entering six valid digits submits automatically.
- Reservation viewport checks: 320, 390, 768, 1200 and 1440 pixels, with screenshots,
  no horizontal overflow or overlapping status/seat forms.
- Production data is excluded from tests, screenshots and deploy packages.

## Remaining Boundaries
- Real LINE OA and third-party slip-provider delivery must be checked with the
  deployment's private credentials; local tests do not send real messages.
- Direct LINE dispatch is still synchronous after the database transaction.
  A durable delivery queue and retry worker are separate follow-up work.
- Hidden-tab browser polling is best effort, not closed-app push delivery.
- Other admin/Time Staff pages have not all received browser-level visual QA
  in this patch. Existing automated regression coverage was rerun.

## Deployment
Use the standard outer `_deploy_pack` directory, not a nested source-tree folder.
Install on v1.49.16 and upload all 18 application files from `release-info.json`.
Verify the live version and clear OPcache if the hosting environment requires it.
