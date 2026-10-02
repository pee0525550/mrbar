# Report audit v1.49.20

## Changes
- Separate read-only report projection and tests; original records and payout calculation rules are unchanged.
- Correct employee/user/PR namespaces, booking deposit/date, attendance review state and correction request date.
- Missing amounts display a dash, not an invented zero. Daily Close displays jobs, not money.
- Separate verified and pending deposits; exclude cancelled reservations.
- Add coverage by record type, undated counts, type filters, period shortcuts and 20/50/100-row pages.
- Preserve filters when loading more, protect CSV formula cells and redact secret fields from audit details.
- Include LINE delivery status without recipient identities or message payloads.
- Print/PDF view includes up to 2,000 filtered records. CSV includes all filtered records.
- Separate report viewing, export, audit and management permission checks. Employee summaries exclude void payout rounds.
- Add wage-summary CSV alongside attendance-detail CSV. Reject invalid date ranges.
- Flag monthly payroll spanning multiple months and block its summary export until a single month is selected.

## Remaining boundaries
- Operational reports are not a certified accounting ledger or bank reconciliation.
- POS and payout batches are counted in full when their period overlaps the selected dates, not prorated daily.
- Saved payout rounds do not prove bank payment. Payroll totals remain estimates under existing rules.
- POS bill matching lists all bill batches; payout filters below it do not filter that separate list.
- Deposit reporting uses booking dates, not bank settlement dates.
- Undated data requires the all-time view. Coverage reflects recorded data, not proof that every business event was entered.
- Cross-month monthly wage calculation has not been redesigned; the export guard prevents a misleading summary.
- QA uses synthetic local data, not production reconciliation. No database migrations or production data are in the pack.

## Verification
Pure report tests cover source fields, identity collisions, units, missing amounts, deposits, audit redaction, CSV formulas, date filtering and void rounds. Isolated browser regression covers pagination, exports, print, permissions and mobile layouts alongside existing booking/LINE/PIN flows.
