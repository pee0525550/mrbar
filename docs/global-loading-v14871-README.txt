MR BAR Update Pack v1.48.71
Global Loading Experience + HR/Report theme contrast fixes

Base version: v1.48.68
Database migration: None
Production data included: No

Upload and overwrite only these eight files in the site document root:

  app/bootstrap.php
  assets/mr-loading-v14871.css
  assets/mr-loading-v14871.js
  hr-approval-center.php
  assets/hr-approval-center-theme-v14869.css
  system-reports.php
  assets/system-reports-theme-v14870.css
  config/app.php

The two page files and their theme stylesheets include the earlier contrast
fixes so this pack can be installed directly on v1.48.68. config/app.php updates
the displayed release version and pack name.

Do not upload manifest.json, release-info.json, or this README. Do not replace
the database, storage, uploads, or any other application files.

After upload, hard-refresh and check page loading, one normal form submission,
one slow async/fetch action, and CSV export. Confirm Light/Dark themes still work
on /hr-approval-center.php and /system-reports.php.
