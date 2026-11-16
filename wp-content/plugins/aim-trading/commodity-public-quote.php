<?php
defined('ABSPATH') || exit;

/** Public reference prices used for manual AIM practice orders. */
final class AIM_Commodity_Public_Quote {
 const METALS=array('XAU_USD'=>'XAU','XAG_USD'=>'XAG','XPT_USD'=>'XPT','XPD_USD'=>'XPD');
 const DAILY=array(
  'WTICO_USD'=>array('pet/hist/RWTCD.htm','Cushing, OK WTI Spot Price FOB'),
  'BCO_USD'=>array('pet/hist/RBRTED.htm','Europe Brent Spot Price FOB'),
  'NG_USD'=>array('ng/hist/rngwhhdD.htm','Henry Hub Natural Gas Spot Price (Dollars per Million Btu)'),
 );
 const GOLD_SOURCE='Gold API';
 const OIL_SOURCE='EIA';
 const SPREAD=0.001;

 static function source($symbol){
  return isset(self::METALS[$symbol])?self::GOLD_SOURCE:(isset(self::DAILY[$symbol])?self::OIL_SOURCE:null);
 }
 static function instruments($en){
  return array(
   array('CAPITALCOM:XPTUSD','XPT/USD',$en?'Platinum':'Bạch kim','USD/oz'),
   array('OANDA:XPDUSD','XPD/USD','Palladium','USD/oz'),
   array('NYMEX:NG1!','NG/USD',$en?'Henry Hub natural gas':'Khí tự nhiên Henry Hub','USD/MMBtu'),
  );
 }
 static function is_public($source){return in_array($source,array(self::GOLD_SOURCE,self::OIL_SOURCE),true);}
 static function now(){return (int)round(microtime(true)*1000);}

 static function metal($body,$symbol,$now){
  $data=json_decode($body,true);
  if(($data['symbol']??null)!==self::METALS[$symbol])throw new RuntimeException('Gold API trả sai mã.');
  $time=AIM_Commodity_Feed::timestamp($data['updatedAt']??null);
  if($time>$now+2000||$now-$time>180000)throw new RuntimeException('Gold API chưa có giá cập nhật.');
  return self::book($symbol,$data['price']??null,$time,$now,'timestamp');
 }

 static function daily($body,$symbol,$now){
  $name=self::DAILY[$symbol][1];
  if(strpos($body,$name)===false||!preg_match('/Release Date:\s*(\d{1,2}\/\d{1,2}\/\d{4})/i',$body,$release)){
   throw new RuntimeException('EIA chưa trả đúng bảng giá giao ngay.');
  }
  $published=DateTimeImmutable::createFromFormat('!n/j/Y',$release[1],new DateTimeZone('UTC'));
  if(!$published||$published->format('n/j/Y')!==ltrim(str_replace('/0','/',$release[1]),'0')||$published->getTimestamp()*1000>$now){
   throw new RuntimeException('Ngày công bố giá EIA không hợp lệ.');
  }
  preg_match_all("~<td\\b[^>]*class=['\"]B6['\"][^>]*>(.*?)</td>((?:\\s*<td\\b[^>]*class=['\"]B3['\"][^>]*>.*?</td>){5})~is",$body,$rows,PREG_SET_ORDER);
  foreach(array_reverse($rows) as $row){
   $label=trim(str_replace("\u{00a0}",' ',html_entity_decode(strip_tags($row[1]),ENT_QUOTES,'UTF-8')));
   if(!preg_match('/^(\d{4}) ([A-Z][a-z]{2})-\s*(\d{1,2}) to [A-Z][a-z]{2}-\s*\d{1,2}$/D',$label,$week))continue;
   $date=DateTimeImmutable::createFromFormat('!Y M j',$week[1].' '.$week[2].' '.(int)$week[3],new DateTimeZone('UTC'));
   if(!$date||$date->format('N')!=='1'||$date->format('Y M j')!==$week[1].' '.$week[2].' '.(int)$week[3])continue;
   preg_match_all('~<td\\b[^>]*>(.*?)</td>~is',$row[2],$cells);
   for($i=4;$i>=0;$i--){
    $raw=trim(strip_tags($cells[1][$i]??''));
    $observed=$date->modify('+'.$i.' days');
    if(!is_numeric($raw)||(float)$raw<=0||$observed>$published)continue;
    $q=self::book($symbol,$raw,$observed->getTimestamp()*1000,$now,'daily');
    $q['observation_date']=$observed->format('Y-m-d');
    $q['published_date']=$published->format('Y-m-d');
    return $q;
   }
  }
  throw new RuntimeException('EIA chưa công bố giá hợp lệ.');
 }

 static function book($symbol,$raw,$time,$now,$basis){
  $price=AIM_Commodity_Feed::number($raw);
  if($price>=999000||$time<=0)throw new RuntimeException('Giá tham chiếu ngoài giới hạn.');
  $bid=$price*(1-self::SPREAD/2);$ask=$price*(1+self::SPREAD/2);
  return array('symbol'=>$symbol,'source'=>self::source($symbol),'time'=>$time,'received'=>$now,
   'time_basis'=>$basis,'tradeable'=>true,'open'=>true,'manual_only'=>true,
   'reference'=>$price,'bid'=>$bid,'ask'=>$ask,'spread_rate'=>self::SPREAD,
   'bids'=>array(array('price'=>$bid,'units'=>1e7)),'asks'=>array(array('price'=>$ask,'units'=>1e7)));
 }

 static function quote($symbol){
  $source=self::source($symbol);
  if($source===null)return null;
  $key='aimtr_public_quote_'.strtolower($symbol);
  $cached=get_transient($key);
  if(is_array($cached))return $cached;
  if(get_transient($key.'_retry'))return AIM_Trading::error('Nguồn tham chiếu đang tạm gián đoạn.',503);
  $metal=isset(self::METALS[$symbol]);
  $url=$metal?'https://api.gold-api.com/price/'.self::METALS[$symbol]:'https://www.eia.gov/dnav/'.self::DAILY[$symbol][0];
  $response=wp_safe_remote_get($url,array('timeout'=>8,'redirection'=>0,'limit_response_size'=>$metal?16384:2000000));
  try{
   if(is_wp_error($response)||(int)wp_remote_retrieve_response_code($response)!==200)throw new RuntimeException('Chưa đọc được giá từ '.$source.'.');
   $body=wp_remote_retrieve_body($response);$now=self::now();
   $quote=$metal?self::metal($body,$symbol,$now):self::daily($body,$symbol,$now);
   set_transient($key,$quote,$metal?60:900);
   return $quote;
  }catch(RuntimeException $e){
   set_transient($key.'_retry',1,60);
   return AIM_Trading::error($e->getMessage(),503);
  }
 }

 static function spec($symbol){
  $spec=AIM_Reference_Feed::spec($symbol);
  if(in_array($symbol,array('XPT_USD','XPD_USD'),true))$spec['lot_units']=10;
  if($symbol==='NG_USD'){
   $spec['display_precision']=3;
   $spec['tick']=0.001;
   $spec['pip']=0.001;
  }
  return $spec;
 }

 static function valid_clock($q,$now){
  $time=$q['time']??null;$received=$q['received']??null;$symbol=$q['symbol']??'';
  if(!is_int($time)||!is_int($received)||$time<=0||$received<=0||$received>$now+2000||$time>$received+2000||($q['source']??null)!==self::source($symbol)||empty($q['manual_only']))return false;
  if(isset(self::METALS[$symbol]))return ($q['time_basis']??'')==='timestamp'&&$now-$received<=90000&&$now-$time<=180000;
  return ($q['time_basis']??'')==='daily'&&($q['observation_date']??'')===gmdate('Y-m-d',(int)($time/1000))&&$now-$received<=900000;
 }
}
