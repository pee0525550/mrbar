# Dashboard v1.49.21

- Sales area reads active imported POS batches. Bill imports (commission workflow) and menu imports (drink workflow) are separate selectable sources.
- Only one selected batch is totaled. No combined bill/menu total and no sum of overlapping snapshots.
- Bill metrics: net sales, bill count, average bill and source-date chart. Menu metrics: net sales, row count, quantity and ranked menu totals.
- Product summary imports represent a period total; they never become a fabricated daily chart. Source dates in other files may use importer end-date fallback.
- Graphs display up to the latest 31 dates containing records. Negative amounts remain in totals and have orange bars.
- Undated rows stay in totals but not charts. Difference from batch total produces a warning. Missing rows produce an empty state, not invented zero.
- Show selected period, filename and import timestamp. This is not a live POS connection, profit, payment-channel analysis or proof of money received.
- Sales visibility requires reports.view or employees.manage (or super admin), in addition to dashboard.view.
- View switches isolate sales/operations. Chart points support mouse, keyboard and touch. Number animation respects reduced-motion preferences.
- Existing operations sections remain visible; current operational counts are independent of the selected POS batch.
- A populated report fixture also exposed a narrow POS filter grid; its columns now shrink and wrap responsively.

Unit tests cover source separation, void/inactive exclusions, summary handling, menu grouping, unknown batches, negative amounts, undated data and reconciliation. Browser regression tests sourced totals, view switching, chart interaction, screen rendering and responsive layouts.
