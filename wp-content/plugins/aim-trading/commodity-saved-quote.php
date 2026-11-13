<?php
defined('ABSPATH') || exit;

/** Last executable book per commodity. Only the manual order path may reuse it. */
final class AIM_Commodity_Saved_Quote {
 static function source($symbol){
  if(!AIM_Reference_Feed::selected())return 'OANDA Practice';
  return isset(AIM_Oil_Reference::map()[$symbol])?AIM_Oil_Reference::SOURCE:AIM_Reference_Feed::SOURCE;
 }
 static function key($symbol,$source=null){
  if(AIM_Commodity_Public_Quote::is_public($source))return 'aimtr_public_book_'.strtolower($symbol);
  return self::legacy_key($symbol);
 }
 static function legacy_key($symbol){return 'aimtr_commodity_book_'.strtolower($symbol);}
 static function now(){return (int)round(microtime(true)*1000);}
 static function valid($symbol,$quote,$spec,$now){
  if(($spec['symbol']??null)!==$symbol||!in_array($quote['source']??null,array(self::source($symbol),AIM_Commodity_Public_Quote::source($symbol)),true))return false;
  try{AIM_Commodity_Engine::quote($quote,$symbol,$now);return true;}
  catch(RuntimeException $e){return false;}
 }
 static function remember($snapshot){
  if(is_wp_error($snapshot))return;
  foreach($snapshot['quotes'] as $symbol=>$quote){
   $spec=$snapshot['specs'][$symbol]??null;
   if(!in_array($symbol,AIM_Commodity_Feed::symbols(),true)||!self::valid($symbol,$quote,$spec,self::now()))continue;
   global $wpdb;
   $key=self::key($symbol,$quote['source']);$lock='aimtr_book_'.md5($wpdb->prefix.$key);
   if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1)continue;
   try{
    wp_cache_delete($key,'options');
    $prior=get_option($key);
    if(($prior['quote']['source']??null)!==$quote['source']||($prior['quote']['time']??0)<=$quote['time']){
     update_option($key,array('quote'=>$quote,'spec'=>$spec),false);
    }
   }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
  }
 }
 static function saved($symbol,$source=null){
  $entry=get_option(self::key($symbol,$source));
  $quote=$entry['quote']??null;$spec=$entry['spec']??null;
  // Validate against the original server receive time.
  $received=$quote['received']??0;
  if(!is_int($received)||$received<=0||$received>self::now()+2000||!self::valid($symbol,$quote,$spec,$received))return null;
  return $entry;
 }
 static function order($snapshot,$symbol,$position=null,$public=false){
  $now=self::now();$quote=is_wp_error($snapshot)?null:($snapshot['quotes'][$symbol]??null);
  $spec=is_wp_error($snapshot)?null:($snapshot['specs'][$symbol]??null);
  $saved=false;
  $entry=self::saved($symbol,$public?AIM_Commodity_Public_Quote::source($symbol):null);
  if(!self::valid($symbol,$quote,$spec,$now)||($entry!==null&&$entry['quote']['time']>$quote['time'])){
   if($entry===null){
    $reason=is_wp_error($snapshot)?$snapshot->get_error_message():'';
    return AIM_Trading::error(trim($reason.' Chưa có báo giá giao dịch hợp lệ đã lưu cho mã này.'),409);
   }
   $quote=$entry['quote'];$spec=$entry['spec'];$saved=true;
  }
  $manual_only=!empty($quote['manual_only']);
  if($position!==null&&(!$manual_only||($position['source']??null)===$quote['source'])&&(($position['source']??'OANDA Practice')!==$quote['source']||$quote['time']<$position['last_quote'])){
   return AIM_Trading::error('Chưa có báo giá cùng nguồn và đủ mới so với vị thế này.',409);
  }
  $token=hash('sha256',wp_json_encode(array($saved,$quote['source'],$symbol,$quote['time'],$quote['bids'],$quote['asks'],$spec)));
  $quote['open']=$quote['open']??$quote['tradeable'];
  return array('quote'=>$quote,'spec'=>$spec,'saved'=>$saved,'token'=>$token);
 }
}
