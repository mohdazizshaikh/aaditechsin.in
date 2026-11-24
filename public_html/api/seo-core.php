<?php
declare(strict_types=1);
require_once __DIR__.'/growth-core.php';
function seoEnabled(array $cfg):bool{return ($cfg['growth']['enabled']??false)===true&&($cfg['seo']['enabled']??false)===true;}
function seoTopics():array {
    $v=json_decode((string)file_get_contents(dirname(__DIR__).'/assets/seo-topics.json'),true,32,JSON_THROW_ON_ERROR);
    if(($v['version']??null)!==1||array_diff_key(growthCatalog(),$v['services'])||array_diff_key($v['services'],growthCatalog()))throw new RuntimeException('Topic coverage mismatch');
    return $v['services'];
}
function seoRegister(PDO $db,array $r):array {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $p=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
    if($p===false||(int)$p!==0)throw new RuntimeException('Growth paused');
    if(($r['version']??null)!==1||($r['scope']??null)!=='source-only'||!is_int($r['pages']??null)||$r['pages']<1||$r['pages']>1000||!is_array($r['counts']??null))throw new InvalidArgumentException('Invalid report');
    $codes=['missing_title','title_length_review','duplicate_title','missing_description','description_length_review','duplicate_description','h1_count','canonical_mismatch','missing_alt','broken_internal_link','missing_sitemap_page','unknown_sitemap_page','robots_review','noindex_public'];
    $counts=$r['counts'];
    foreach($counts as $k=>$v)if(!in_array($k,$codes,true)||!is_int($v)||$v<1||$v>100000)throw new InvalidArgumentException('Invalid count');
    ksort($counts);$json=json_encode((object)$counts,JSON_THROW_ON_ERROR);
    $hash=hash('sha256',$r['pages'].':'.$json);
    $q=$db->prepare('INSERT IGNORE INTO growth_seo_reports(report_hash,page_count,counts_json) VALUES(?,?,?)');$q->execute([$hash,$r['pages'],$json]);
    return ['status'=>$q->rowCount()===1?'recorded':'duplicate_summary','sha256'=>$hash];
}
function seoCommand(PDO $db,array $cfg,string $command):?string {
    if($command==='/searchconsole'){require_once __DIR__.'/search-console-core.php';return scSummary($db,$cfg);}
    if($command!=='/seo'&&!preg_match('/^\\/seotopics(?: |$)/D',$command))return null;
    if(!seoEnabled($cfg))return 'SEO module disabled.';
    if($command==='/seo'){
        $r=$db->query('SELECT page_count,counts_json,created_at FROM growth_seo_reports ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if(!$r)return 'No source SEO audit summary recorded. Use /searchconsole for separately authorized search analytics.';
        $lines=['Last distinct source SEO summary: '.$r['created_at'],'Pages inspected: '.$r['page_count']];
        foreach(json_decode($r['counts_json'],true,32,JSON_THROW_ON_ERROR) as $k=>$n)$lines[]=$k.': '.$n;
        $lines[]='Source audit only, not live rankings or traffic. Use /searchconsole for separate evidence.';
        return implode("\n",$lines);
    }
    if(preg_match('/^\\/seotopics ([a-z0-9-]+)$/D',$command,$m)&&isset(seoTopics()[$m[1]]))return "Suggested topics (not measured keywords):\n".implode("\n",seoTopics()[$m[1]]);
    return 'Use /seotopics SERVICE. All 14 catalog services supported.';
}
