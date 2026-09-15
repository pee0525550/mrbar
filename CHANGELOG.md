# v1.39.0 — POS Upload Inbox

- Added a server-backed POS data inbox so the IT team can upload XLSX/CSV files with a title, notes and calculation period without processing them immediately.
- Added clear Uploaded, Processing, Completed and Failed states so the payment team can select and process work later.
- Preserves uploaded source files under `storage/pos-upload-inbox` and records uploader/processor audit events.
- Duplicate protection now blocks only the same completed file in the same exact date range instead of all historical imports.
- Added recent-processing protection plus safe retry for abandoned processing jobs after 15 minutes.
- Persists the inbox as branch-scoped data. Schema remains v28; no migration is required.

# v1.38.0 — Flexible POS Period Flow

- Replaced the subtle month bar with a prominent calculation-period selector.
- Added monthly, weekly, 22-to-22 and custom date-range modes, including calculate-through-today.
- Added cross-month import support for accounting cycles such as 22 July through 22 August.
- Removed the visible source/mapping confirmation block from the normal workflow; upload now auto-detects, stores and opens Sort.
- Connected the existing “Calculate selected total” button directly to Sales commission when Group > Sales is selected.
- Filters product and Sales commission data by the selected start/end dates.
- Stores Sales commission conditions separately for each exact date range.
- Schema remains v28; no migration is required.

# v1.37.0 — Sales Commission Payout

- Added a Sales-only commission action from the imported product Group sorter.
- Consolidates D/M product rows into one Sales name and uses total sold units as monthly bills/tables.
- Added monthly conditions for minimum units, per-unit commission, bonus target and target bonus.
- Added clear source, payout and overall summary tables for accounting.
- Added one-click tab-delimited copy for Excel, Google Sheets and payment workflows.
- Stores Sales commission conditions per month with an audit entry.
- Schema remains v28; no migration is required.

# v1.36.0 — POS Product Group & Category Sort

- Replaced the employee/role filter from v1.35.0 with product-level sorting based on the imported POS columns.
- Added a two-stage selector: choose Group or Category, then choose an actual value such as Sales.
- Added instant filtered totals for row count, quantity, net sales and average sales.
- Added searchable full-file preview before commit and a persistent imported-product explorer after commit.
- Preserved the monthly commission rules and employee result calculations as a separate next step.
- Schema remains v28; no migration is required.

# v1.35.1 — POS Incentive Deploy Structure Fix

- Reissued the POS Incentive update with preserved `assets/` and `config/` directory paths.
- Bumped the Wizard stylesheet URL to v1351 to bypass cached missing/old CSS responses.
- No data or calculation changes; Schema remains v28.

# v1.35.0 — POS Incentive Guided Flow

- Reorganized POS Incentive into a four-step workflow: Import, Group & Sort, Calculate Commission, and Review & Finalize.
- Added Excel-style grouping by Sales, PR and Team with optional employee filtering.
- Added on-demand calculation summary for selected groups using the existing monthly commission rules.
- Improved KPI, workflow, filter and calculation-card contrast for faster reading in operational use.
- Existing imported POS batches, aliases, rules and monthly closings are preserved; Schema remains v28.

# v1.34.0 — Portal Favicon Upload

- Added direct Favicon upload, preview, replacement and removal to Config Web Portal.
- Portal Favicon files are validated as JPG, PNG or WEBP and stored securely in server-side Portal media storage.
- The public Portal now emits the configured browser tab icon automatically.
- Schema remains v28; no migration is required.

# v1.33.2 — Portal Logo Inline Lock

- Locked the Portal Logo directly on the image element to a 64 × 64 px square, independent of stylesheet loading or cache.
- Prevented intrinsic image dimensions and global CSS from expanding the Portal Header.
- Schema remains v28; no migration is required.

# v1.33.1 — Portal Logo Size Hotfix

- Locked the Portal header Logo to a compact 76 × 58 px desktop box so global image styles cannot stretch the Header.
- Added proportional tablet/mobile limits while keeping the transparent, frameless presentation.
- Schema remains v28; no migration is required.

# v1.33.0 — Portal Brand Experience

- Added direct Portal Logo upload to server storage from Config Web Portal with secure image validation and public media delivery.
- Portal header now renders the uploaded transparent Logo at a larger size without a surrounding frame, with a graceful symbol fallback.
- Strengthened the Portal hero with a vivid RGB border, layered lighting, enhanced count badge and responsive light/dark presentation.
- Schema remains v28; no migration is required.

# v1.32.1 — Sales Photo Branch URL Fix

- Fixed broken Sales profile images in the customer reservation selector opened from canonical `/shop/{slug}/` pages.
- Sales photo URLs now use the installation-root endpoint and carry `public_branch`, so the media endpoint loads the Employee from the correct branch.
- Added regression checks for installation subdirectory and Branch context; Schema remains v28 with no migration required.

# v1.32.0 — Unified People & Access

- Established Employee Master as the single person record per branch; a login account is now an optional one-to-one extension.
- Separated operational Position from User Role: Position drives PR/Sales/customer/POS behavior, while Role and overrides control system access only.
- Added Schema v28 repair for legacy Sales accounts and missing PR operational profiles without duplicating people across branches.
- Added branch-aware account health counters for unlinked accounts, employees without login, incompatible roles and inactive logins.
- Limited account and permission-user directories to the active branch while retaining global authentication and platform Role templates.
- Blocked incompatible Role/Super Admin changes for linked PR/Sales employees and added per-branch account linkage metadata.
- Migration is automatic on first request; back up the production `storage/` folder before deployment.

# v1.31.0 — Customer CRM / Member Foundation

- Added branch-scoped Customer CRM storage and Schema v27 migration.
- Existing and new Reservations are linked to customer records by normalized phone number without changing confirmed table assignment behavior.
- Added the Customer CRM administration page with search, VIP, tier, birthday, tags, notes, marketing consent and preferred Sales/PR.
- Added visit summaries for Booking count, seated visits, last visit, latest Sales and latest PR.
- Added separate `customers.view` and `customers.manage` permissions plus audit events.
- Added team documentation and the next-step contract for LINE Login, OTP and POS reconciliation.
- Migration is automatic on first request; back up the production `storage/` folder before deployment.

# v1.30.13 — Customer Branch Logo

- Connected the customer shop Header and Footer to the Logo saved for the selected Branch.
- Resolved uploaded `branch-media.php` paths against the installation root so canonical `/shop/{slug}/` URLs do not request media from the wrong nested path.
- Added responsive contain-fit Logo presentation with a safe animated-star fallback when a configured image is missing or cannot load.
- Schema remains v26; no migration is required.

# v1.30.12 — Reproducible Full Source Baseline

- Corrected PowerShell argument handling in the Full Source ZIP builder so Git receives the archive prefix and output path atomically.
- Revalidated the complete Source, generated the reproducible archive and retained v1.30.11 as immutable history.
- Application functions and schema remain unchanged at v26; no migration is required.

# v1.30.11 — Full Source Team Collaboration Baseline

- Published a complete, safe Source baseline for team development; this is not a partial deployment pack.
- Added GitHub Actions validation for PHP syntax, JavaScript syntax and forbidden runtime/private files.
- Added team Branch assignments, project structure documentation and a repeatable release checklist.
- Added a PowerShell builder that creates a reproducible Full Source ZIP directly from the committed Git tree.
- Prepared CRM/Member, OTP authentication, POS reconciliation and Booking Operations work streams from `develop`.
- Application functions and schema remain unchanged at v26; no migration is required.

# v1.30.10 — Fixed Compact Logo Preview

- Corrected the v1.30.9 Logo preview regression where inherited aspect and minimum-height rules could still expand the preview into the text column.
- Set an explicit 76 × 62 px Logo preview with a dedicated 82 px grid track.
- Forced upload controls and URL fields to remain inside their own flexible column without overlap.
- Portal Cover preview remains unchanged; schema remains v26 with no migration.

# v1.30.9 — Compact Branch Logo Preview

- Reduced the Branch Manager Logo preview from about 160px to 118px wide on desktop.
- Returned the freed horizontal space to upload instructions, saved media URL and helper text.
- Kept the Portal Cover preview unchanged and added a compact mobile Logo preview.
- Schema remains v26 with no migration.

# v1.30.8 — Wider Featured Portal Image

- Reduced the desktop Featured Shop detail column by approximately 48 pixels at the current Portal width.
- Returned the saved width directly to the branch cover image for a larger visual presentation.
- Tightened detail-panel padding and responsively scaled long shop titles so the narrower column remains readable.
- Tablet and mobile stacked layouts are unchanged; schema remains v26 with no migration.

# v1.30.7 — Adaptive Multi-Mood Portal Themes

- Corrected Portal text colours that became unreadable when the shared Light theme recoloured headings over dark surfaces.
- Rebuilt Light mode with pearl-white glass panels, dark readable typography and vivid cyan, violet and pink ambience.
- Brightened Dark mode from near-black to luminous navy, violet and cyan while preserving the nightlife identity.
- Added rotating per-branch colour personalities so shop cards can represent different moods instead of sharing one dark palette.
- Increased card surface contrast, live badges, footer actions and closed-shop readability in both themes.
- Changes are isolated to the public Portal; schema remains v26 with no migration.

# v1.30.6 — Readable Multi-Branch Admin Themes

- Rebuilt Branch Manager and Config Web Portal surfaces for correct light and dark theme contrast.
- Light mode now uses bright cards and form fields with dark readable text instead of dark-on-dark content.
- Increased page titles, section headings, labels, helper text, branch details, action buttons and live-list typography.
- Enlarged form controls, switch options, save actions and direct image-upload controls for easier use at 100% browser scale.
- Added responsive spacing and single-column KPI behavior for tablet and mobile screens.
- Changes are isolated to the two multi-branch administration pages; schema remains v26 with no migration.

# v1.30.5 — Collision-Safe Sidebar Footer

- Rebuilt the desktop admin sidebar as a fixed-height flex layout with separate brand, scrollable navigation and footer zones.
- Reserved dedicated bottom space between the `SYSTEM READY` version card and the fixed `ACTIVE SHOP` switcher.
- Navigation now scrolls independently when the viewport is short or multiple menu groups are expanded.
- Added compact-height tuning and a collapsed-sidebar version of the branch switcher.
- The mobile drawer remains scrollable and temporarily clears the floating branch switcher while the drawer is open.
- Scope is limited to shared admin sidebar layout; schema remains v26 with no migration.

# v1.30.4 — Living RGB Portal Showcase

- Rebuilt Portal shop cards as large visual showcases with a full-width featured branch and spacious two-column standard cards.
- Added animated RGB borders, ambient glow, light sweeps, live-status pulses and gently floating brand artwork.
- Added scroll reveal and subtle pointer tilt interactions on supported desktop devices.
- Added responsive ambient particles and a pointer-reactive background spotlight to make the Portal feel alive.
- Brightened the Portal background, glass panels, typography and CTA contrast while retaining the nightlife identity.
- Added responsive layouts for desktop, tablet and mobile plus `prefers-reduced-motion` accessibility behavior.
- JavaScript failure remains safe: shop cards are visible by default before motion enhancement activates.
- Schema remains v26; no migration is required and production data remains excluded.

# v1.30.3 — Direct Branch Media Upload

- Added direct Logo and Portal Cover uploads to Branch Manager while retaining optional external URL fields.
- Added instant client-side image previews and clear file name/size feedback before saving.
- Added server-side validation for upload errors, real image content, MIME type, file size and image dimensions.
- Uploaded branch media is stored in `storage/branch-media` and served through a restricted public image endpoint.
- Portal asset paths are now base-path aware, supporting both the current `/it/` installation and the future root-domain deployment.
- Branch directory thumbnails now display the configured Logo or Cover image.
- Schema remains v26; no migration is required and production media is excluded from the update pack.

# v1.30.2 — Responsive Long Shop Header

- Rebalanced the customer header grid so navigation and action buttons always retain their own space.
- Long shop names now scale responsively and truncate with an ellipsis instead of overlapping the navigation.
- The full shop name remains available through the logo link tooltip and accessible label.
- Added dedicated desktop, tablet, mobile, and extra-small breakpoints for future branches with longer names.
- Scope is limited to the public customer header; schema remains v26 with no migration.

# v1.30.1 — Canonical Portal & Priest Data Recovery

- Changed the application root to redirect to the central `/Portal` gateway instead of the legacy `/custumers/` page.
- Direct customer-home access without a selected branch now returns to the central Portal.
- Added canonical public shop URLs such as `/shop/Priest/` while preserving editable slugs and case-insensitive aliases.
- Added automatic schema v26 repair for v1.30.0 installations where legacy Priest data was stored under another branch during the first multi-branch migration.
- Recovery copies missing tables, PR, employees, media, Hero media, floor plans, bookings, attendance, service history and POS incentive data into Priest without deleting the source branch.
- Branch Manager now shows table/PR/media counts and confirms when automatic recovery was applied.
- Added root-domain compatibility for the future `mrbarsupport.com` deployment; shared assets and trusted-device cookies no longer require the `/it/` subfolder.
- Production storage and uploads remain excluded from the update pack.

# v1.30.0 — Multi Branch Foundation

- Added a central customer Portal that lists published shops and shows live table/PR availability per branch.
- Added Super Admin Portal configuration and branch management pages.
- Added the lower-left shop switcher for the back office, with branch-aware access control for each team member.
- Added stable internal branch IDs and editable public slugs at `/shop/{slug}/`; old slugs are retained as aliases and redirect to the current URL.
- Separated operational settings and data by branch, including tables, PR, employees, bookings, attendance, floor plans, customer media, service sessions, and POS incentive data.
- Preserved global users, roles, branch directory, Portal configuration, and audit storage.
- Customer booking, Check-in, status, privacy and floor-plan routes now preserve the selected branch.
- Existing schema v24 data is migrated automatically to schema v25 on first request. A full `storage/` backup is mandatory before deployment.
- Production `storage/`, uploads, and data files are not included in the update pack.

# v1.29.18 — Stable Customer Hero

- Fixed the periodic dark flash during automatic customer Hero transitions.
- Incoming media starts before activation; outgoing media remains visible through the crossfade.
- Outgoing video and YouTube media stop/reset only after the 650 ms fade has completed.
- Pending cleanup is cancelled when a slide is selected again quickly, preventing active media from being reset.
- Scope is limited to the public customer Hero slider; schema remains v24 with no migration.

# v1.29.17 — Flicker-Free Quick Floor

- Replaced the primary Night Operations table update flow with an AJAX Quick Switch; the whole page no longer reloads after a quick status save.
- Manual refresh and 15-second background sync update only live table data, avoiding page flashes and scroll jumps.
- Added large touch controls for Available, Occupied, and Temporarily Blocked table states.
- Added quick PR assignment/change and automatic PR BUSY/release status handling.
- Added Sales owner attribution for each occupied table.
- Added additive `floor_service_sessions` history with table/check-in, PR snapshots, Sales employee snapshots, start/end timestamps, action user, and change/end reason for future POS and commission reconciliation.
- Changing PR or Sales closes the prior service interval before opening a new one, preserving an auditable timeline.
- Added `operations.quick_floor` permission to Admin, Staff, and Sales default roles; custom roles remain opt-in.
- Retained Reservation, Walk-in, Move Table, Complete, and Cancel controls under Advanced tools.
- Moving a table now closes the prior table interval and continues the same service on the destination table; closing/cancelling from Night Operations also closes its service interval.
- Stale open service intervals linked to Check-ins completed elsewhere are reconciled on the next Quick Floor update.
- Server-side permission, CSRF, active-table, duplicate Check-in, and PR double-assignment checks remain authoritative.
- Schema remains v24; no migration and no production data folders are included.

# v1.29.16 — Complete Live Floor Controls

- Added a complete operational control panel when a table is selected.
- Available/requested tables can create a Walk-in Check-in directly.
- Linked tables can be opened or temporarily blocked; customer availability reflects the saved table state.
- Active tables can move guests, complete the current job, or cancel it and safely release the table.
- Existing reservation requests can still be seated directly from the selected table.
- Added permission-aware controls for tables.manage, reservations.seat, operations.move_table, operations.complete, and operations.cancel.
- Added CSRF protection, active Check-in conflict detection, invalid reservation-state protection, inactive-table checks, confirmations, double-submit prevention, and audit events.
- No schema migration; existing data is preserved.
