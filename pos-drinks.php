<?php
$drinkMode=(string)($_GET['drink_mode']??$_POST['drink_mode']??'');
if(!in_array($drinkMode,['pr','sales'],true)){
 require __DIR__.'/app/pos-drinks-landing.php';
 return;
}
$salesDrinkType=(string)($_GET['sales_drink_type']??$_POST['sales_drink_type']??'');
if($drinkMode==='sales'&&!in_array($salesDrinkType,['direct','team_pr'],true)){
 require __DIR__.'/app/pos-sales-drinks-landing.php';
 return;
}
$posCore='drinks';
require __DIR__.'/app/pos-core-page.php';
