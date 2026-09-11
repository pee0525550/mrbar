<?php $ticket=trim((string)($_GET['ticket']??''));header('Location:custumers/status.php?ticket='.urlencode($ticket));exit;
