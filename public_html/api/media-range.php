<?php
declare(strict_types=1);
// Full hash verification occurs before this single-range response calculation.
function mediaRange(string $kind,int $size,?string $range,string $method):array|false|null{
    if($range===null)return null;
    if($kind!=='video'||$method!=='GET'||$size<1||!preg_match('/^bytes=([0-9]{1,9})-([0-9]{0,9})$/D',$range,$m))return false;
    $start=(int)$m[1];$end=$m[2]===''?$size-1:min((int)$m[2],$size-1);
    return $start<$size&&$end>=$start?[$start,$end]:false;
}
