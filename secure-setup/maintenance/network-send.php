<?php
declare(strict_types=1);
function networkRequest(array $p,string $method,string $path,array $data=[]):array {
    if(PHP_SAPI!=='cli'||!function_exists('curl_init'))throw new RuntimeException('CLI curl required');
    $host=match($p['provider']){'linkedin'=>'api.linkedin.com','x'=>'api.x.com','google-business'=>'mybusiness.googleapis.com'};
    $url='https://'.$host.'/'.$path;
    if($method==='GET'&&$data)$url.='?'.http_build_query($data);
    $headers=['Authorization: Bearer '.$p['token'],'Accept: application/json','Content-Type: application/json'];
    if($p['provider']==='linkedin'){$headers[]='LinkedIn-Version: '.$p['version'];$headers[]='X-Restli-Protocol-Version: 2.0.0';}
    $ch=curl_init($url);$body='';$receipt=null;
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,
        CURLOPT_HEADERFUNCTION=>static function($ch,string $line)use(&$receipt):int{if(preg_match('/^HTTP\//i',$line))$receipt=null;if(stripos($line,'x-restli-id:')===0)$receipt=trim(substr($line,12));return strlen($line);},
        CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>65536)return 0;$body.=$chunk;return strlen($chunk);}]);
    if($method==='POST'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$p['provider']==='x'&&preg_match('#^2/media/upload/[1-9][0-9]{0,18}/finalize$#D',$path)&&$data===[]?'':json_encode($data,JSON_THROW_ON_ERROR));}
    $ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    return ['http'=>$ok===false?0:$code,'json'=>json_decode($body,true,32,JSON_BIGINT_AS_STRING),'receipt'=>$receipt];
}
function networkGooglePath(array $p):string {
    [$account,$location]=explode(':',$p['id']);
    return 'v4/accounts/'.$account.'/locations/'.$location.'/localPosts';
}
function networkIdentity(array $p,callable $request):bool {
    if($p['provider']==='x'){
        $r=$request($p,'GET','2/users/me',[]);
        return ($r['http']??0)===200&&isset($r['json']['data']['id'])&&(string)$r['json']['data']['id']===$p['id'];
    }
    if($p['provider']==='google-business'){
        // Scoped access check for this configured account/location path; not publish permission.
        $r=$request($p,'GET',networkGooglePath($p),['pageSize'=>1]);
        return ($r['http']??0)===200&&is_array($r['json']??null)&&!isset($r['json']['error']);
    }
    // Conservative administrator-only policy. Bounded paging, never follow supplied URLs.
    for($start=0;$start<100;$start+=10){
        $r=$request($p,'GET','rest/organizationAcls',['q'=>'roleAssignee','role'=>'ADMINISTRATOR','state'=>'APPROVED','count'=>10,'start'=>$start]);
        if(($r['http']??0)!==200||!is_array($r['json']['elements']??null))return false;
        foreach($r['json']['elements'] as $e){
            if(($e['role']??null)==='ADMINISTRATOR'&&($e['state']??null)==='APPROVED'&&($e['organization']??$e['organizationTarget']??null)==='urn:li:organization:'.$p['id'])return true;
        }
        if(count($r['json']['elements'])<10)return false;
    }
    return false;
}
function networkResult(string $status,?string $receipt=null,string $error=''):array{return ['status'=>$status,'receipt'=>$receipt,'error'=>$error];}
function networkSend(array $p,array $row,callable $request):array {
    if($row['provider']!==$p['provider']||$row['target_id']!==$p['id'])return networkResult('failed',null,'target_mismatch');
    $caption=$row['caption'];$limit=$p['provider']==='x'?280:900;
    if(!is_string($caption)||trim($caption)===''||strlen($caption)>$limit)return networkResult('failed',null,'invalid_caption');
    try{
        if(!networkIdentity($p,$request))return networkResult('failed',null,'identity_unverified');
        if($p['provider']==='linkedin'){
            $r=$request($p,'POST','rest/posts',['author'=>'urn:li:organization:'.$p['id'],'commentary'=>$caption,'visibility'=>'PUBLIC','distribution'=>['feedDistribution'=>'MAIN_FEED','targetEntities'=>[],'thirdPartyDistributionChannels'=>[]],'lifecycleState'=>'PUBLISHED','isReshareDisabledByAuthor'=>false]);
            $id=$r['receipt']??null;$valid=is_string($id)&&preg_match('/^urn:li:(?:share|ugcPost):[0-9]{1,30}$/D',$id);
        }elseif($p['provider']==='x'){
            $r=$request($p,'POST','2/tweets',['text'=>$caption]);$id=$r['json']['data']['id']??null;$valid=is_string($id)&&preg_match('/^[1-9][0-9]{0,30}$/D',$id);
        }else{
            $path=networkGooglePath($p);$r=$request($p,'POST',$path,['languageCode'=>'en','summary'=>$caption,'topicType'=>'STANDARD']);
            $id=$r['json']['name']??null;$valid=is_string($id)&&preg_match('/^'.preg_quote(substr($path,3),'/').'\/[A-Za-z0-9_-]{1,40}$/D',$id);
            // Resource created does not mean LIVE. Preserve receipt for reconciliation.
        }
        $http=$r['http']??0;
        if($http>=200&&$http<300&&$valid)return networkResult('accepted',$id);
        if(in_array($http,[400,401,403,404,429],true))return networkResult('failed',null,'provider_rejected');
        return networkResult('unknown',null,'ambiguous_response');
    }catch(Throwable $e){return networkResult('unknown',null,'transport_error');}
}
