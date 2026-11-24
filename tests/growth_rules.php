<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/growth-core.php';
function checkGrowth(bool $ok,string $label): void {if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}}
$catalog=growthCatalog();checkGrowth(count($catalog)===14,'all services');
$js=file_get_contents(dirname(__DIR__).'/public_html/assets/enhancements.js');
preg_match_all("/\\{title:'([^']+)'/",$js,$titles);
checkGrowth(array_column($catalog,'title')===$titles[1],'catalog matches website');
$secret=str_repeat('A',40);$cfg=['growth'=>['enabled'=>true,'telegram'=>['enabled'=>true,'webhook_secret'=>$secret,'owner_user_id'=>'12345','owner_chat_id'=>'12345']]];
$u=['update_id'=>1,'message'=>['date'=>1000,'from'=>['id'=>12345,'is_bot'=>false],'chat'=>['id'=>12345,'type'=>'private'],'text'=>'/today']];
checkGrowth(growthOwnerUpdate($u,$cfg,$secret,1000),'owner accepted');
checkGrowth(!growthOwnerUpdate($u,$cfg,'bad',1000),'bad secret rejected');
checkGrowth(!growthOwnerUpdate($u,$cfg,$secret,5000),'stale command rejected');
$x=$u;$x['message']['from']['id']=12346;checkGrowth(!growthOwnerUpdate($x,$cfg,$secret,1000),'other user rejected');
$x=$u;$x['message']['chat']['type']='group';checkGrowth(!growthOwnerUpdate($x,$cfg,$secret,1000),'group rejected');
$x=$cfg;$x['growth']['enabled']=false;checkGrowth(!growthOwnerUpdate($u,$x,$secret,1000),'default-off gate');
echo "V9.1 catalog and Telegram authorization rules: PASS\n";
