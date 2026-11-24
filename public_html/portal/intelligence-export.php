<?php
declare(strict_types=1);
require __DIR__.'/../api/dashboard-core.php';
opsHeaders();
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET'||!opsSsl())throw new RuntimeException('Unavailable');
    $cfg=opsConfig();if(!dashboardEnabled($cfg)||($cfg['intelligence_dashboard']['csv_enabled']??false)!==true)throw new RuntimeException('Unavailable');
    opsSession();if(!dashboardOwner($cfg,$_SESSION,time()))throw new RuntimeException('Unavailable');
    if(count($_GET)>1||(count($_GET)===1&&!array_key_exists('days',$_GET)))throw new RuntimeException('Unavailable');
    $days=dashboardDays($_GET['days']??'7');$_SESSION['last_seen']=time();session_write_close();
    $db=opsDb($cfg);$db->beginTransaction();$data=dashboardSnapshot($db,$cfg,$days);$raw=dashboardCsv($data);$db->commit();
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="aaditechs-aggregate-'.$days.'d.csv"');header('Content-Length: '.strlen($raw));echo $raw;
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();http_response_code(404);exit('Unavailable');}
