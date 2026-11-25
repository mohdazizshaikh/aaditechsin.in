<?php
declare(strict_types=1);
require_once __DIR__.'/seo-core.php';
require_once __DIR__.'/creative-core.php';
function liveSeoEnabled(array $cfg):bool{return seoEnabled($cfg)&&($cfg['seo']['live_crawl_enabled']??false)===true;}
function liveSeoPaths():array{return ['/','/video-conferencing-solutions/','/auditorium-av-solutions/','/professional-sound-systems/','/av-project-planner/','/data-recovery/','/it-amc/'];}
function liveSeoFullPaths():array{return array_merge(liveSeoPaths(),['/it-infrastructure/','/software-web-development/','/cloud-modern-it/','/security-compliance/','/data-management-backup/','/it-asset-procurement/','/smart-office-solutions/','/it-special-projects/','/av-installation-maintenance/']);}
function liveSeoValidate(array $r,DateTimeImmutable $now):array{
    $version=$r['version']??null;
    $fields=['version','scope','origin','captured_utc','pages','counts'];
    $paths=$version===3?liveSeoFullPaths():liveSeoPaths();$pageCount=count($paths);
    if(in_array($version,[2,3],true))$fields[]='page_details';
    if(count($r)!==count($fields)||array_diff($fields,array_keys($r))||!in_array($version,[1,2,3],true)||$r['scope']!=='public-html-observation'||$r['origin']!=='https://aaditechs.in'||$r['pages']!==$pageCount||!is_string($r['captured_utc'])||!is_array($r['counts']))throw new InvalidArgumentException('Report');
    $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$r['captured_utc'],new DateTimeZone('UTC'));
    if(!$d||$d->format('Y-m-d\TH:i:s\Z')!==$r['captured_utc']||$d>$now||$d<$now->modify('-24 hours'))throw new InvalidArgumentException('Stale report');
    $keys=['html_ok','http_non200','unavailable','missing_title','missing_description','h1_review','canonical_review','noindex_meta','missing_alt_page'];
    $c=$r['counts'];if(count($c)!==count($keys)||array_diff($keys,array_keys($c)))throw new InvalidArgumentException('Counts');
    foreach($c as $n)if(!is_int($n)||$n<0||$n>$pageCount)throw new InvalidArgumentException('Count');
    if($c['html_ok']+$c['http_non200']+$c['unavailable']!==$pageCount)throw new InvalidArgumentException('Coverage');
    foreach(array_slice($keys,3) as $k)if($c[$k]>$c['html_ok'])throw new InvalidArgumentException('Coverage');
    if(in_array($version,[2,3],true)){
        $detail=$r['page_details'];
        if(!is_array($detail)||!array_is_list($detail)||count($detail)!==count($paths))throw new InvalidArgumentException('Page coverage');
        $derived=array_fill_keys($keys,0);$allowed=array_slice($keys,3);
        foreach($detail as $i=>$row){
            if(!is_array($row)||count($row)!==3||array_diff(['path','status','findings'],array_keys($row))||$row['path']!==$paths[$i]||!in_array($row['status'],array_slice($keys,0,3),true)||!is_array($row['findings'])||!array_is_list($row['findings'])||count($row['findings'])>count($allowed))throw new InvalidArgumentException('Page detail');
            if($row['status']!=='html_ok'&&$row['findings'])throw new InvalidArgumentException('Uninspected findings');
            ++$derived[$row['status']];$seen=[];
            foreach($row['findings'] as $finding){
                if(!in_array($finding,$allowed,true)||isset($seen[$finding]))throw new InvalidArgumentException('Invalid finding');
                $seen[$finding]=true;++$derived[$finding];
            }
        }
        foreach($derived as $key=>$value)if($c[$key]!==$value)throw new InvalidArgumentException('Detail count mismatch');
    }
    ksort($c);return ['captured'=>$d->format('Y-m-d H:i:s'),'counts'=>$c];
}
function liveSeoCapture(PDO $db,array $cfg,array $r,?DateTimeImmutable $now=null):void{
    if(!liveSeoEnabled($cfg)||!$db->inTransaction())throw new RuntimeException('Disabled');creativeActive($db);
    $v=liveSeoValidate($r,$now??new DateTimeImmutable('now'));$json=json_encode($v['counts'],JSON_THROW_ON_ERROR);
    $old=$db->query('SELECT captured_at FROM growth_live_seo_snapshot WHERE id=1 FOR UPDATE')->fetchColumn();
    if($old!==false&&$old>=$v['captured'])throw new RuntimeException('Older or duplicate report');
    $hash=hash('sha256',$v['captured'].':'.$json);
    $db->exec('SAVEPOINT live_seo_capture');
    try{
    $db->prepare('INSERT INTO growth_live_seo_snapshot(id,report_hash,counts_json,captured_at) VALUES(1,?,?,?) ON DUPLICATE KEY UPDATE report_hash=VALUES(report_hash),counts_json=VALUES(counts_json),captured_at=VALUES(captured_at)')->execute([$hash,$json,$v['captured']]);
    growthAudit($db,null,null,'live_seo_capture','private_cli');
    $db->exec('RELEASE SAVEPOINT live_seo_capture');
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT live_seo_capture');$db->exec('RELEASE SAVEPOINT live_seo_capture');throw $e;}
}
function liveSeoSummary(PDO $db,array $cfg,?DateTimeImmutable $now=null):string{
    if(!liveSeoEnabled($cfg))return 'Public SEO observation disabled.';
    $r=$db->query('SELECT counts_json,captured_at FROM growth_live_seo_snapshot WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    if(!$r)return 'Public SEO observation: no report.';
    $ts=strtotime($r['captured_at'].' UTC');$t=($now??new DateTimeImmutable('now'))->getTimestamp();
    if($ts===false||$ts>$t||$t-$ts>86400)return 'Public SEO observation: stale report.';
    $c=json_decode($r['counts_json'],true,8,JSON_THROW_ON_ERROR);
    $lines=['Operator-imported public HTML observation '.$r['captured_at'].' UTC: '.$c['html_ok'].'/'.($c['html_ok']+$c['http_non200']+$c['unavailable']).' HTML pages inspected.'];
    foreach($c as $k=>$n)if($k!=='html_ok')$lines[]=$k.': '.$n;
    $lines[]='Findings on inspected pages only; not uptime, rendered JS, indexing or rankings.';
    return implode("\n",$lines);
}
