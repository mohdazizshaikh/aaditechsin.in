<?php
declare(strict_types=1);
function preflightFlagPaths():array{
    return [
        'analytics.enabled',
        'communications.auto_review_enabled',
        'communications.batch_import_enabled',
        'communications.bridge_enabled',
        'communications.bridges.calls.authorized',
        'communications.bridges.calls.enabled',
        'communications.bridges.sms.authorized',
        'communications.bridges.sms.enabled',
        'communications.bridges.whatsapp.authorized',
        'communications.bridges.whatsapp.enabled',
        'communications.enabled',
        'communications.gmail.enabled',
        'communications.gmail.renewal.authorized',
        'communications.gmail.renewal.enabled',
        'communications.gmail.renewal.scheduled_capture_enabled',
        'communications.gmail.revoked',
        'communications.native_usage.twilio-calls.authorized',
        'communications.native_usage.twilio-calls.enabled',
        'communications.native_usage.twilio-calls.revoked',
        'communications.native_usage.twilio-sms.authorized',
        'communications.native_usage.twilio-sms.enabled',
        'communications.native_usage.twilio-sms.revoked',
        'communications.native_usage.whatsapp.authorized',
        'communications.native_usage.whatsapp.enabled',
        'communications.native_usage.whatsapp.revoked',
        'communications.native_usage_enabled',
        'communications.retention_enabled',
        'communications.review_batch_enabled',
        'communications.reviews_enabled',
        'communications.twilio_inbound.calls.authorized',
        'communications.twilio_inbound.calls.enabled',
        'communications.twilio_inbound.calls.log_redaction_verified',
        'communications.twilio_inbound.sms.authorized',
        'communications.twilio_inbound.sms.enabled',
        'communications.twilio_inbound.sms.log_redaction_verified',
        'communications.twilio_retention_enabled',
        'communications.whatsapp_inbound.authorized',
        'communications.whatsapp_inbound.enabled',
        'communications.whatsapp_inbound.log_redaction_verified',
        'communications.whatsapp_retention_enabled',
        'creative.batch_enabled',
        'creative.enabled',
        'creative_transport.enabled',
        'database.enabled',
        'discovery.cadence_enabled',
        'discovery.enabled',
        'discovery.feeds_enabled',
        'discovery.followup_batch_enabled',
        'discovery.followups_enabled',
        'discovery.local_batch_enabled',
        'discovery.recovery_enabled',
        'discovery.scheduler_enabled',
        'discovery.tender_poll_enabled',
        'discovery.tender_review_enabled',
        'expert.enabled',
        'google.enabled',
        'growth.enabled',
        'growth.telegram.enabled',
        'growth.telegram.reports_enabled',
        'intelligence_dashboard.csv_enabled',
        'intelligence_dashboard.enabled',
        'leads.enabled',
        'leads.response_queue_enabled',
        'mail.enabled',
        'media.enabled',
        'media.native_review_enabled',
        'media_upload.enabled',
        'media_upload.facebook_enabled',
        'media_upload.fetch_enabled',
        'media_upload.fetch_log_redaction_verified',
        'media_upload.gbp_media_check_enabled',
        'media_upload.inbox_batch_enabled',
        'media_upload.inbox_import_enabled',
        'media_upload.instagram.enabled',
        'media_upload.instagram_carousel_enabled',
        'media_upload.instagram_images_enabled',
        'media_upload.instagram_mixed_carousel_enabled',
        'media_upload.inventory_enabled',
        'media_upload.linkedin_enabled',
        'media_upload.linkedin_video_enabled',
        'media_upload.native_import_enabled',
        'media_upload.retention_enabled',
        'media_upload.telegram_album_enabled',
        'media_upload.telegram_media_enabled',
        'media_upload.threads_carousel_enabled',
        'media_upload.threads_images_enabled',
        'media_upload.threads_mixed_carousel_enabled',
        'media_upload.threads_video_enabled',
        'media_upload.x_enabled',
        'media_upload.x_video_enabled',
        'media_upload.youtube.enabled',
        'oauth_lifecycle.alerts_enabled',
        'oauth_lifecycle.automatic_candidate_enabled',
        'oauth_lifecycle.batch_health_enabled',
        'oauth_lifecycle.enabled',
        'oauth_lifecycle.providers.facebook.revoked',
        'oauth_lifecycle.providers.google-business.revoked',
        'oauth_lifecycle.providers.instagram.revoked',
        'oauth_lifecycle.providers.linkedin.revoked',
        'oauth_lifecycle.providers.threads.revoked',
        'oauth_lifecycle.providers.x.revoked',
        'oauth_lifecycle.providers.youtube.revoked',
        'oauth_lifecycle.renewal.google-business.authorized',
        'oauth_lifecycle.renewal.google-business.enabled',
        'oauth_lifecycle.renewal.youtube.authorized',
        'oauth_lifecycle.renewal.youtube.enabled',
        'oauth_lifecycle.rotation_review_enabled',
        'portal.admin_totp_enabled',
        'portal.enabled',
        'reconciliation.enabled',
        'seo.competitor_review_batch_enabled',
        'seo.enabled',
        'seo.history_retention_enabled',
        'seo.live_crawl_enabled',
        'seo.search_console.authorized',
        'seo.search_console.enabled',
        'seo.search_console.history_enabled',
        'seo.search_console.revoked',
        'seo.search_console.scheduled_capture_enabled',
        'social.enabled',
        'social.meta.enabled',
        'social.meta.facebook.enabled',
        'social.meta.threads.enabled',
        'social.network.enabled',
        'social.network.google-business.enabled',
        'social.network.linkedin.enabled',
        'social.network.x.enabled',
        'social.telegram_channel.enabled',
    ];
}
function preflightDependencies():array{
    return [
        'leads.enabled'=>['database.enabled'],
        'leads.response_queue_enabled'=>['leads.enabled','portal.enabled','database.enabled'],
        'portal.enabled'=>['database.enabled'],
        'portal.admin_totp_enabled'=>['portal.enabled'],
        'analytics.enabled'=>['database.enabled'],
        'growth.enabled'=>['database.enabled'],
        'growth.telegram.enabled'=>['growth.enabled'],
        'growth.telegram.reports_enabled'=>['growth.telegram.enabled'],
        'discovery.enabled'=>['growth.enabled'],
        'discovery.followup_batch_enabled'=>['discovery.followups_enabled','discovery.enabled'],
        'discovery.scheduler_enabled'=>['discovery.enabled','discovery.feeds_enabled','discovery.cadence_enabled'],
        'seo.enabled'=>['growth.enabled'],
        'seo.competitor_review_batch_enabled'=>['seo.enabled'],
        'seo.live_crawl_enabled'=>['seo.enabled'],
        'seo.search_console.enabled'=>['seo.enabled'],
        'seo.search_console.history_enabled'=>['seo.search_console.enabled'],
        'creative.enabled'=>['growth.enabled'],
        'creative.batch_enabled'=>['creative.enabled'],
        'media.enabled'=>['creative.enabled'],
        'media.native_review_enabled'=>['media.enabled'],
        'media_upload.native_import_enabled'=>['media.native_review_enabled','media_upload.enabled','media_upload.inbox_import_enabled'],
        'media_upload.enabled'=>['media.enabled'],
        'media_upload.inventory_enabled'=>['media_upload.enabled'],
        'media_upload.retention_enabled'=>['media_upload.inventory_enabled','media_upload.enabled'],
        'media_upload.inbox_batch_enabled'=>['media_upload.inbox_import_enabled','media_upload.enabled'],
        'communications.enabled'=>['growth.enabled'],
        'communications.auto_review_enabled'=>['communications.reviews_enabled','communications.enabled'],
        'communications.review_batch_enabled'=>['communications.reviews_enabled','communications.enabled'],
        'communications.gmail.renewal.scheduled_capture_enabled'=>['communications.gmail.renewal.enabled','communications.gmail.renewal.authorized','communications.gmail.enabled','communications.enabled'],
        'seo.search_console.scheduled_capture_enabled'=>['seo.search_console.enabled','seo.search_console.authorized'],
        'communications.gmail.renewal.enabled'=>['communications.gmail.enabled','communications.enabled'],
        'oauth_lifecycle.batch_health_enabled'=>['growth.enabled','oauth_lifecycle.enabled'],
        'oauth_lifecycle.automatic_candidate_enabled'=>['oauth_lifecycle.enabled','oauth_lifecycle.rotation_review_enabled'],
        'oauth_lifecycle.renewal.google-business.enabled'=>['oauth_lifecycle.automatic_candidate_enabled','oauth_lifecycle.renewal.google-business.authorized','social.network.google-business.enabled'],
        'oauth_lifecycle.renewal.youtube.enabled'=>['oauth_lifecycle.automatic_candidate_enabled','oauth_lifecycle.renewal.youtube.authorized','media_upload.youtube.enabled'],
        'oauth_lifecycle.rotation_review_enabled'=>['growth.enabled','oauth_lifecycle.enabled'],
        'intelligence_dashboard.csv_enabled'=>['intelligence_dashboard.enabled'],
        'social.network.linkedin.enabled'=>['social.network.enabled'],
        'social.network.x.enabled'=>['social.network.enabled'],
        'social.network.google-business.enabled'=>['social.network.enabled'],
        'social.meta.facebook.enabled'=>['social.meta.enabled'],
        'social.meta.threads.enabled'=>['social.meta.enabled'],
        'social.network.enabled'=>['social.enabled'],
        'social.meta.enabled'=>['social.enabled'],
        'social.telegram_channel.enabled'=>['social.enabled'],
        'discovery.cadence_enabled'=>['discovery.enabled'],
        'discovery.feeds_enabled'=>['discovery.enabled'],
        'discovery.followups_enabled'=>['discovery.enabled'],
        'discovery.local_batch_enabled'=>['discovery.enabled'],
        'discovery.tender_review_enabled'=>['discovery.enabled'],
        'discovery.tender_poll_enabled'=>['discovery.enabled','discovery.tender_review_enabled'],
        'discovery.recovery_enabled'=>['discovery.enabled'],
        'communications.batch_import_enabled'=>['communications.enabled'],
        'communications.bridge_enabled'=>['communications.enabled'],
        'communications.bridges.calls.enabled'=>['communications.enabled'],
        'communications.bridges.sms.enabled'=>['communications.enabled'],
        'communications.bridges.whatsapp.enabled'=>['communications.enabled'],
        'communications.gmail.enabled'=>['communications.enabled'],
        'communications.native_usage.twilio-calls.enabled'=>['communications.enabled'],
        'communications.native_usage.twilio-sms.enabled'=>['communications.enabled'],
        'communications.native_usage.whatsapp.enabled'=>['communications.enabled'],
        'communications.native_usage_enabled'=>['communications.enabled'],
        'communications.retention_enabled'=>['communications.enabled'],
        'communications.reviews_enabled'=>['communications.enabled'],
        'communications.twilio_inbound.calls.enabled'=>['communications.enabled'],
        'communications.twilio_inbound.sms.enabled'=>['communications.enabled'],
        'communications.twilio_retention_enabled'=>['communications.enabled'],
        'communications.whatsapp_inbound.enabled'=>['communications.enabled'],
        'communications.whatsapp_retention_enabled'=>['communications.enabled'],
        'media_upload.facebook_enabled'=>['media_upload.enabled'],
        'media_upload.fetch_enabled'=>['media_upload.enabled'],
        'media_upload.gbp_media_check_enabled'=>['media_upload.enabled'],
        'media_upload.inbox_import_enabled'=>['media_upload.enabled'],
        'media_upload.instagram.enabled'=>['media_upload.enabled'],
        'media_upload.instagram_carousel_enabled'=>['media_upload.enabled'],
        'media_upload.instagram_images_enabled'=>['media_upload.enabled'],
        'media_upload.instagram_mixed_carousel_enabled'=>['media_upload.enabled'],
        'media_upload.linkedin_enabled'=>['media_upload.enabled'],
        'media_upload.linkedin_video_enabled'=>['media_upload.enabled'],
        'media_upload.telegram_album_enabled'=>['media_upload.enabled'],
        'media_upload.telegram_media_enabled'=>['media_upload.enabled'],
        'media_upload.threads_carousel_enabled'=>['media_upload.enabled'],
        'media_upload.threads_images_enabled'=>['media_upload.enabled'],
        'media_upload.threads_mixed_carousel_enabled'=>['media_upload.enabled'],
        'media_upload.threads_video_enabled'=>['media_upload.enabled'],
        'media_upload.x_enabled'=>['media_upload.enabled'],
        'media_upload.x_video_enabled'=>['media_upload.enabled'],
        'media_upload.youtube.enabled'=>['media_upload.enabled'],
    ];
}

function preflightValue(array $cfg,string $path):mixed{
    $v=$cfg;
    foreach(explode('.',$path) as $k){if(!is_array($v)||!array_key_exists($k,$v))return null;$v=$v[$k];}
    return $v;
}
function preflightFlags(array $cfg):array{
    $out=[];
    foreach(preflightFlagPaths() as $path){
        $v=preflightValue($cfg,$path);
        $out[$path]=$v===true?'ON':($v===false?'OFF':'INVALID_OR_MISSING');
    }
    return $out;
}
function preflightDependencyFailures(array $cfg):array{
    $errors=[];
    foreach(preflightDependencies() as $flag=>$required){
        if(preflightValue($cfg,$flag)!==true)continue;
        foreach($required as $dep)if(preflightValue($cfg,$dep)!==true)$errors[]=$flag.' requires '.$dep;
    }
    return $errors;
}
function preflightBaseline(array $cfg,string $expected):array{
    // Exact operator-supplied non-production HTTPS origin, not DNS or ownership proof.
    $host=parse_url($expected,PHP_URL_HOST);
    $valid=preg_match('~^https://[a-z0-9](?:[a-z0-9.-]*[a-z0-9])$~D',$expected)===1&&is_string($host)&&str_contains($host,'.')&&filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)!==false&&filter_var($host,FILTER_VALIDATE_IP)===false&&!in_array($host,['aaditechs.in','www.aaditechs.in','aaditechsin.in','www.aaditechsin.in'],true);
    $errors=[];
    if(!$valid||($cfg['public_base_url']??null)!==$expected)$errors[]='Exact isolated HTTPS origin required';
    foreach(preflightFlags($cfg) as $path=>$state)if($state!=='OFF')$errors[]=$path.' must be explicitly OFF';
    return $errors;
}
function preflightContract(string $path):array{
    $raw=file_get_contents($path);if($raw===false||strlen($raw)>131072)throw new RuntimeException('Contract unavailable');
    $c=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(($c['version']??null)!==1||!is_array($c['tables']??null)||count($c['tables'])!==49)throw new RuntimeException('Contract inventory');
    foreach($c['tables'] as $t=>$columns){
        if(!preg_match('/^[a-z0-9_]+$/D',$t)||!is_array($columns)||!array_is_list($columns)||!$columns||count($columns)!==count(array_unique($columns)))throw new RuntimeException('Contract table');
        foreach($columns as $col)if(!is_string($col)||!preg_match('/^[a-z0-9_]+$/D',$col))throw new RuntimeException('Contract column');
    }
    return $c['tables'];
}
function preflightSchemaFailures(array $expected,array $actual):array{
    $missing=[];
    foreach($expected as $table=>$columns){
        if(!isset($actual[$table])){$missing[]=$table;continue;}
        foreach($columns as $column)if(!isset($actual[$table][$column]))$missing[]=$table.'.'.$column;
    }
    return $missing;
}

function preflightPrivateConfig(string $public,string $private,string $config):bool{
    $w=realpath($public);$s=realpath($private);$c=realpath($config);
    if($w===false||$s===false||$c===false||$s!==$private||$c!==$config||dirname($c)!==$s||is_link($private)||is_link($config)||!is_dir($s)||!is_file($c))return false;
    if($s===$w||str_starts_with($s,$w.'/')||str_starts_with($w,$s.'/'))return false;
    $st=lstat($c);return $st!==false&&$st['nlink']===1&&($st['mode']&0077)===0&&(fileperms($s)&0077)===0;
}
