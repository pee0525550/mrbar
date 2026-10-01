MR BAR Update Pack v1.48.72
System Reports Dashboard + Global Loading + Theme Contrast

Base version: v1.48.68
Database migration: None
Production data included: No

Upload and overwrite only these ten files in the site document root:

  app/bootstrap.php
  assets/mr-loading-v14871.css
  assets/mr-loading-v14871.js
  hr-approval-center.php
  assets/hr-approval-center-theme-v14869.css
  system-reports.php
  assets/system-reports-theme-v14870.css
  assets/system-reports-dashboard-v14872.css
  assets/system-reports-dashboard-v14872.js
  config/app.php

This cumulative pack can be installed directly on v1.48.68. It includes the
earlier HR Approval Center and System Reports contrast fixes and global loading
experience. Report data is read from the existing application database; no
schema/data migration is required.

Do not upload manifest.json, release-info.json, this README, or checksums.
Do not replace the database, storage, uploads, or any other application files.

After upload, hard-refresh /system-reports.php and verify:
  - Overview KPIs, module activity, and separate financial source totals
  - Records and Audit Log tabs, filters, sorting, and CSV export
  - Load more appends 20 rows per click and hides when all rows are shown
  - Light and Dark themes
  - HR Approval Center contrast and global page/function loading
