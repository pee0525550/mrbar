MR BAR Update Pack v1.48.70
HR Approval Center and System Reports Theme Contrast Fix

Base version: v1.48.68
Database migration: None
Production data included: No

Upload and overwrite only these five files in the site document root:

  hr-approval-center.php
  system-reports.php
  assets/hr-approval-center-theme-v14869.css
  assets/system-reports-theme-v14870.css
  config/app.php

The config/app.php file only updates the displayed release version and pack
name. Do not upload manifest.json, release-info.json, or other release records.
Do not replace the database, storage, uploads, or any other application files.

After upload, hard-refresh both pages and verify Light and Dark themes:
  /hr-approval-center.php
  /system-reports.php

If a CDN is enabled, purge the two pages and their newly versioned stylesheets.
