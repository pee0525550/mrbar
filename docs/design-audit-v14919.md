# Design audit and mobile usability, v1.49.19

## Scope

This is a usability repair release, not a complete visual rebrand. The browser
audit covers 23 routes at 390 and 1440 pixels using isolated synthetic data:
dashboard, operations/tools, reservations/settings, employees, workforce,
payroll, HR overview/detail, embedded leave/exceptions, customers/customer web,
reports, account administration, permissions, settings, LINE settings, personal
account and the three employee Time screens. Targeted checks also cover Time
and HR at 320 and 768 pixels, plus the HR light theme.

Screenshots and measurements are saved outside the source/deploy tree in
`D:/Test/TrackingPR/_qa_v14919`. No production data or real LINE delivery is used.

## Fixed

- Mobile dashboard and Operations headers wrap without off-screen actions.
- Hidden mobile navigation is inert; opening moves focus into the menu,
  Tab remains inside, Escape/close restores focus, and page scrolling is locked.
- The mobile menu close control has a usable touch area and the launcher
  accounts for the device bottom safe area.
- Staff calendar now supplies its own fixed four-column navigation geometry,
  matching Time/income instead of relying on an absent stylesheet.
- The calendar Today action stays readable on narrow phones; form focus is clear.
- HR embedded frame height no longer overrides the automatic content measurement.
- Embedded HR pages do not create duplicate theme switches and follow storage
  changes from their parent page.
- HR date labels remain readable in dark mode. Mobile sections no longer reserve
  a large empty minimum height; their corners and heading spacing are restrained.
- Reports no longer pass associative array keys as named arguments into max().
  The browser audit found this fatal error when rendering module chart data.
- Changed cached asset URLs are bumped; schema stays v28 with no migration.

## Remaining design work

1. CSS is layered across many versioned files; shared typography, spacing, button
   sizing and dark/light surface tokens still need a gradual consolidation.
   Replacing all styles at once would risk editors, import tools and calendars.
2. Persistent notifications intentionally remain visible until acknowledgement.
   Multiple notifications can cover content on small screens. A dedicated compact
   notification inbox should be designed without silently acknowledging messages.
3. Dense POS, commission/import, floor-map editor, PR-only workflows and customer
   marketing pages need a separate populated-state visual pass. They are not
   certified by this 23-route administrator fixture.
4. Empty synthetic states do not establish that long lists, large monetary values,
   every role, modal, validation error or permission combination look correct.
5. Real iOS keyboard/safe-area behavior, granted GPS/camera and actual map tiles
   need device testing. Headless screenshots here use the GPS-unavailable state.

## Verification

The web regression checks request/booking safety, notification acknowledgement,
PIN lockout, desktop/mobile layout, menu focus restoration, embedded theme and
frame height. PHP and JS regression suites must also pass before packaging.
The deploy pack includes changed application files only, not fixtures or screenshots.
