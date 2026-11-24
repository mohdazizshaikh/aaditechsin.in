<?php
declare(strict_types=1);
require_once __DIR__.'/creative-core.php';
function publicationOverview(PDO $db,array $cfg,string $cmd):?string{
    if($cmd!=='/publications')return null;
    if(!creativeEnabled($cfg)||($cfg['social']['enabled']??false)!==true)return 'Social reporting disabled.';
    $sql="SELECT provider,status,COUNT(*) AS n FROM (SELECT 'telegram' AS provider,status FROM growth_publications UNION ALL SELECT provider,status FROM growth_meta_publications UNION ALL SELECT provider,status FROM growth_network_publications UNION ALL SELECT 'youtube' AS provider,status FROM growth_media_uploads UNION ALL SELECT 'instagram' AS provider,status FROM growth_instagram_uploads UNION ALL SELECT 'facebook' AS provider,status FROM growth_facebook_media UNION ALL SELECT provider,status FROM growth_network_media UNION ALL SELECT provider,status FROM growth_meta_images UNION ALL SELECT 'linkedin' AS provider,status FROM growth_linkedin_video UNION ALL SELECT 'x' AS provider,status FROM growth_x_video UNION ALL SELECT provider,status FROM growth_carousels) AS all_posts GROUP BY provider,status ORDER BY provider,status";
    $lines=['All publication ledgers (historical counts, not reach or visibility):'];
    foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]=$r['provider'].' '.$r['status'].': '.$r['n'];
    $lines[]='Threads/Instagram JPEG carousels, LinkedIn/X/Threads video, Threads/Instagram images, LinkedIn/X images, Facebook media, Instagram Reels and YouTube staged upload source only. Sending/uploading/publishing/unknown require manual reconciliation.';
    return implode("\n",$lines);
}
