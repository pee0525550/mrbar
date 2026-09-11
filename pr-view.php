<?php
// Legacy route kept for old bookmarks / deployed links. MR BAR TIME Preview now lives at staff-preview.php.
$q=$_GET;$target='staff-preview.php'.($q?'?'.http_build_query($q):'');header('Location: '.$target,true,302);exit;
