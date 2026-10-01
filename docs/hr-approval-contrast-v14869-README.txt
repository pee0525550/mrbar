MR BAR Update Pack v1.48.69
HR Approval Center Contrast Fix

Base version: v1.48.68
Database migration: None
Production data included: No

Upload these two files to the matching paths in the site document root,
overwriting the existing page file:

  hr-approval-center.php
  assets/hr-approval-center-theme-v14869.css

Do not upload the config, manifest, release-info, or changelog files in this
incremental hotfix pack. They are release records only. Do not replace the
database, storage, uploads, or other application files.

After upload, open /hr-approval-center.php and hard-refresh the browser.
Verify the page in both Light and Dark themes. If a CDN is enabled, purge the
page and the new stylesheet URL ending in ?v=14869.
