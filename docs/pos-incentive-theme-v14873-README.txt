MR BAR Update Pack v1.48.73
POS Incentive Dark Theme Contrast + Inbox Empty State

Base version: v1.48.72
Database migration: None
Production data included: No

Upload and overwrite only these three files in the site document root:

  pos-incentive.php
  assets/pos-incentive-theme-v14873.css
  config/app.php

This patch assumes v1.48.72 is installed. It fixes low contrast in the POS
workflow navigation in Dark Mode and clarifies that an empty POS Inbox is
scoped to the currently selected branch, with a direct Import POS link.
Inbox records remain branch-isolated; no cross-branch records are exposed.

Do not upload manifest.json, release-info.json, this README, UPLOAD_PATH.txt,
or checksums-sha256.txt. Do not replace the database, storage, uploads, or
any other application files.

After upload, hard-refresh /pos-incentive.php and check the workflow steps in
Light and Dark themes. If the inbox is empty, confirm that the active branch
matches the branch used to upload the file, then use Import POS on that branch.
