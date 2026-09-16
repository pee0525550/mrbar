# Sales Table QR v1.45.0

## Usage
1. Install the update pack and open Tools & Reports.
2. Print new Sales table QR codes. Existing customer Check-in QR codes still point to the old customer flow.
3. Scan the correct table QR. Sign in using the recorder's own account; the QR destination is retained through login.
4. Select the Sales Employee Card and confirm opening. The Sales owner and the recording user are stored separately.
5. Scan again when the round ends. Enter one receipt number per line and confirm closing.
6. Use the history link to inspect rounds. Authorized managers can correct the owner or receipt numbers with a mandatory reason.

## Permissions
- sales_sessions.record: enabled by default for admin, staff, Sales and PR roles; explicit permission denies are respected.
- sales_sessions.manage: enabled by default for admin; grant to designated managers through Role & Permission.
- User must be active and allowed to access the QR branch. Sales owners must be active Sales employees in that branch.

## Data boundaries
- Records are branch-scoped sales_table_sessions; existing guest check-ins and POS drink commissions are not changed.
- This is an attribution round, not a Night Ops occupancy operation. Staff continue to manage seating/PR status through Night Ops.
- Sales drink-menu amounts are not personal customer sales.
- Receipt numbers are stored as text, including leading zeroes, and must be unique within a branch (case-insensitive).
- Up to 30 receipts per round. Multi-bill closure is supported.
- POS receipt matching/import is not implemented in this update. matched_net_sales remains null and match_status pending.
- If the POS reuses receipt numbers by business day or register, extend the receipt key before production rollout.
- New rounds for a table are blocked until the existing attribution round is closed.
- A scan only opens the page. State changes require authenticated, CSRF-protected POST.
- Audit entries retain actor, timestamp, before/after, and correction reasons.

## Verification
Run php scripts/test-sales-table.php and php scripts/test-sales-table-render.php.
Before broad rollout, scan a new QR using a staff/PR account, open for a different Sales employee, close from another account, and verify the receipt in history.
