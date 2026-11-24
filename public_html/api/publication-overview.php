<?php
declare(strict_types=1);
require_once __DIR__.'/creative-core.php';
function publicationOverview(PDO $db,array $cfg,string $cmd):?string{
    if($cmd!=='/publications')return null;
    if(!creativeEnabled($cfg)||($cfg['social']['enabled']??false)!==true)return 'Social reporting disabled.';
    $sql="SELECT provider,status,COUNT(*) AS n FROM (SELECT 'telegram' AS provider,status FROM growth_publications UNION ALL SELECT provider,status FROM growth_meta_publications UNION ALL SELECT provider,status FROM growth_network_publications) AS all_posts GROUP BY provider,status ORDER BY provider,status";
    $lines=['All publication ledgers (historical counts, not reach or visibility):'];
    foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]=$r['provider'].' '.$r['status'].': '.$r['n'];
    $lines[]='Instagram/YouTube: upload not implemented. Sending/unknown require manual reconciliation.';
    return implode("\n",$lines);
}
