<?php
defined('ABSPATH') || exit;
/** Explicit administrator activation, per symbol; never changes source over open exposure. */
final class AIM_Market_Launch {
 static function symbols(){ $v=get_option('aimtr_live_reference_symbols',array());return is_array($v)?array_values(array_intersect($v,array_keys(AIM_Reference_Feed::map()))):array(); }
 static function active(){return count(self::symbols())>0;}
 static function allowed($symbol){return in_array($symbol,self::symbols(),true);}
 static function market($s){return strpos($s,'_')===false?'stock':(in_array($s,array('EUR_USD','GBP_USD','AUD_USD','NZD_USD'),true)?'forex':'commodity');}
 static function enabled($m){foreach(self::symbols() as $s)if(self::market($s)===$m)return true;return false;}
 static function permission(){return AIM_Trading::permission()&&(current_user_can('manage_options')||self::active());}
 static function activate($r){
  $s=$r->get_param('symbol');if(!is_string($s)||!isset(AIM_Reference_Feed::map()[$s]))return AIM_Trading::error('Mã không hỗ trợ.');
  if(defined('AIM_REFERENCE_FEED')&&AIM_REFERENCE_FEED!=='twelvedata')return AIM_Trading::error('Cấu hình VPS đang cố định nguồn khác. Chưa đổi nguồn.');
  $m=self::market($s);$class=$m==='stock'?'AIM_Stock_Accounts':($m==='forex'?'AIM_Forex_Accounts':'AIM_Commodity_Accounts');
  if(!$class::ready())return AIM_Trading::error('Dữ liệu tài khoản chưa sẵn sàng.',503);
  global $wpdb;
  if(!self::active()&&!AIM_Reference_Feed::selected()){
   foreach(array('forex','commodity','stocks') as $table){
    $rows=$wpdb->get_results('SELECT state FROM '.AIM_Trading::table($table),ARRAY_A);
    if($wpdb->last_error)return AIM_Trading::error('Chưa kiểm tra được vị thế trước khi đổi nguồn.',503);
    foreach($rows as $row){$state=json_decode($row['state'],true);if(!is_array($state)||!empty($state['positions']))return AIM_Trading::error('Cần đối soát vị thế đang giữ trước khi đổi nguồn.',409);}
   }
  }
  $q=AIM_Reference_Feed::quote($s);if(is_wp_error($q))return $q;
  if(!$q['open'])return AIM_Trading::error('Phiên đang đóng; kiểm chứng lại khi thị trường mở.',409);
  $symbols=self::symbols();$symbols[]=$s;update_option('aimtr_live_reference_symbols',array_values(array_unique($symbols)),false);
  return array('symbol'=>$s,'market'=>$m,'enabled'=>self::allowed($s),'source'=>$q['source'],'quote'=>$q);
 }
 static function quote($r){
  $s=$r->get_param('symbol');if(!is_string($s)||!self::allowed($s))return AIM_Trading::error('Mã này chưa có nguồn được kích hoạt; chỉ xem biểu đồ.',503);
  $q=AIM_Reference_Feed::quote($s);if(is_wp_error($q))return $q;return array('quote'=>$q,'spec'=>AIM_Reference_Feed::spec($s));
 }
 static function routes(){
  register_rest_route('aim-trading/v1','/market-launch/activate',array('methods'=>'POST','callback'=>array(__CLASS__,'activate'),'permission_callback'=>array('AIM_Trading','admin_permission')));
  register_rest_route('aim-trading/v1','/market-launch/quote',array('methods'=>'GET','callback'=>array(__CLASS__,'quote'),'permission_callback'=>array(__CLASS__,'permission')));
 }
}
add_action('rest_api_init',array('AIM_Market_Launch','routes'));
