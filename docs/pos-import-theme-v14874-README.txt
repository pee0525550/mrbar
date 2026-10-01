MR BAR Update Pack v1.48.74
POS Import Dark Theme Contrast + Calendar Date Validation

Base version: v1.48.73
Database migration: None
Production data included: No

Upload and overwrite only these five files in the site document root:

  pos-incentive.php
  pos-incentive-import.php
  app/pos-incentive.php
  assets/pos-incentive-theme-v14874.css
  config/app.php

This patch fixes Dark Mode contrast on the POS Import page and applies the
same workflow navigation theme to the Import and Process pages. It also checks
that submitted dates are real calendar dates before storing an uploaded file.
Light Mode is unchanged.

Do not upload manifest.json, release-info.json, this README, UPLOAD_PATH.txt,
or checksums-sha256.txt. Do not replace the database, storage, uploads, or
any other application files.

After upload, hard-refresh /pos-incentive-import.php and verify both Import
cards, date inputs, file chooser, counters, workflow navigation, uploaded-file
lists, and the browser's Dark/Light themes. For validation, submit a date such
as 2026-02-30 and confirm the upload is rejected without storing a file.
