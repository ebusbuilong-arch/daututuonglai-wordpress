<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/stock-feed.php';
final class AIM_Stock_Accounts {
 static function enabled(){return (defined('AIM_STOCK_TRADING_ENABLED')&&AIM_STOCK_TRADING_ENABLED===true)||(class_exists('AIM_Market_Launch')&&AIM_Market_Launch::enabled('stock'));}
 static function allowed($symbol=''){if(isset(AIM_VN_Stock_Feed::map()[$symbol]))return AIM_VN_Stock_Feed::configured();return self::enabled()&&(current_user_can('manage_options')||(class_exists('AIM_Market_Launch')&&AIM_Market_Launch::enabled('stock'))||(defined('AIM_STOCK_PUBLIC_DATA_ALLOWED')&&AIM_STOCK_PUBLIC_DATA_ALLOWED===true));}
 static function ready(){return get_option('aimtr_stock_schema')==='1';}
 static function migrate(){
  if(self::ready())return;global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$c=$wpdb->get_charset_collate();$t=AIM_Trading::table('stocks');$e=AIM_Trading::table('stock_events');
  dbDelta("CREATE TABLE $t (user_id bigint unsigned NOT NULL, state longtext NOT NULL, revision bigint unsigned NOT NULL DEFAULT 0, updated datetime NOT NULL, PRIMARY KEY  (user_id)) ENGINE=InnoDB $c;");
  dbDelta("CREATE TABLE $e (id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, request_id varchar(64) NOT NULL, payload_hash varchar(64) NOT NULL, event longtext NOT NULL, created datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY request_once (user_id,request_id)) ENGINE=InnoDB $c;");
  foreach(array($t,$e,AIM_Trading::table('accounts'),AIM_Trading::table('ledger')) as $table)if(strtoupper((string)$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table)))!=='INNODB')return;
  update_option('aimtr_stock_schema','1',false);
 }
 static function account(){
  $out=array('ready'=>self::ready(),'configured'=>(bool)(AIM_Stock_Feed::configured()||AIM_VN_Stock_Feed::configured()),'enabled'=>(self::allowed()||AIM_VN_Stock_Feed::configured()),'vn_configured'=>AIM_VN_Stock_Feed::configured(),'vnd_per_aim'=>AIM_Stock_Engine::VND_PER_AIM,'source'=>AIM_Reference_Feed::selected()?AIM_Reference_Feed::SOURCE:'Alpaca '.strtoupper(AIM_Stock_Feed::feed()),'usd_per_aim'=>1000,'fee_rate'=>0.001,'state'=>AIM_Stock_Engine::blank(),'history'=>array());
  if(!self::ready())return $out;global $wpdb;$uid=get_current_user_id();
  $r=$wpdb->get_row($wpdb->prepare('SELECT state FROM '.AIM_Trading::table('stocks').' WHERE user_id=%d',$uid),ARRAY_A);if($wpdb->last_error)return AIM_Trading::error('Chưa đọc được danh mục cổ phiếu.',503);
  if($r){$s=json_decode($r['state'],true);if(!is_array($s)||($s['policy']??'')!==AIM_Stock_Engine::POLICY)return AIM_Trading::error('Danh mục cần đối soát.',503);$out['state']=$s;}
  $h=$wpdb->get_results($wpdb->prepare('SELECT event,created FROM '.AIM_Trading::table('stock_events').' WHERE user_id=%d ORDER BY id DESC LIMIT 50',$uid),ARRAY_A);if($wpdb->last_error)return AIM_Trading::error('Chưa đọc được lịch sử cổ phiếu.',503);
  foreach($h as $r)$out['history'][]=array('event'=>json_decode($r['event'],true),'created'=>$r['created']);return $out;
 }
 static function data_permission(){return AIM_Trading::permission()&&(AIM_VN_Stock_Feed::configured()||current_user_can('manage_options')||(class_exists('AIM_Market_Launch')&&AIM_Market_Launch::enabled('stock'))||(defined('AIM_STOCK_PUBLIC_DATA_ALLOWED')&&AIM_STOCK_PUBLIC_DATA_ALLOWED===true));}
 static function quote($r){$symbol=(string)$r->get_param('symbol');if(isset(AIM_VN_Stock_Feed::map()[$symbol]))return AIM_VN_Stock_Feed::quote($symbol);if(class_exists('AIM_Market_Launch')&&AIM_Market_Launch::active()&&!AIM_Market_Launch::allowed((string)$r->get_param('symbol')))return AIM_Trading::error('Mã này chưa được kích hoạt nguồn giá.',503);return AIM_Stock_Feed::quote((string)$r->get_param('symbol'));}
 static function fail($msg,$code=409){global $wpdb;$wpdb->query('ROLLBACK');return AIM_Trading::error($msg,$code);}
 static function replay($r,$hash){return hash_equals($r['payload_hash'],$hash)?array('replayed'=>true,'event'=>json_decode($r['event'],true)):AIM_Trading::error('Mã yêu cầu đã dùng cho lệnh khác.',409);}
 static function order($r){
  global $wpdb;$p=$r->get_json_params();$uid=get_current_user_id();if($uid<=0)return AIM_Trading::error('Cần đăng nhập để đặt lệnh.',401);
  if(!is_array($p)||array_diff(array_keys($p),array('request_id','symbol','side','quantity','bound','order_type'))||!is_string($p['request_id']??null)||!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$p['request_id']))return AIM_Trading::error('Yêu cầu đặt lệnh không hợp lệ.');
  if(!in_array($p['order_type']??'',array('market','limit_ioc'),true)||!in_array($p['symbol']??null,AIM_Stock_Feed::symbols(),true))return AIM_Trading::error('Mã hoặc loại lệnh chưa hỗ trợ.');
  if(!self::ready())return AIM_Trading::error('Chưa khởi tạo dữ liệu cổ phiếu.',503);
  $a=AIM_Trading::table('accounts');$t=AIM_Trading::table('stocks');$e=AIM_Trading::table('stock_events');$hash=hash('sha256',wp_json_encode($p));$req=$p['request_id'];
  $prior=$wpdb->get_row($wpdb->prepare("SELECT * FROM $e WHERE user_id=%d AND request_id=%s",$uid,$req),ARRAY_A);if($wpdb->last_error)return AIM_Trading::error('Chưa đối chiếu được lệnh.',503);if($prior)return self::replay($prior,$hash);
  if(!self::allowed($p['symbol']))return AIM_Trading::error('Chưa bật giao dịch cổ phiếu cho tài khoản này.',503);
  if(!isset(AIM_VN_Stock_Feed::map()[$p['symbol']])&&class_exists('AIM_Market_Launch')&&AIM_Market_Launch::active()&&!AIM_Market_Launch::allowed($p['symbol']))return AIM_Trading::error('Mã này chưa được kích hoạt nguồn giá.',503);
  $q=AIM_Stock_Feed::quote($p['symbol']);if(is_wp_error($q))return $q;
  if($wpdb->query('START TRANSACTION')===false)return AIM_Trading::error('Chưa mở được giao dịch dữ liệu.',503);
  $pool=$wpdb->get_row("SELECT * FROM $a WHERE user_id=0 FOR UPDATE",ARRAY_A);if($wpdb->last_error)return self::fail('Chưa khóa được quỹ.',503);
  $spot=$wpdb->get_row($wpdb->prepare("SELECT * FROM $a WHERE user_id=%d FOR UPDATE",$uid),ARRAY_A);if($wpdb->last_error)return self::fail('Chưa khóa được tài khoản.',503);
  $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d FOR UPDATE",$uid),ARRAY_A);if($wpdb->last_error)return self::fail('Chưa khóa được danh mục.',503);
  $prior=$wpdb->get_row($wpdb->prepare("SELECT * FROM $e WHERE user_id=%d AND request_id=%s",$uid,$req),ARRAY_A);if($wpdb->last_error)return self::fail('Chưa đối chiếu được lệnh.',503);if($prior){$wpdb->query('ROLLBACK');return self::replay($prior,$hash);}
  if(!$spot||!$pool)return self::fail('Cần kích hoạt tài khoản giao ngay và quỹ thanh khoản.');
  $reserved=AIM_Limit_Orders::reserved($uid);if(is_wp_error($reserved))return self::fail('Chưa đọc được AIM đang giữ cho lệnh Crypto.',503);
  try{
   $s=$row?json_decode($row['state'],true):AIM_Stock_Engine::blank();
   $fill=AIM_Stock_Engine::fill($s,(int)$spot['cash']-$reserved['cash'],$p['symbol'],$p['side']??null,$p['quantity']??null,$q,(int)round(microtime(true)*1000),$p['bound']??null);
   AIM_Stock_Engine::need((int)$pool['cash']+$fill['pool_delta']>=0,'Quỹ đối ứng chưa đủ AIM thanh toán.');
   $ev=$fill['event'];$ev['order_type']=$p['order_type'];$ev['request_id']=$req;$ev['cash_after']=(int)$spot['cash']+$fill['cash_delta'];
  }catch(Throwable $x){return self::fail($x->getMessage());}
  $date=current_time('mysql',true);$data=array('state'=>wp_json_encode($fill['state']),'revision'=>(int)($row['revision']??0)+1,'updated'=>$date);
  if(($row?$wpdb->update($t,$data,array('user_id'=>$uid)):$wpdb->insert($t,array_merge(array('user_id'=>$uid),$data)))===false)return self::fail('Chưa lưu được danh mục.',503);
  foreach(array(0=>$fill['pool_delta'],$uid=>$fill['cash_delta']) as $id=>$delta)if($wpdb->query($wpdb->prepare("UPDATE $a SET cash=cash+(%d),updated=%s WHERE user_id=%d",$delta,$date,$id))!==1)return self::fail('Chưa hạch toán được AIM.',503);
  if($wpdb->insert($e,array('user_id'=>$uid,'request_id'=>$req,'payload_hash'=>$hash,'event'=>wp_json_encode($ev),'created'=>$date))===false)return self::fail('Chưa lưu được kết quả lệnh.',503);
  if($wpdb->insert(AIM_Trading::table('ledger'),array('user_id'=>$uid,'request_id'=>'stock-'.substr(hash('sha256',$req),0,58),'kind'=>'stock_'.$p['side'],'symbol'=>$p['symbol'],'quantity'=>(string)$p['quantity'],'price'=>(string)$ev['price'],'fee'=>$ev['fee'],'cash_delta'=>$fill['cash_delta'],'pool_delta'=>$fill['pool_delta'],'realized'=>$ev['realized'],'cash_after'=>$ev['cash_after'],'quoted_at'=>$q['time'],'created'=>$date))===false)return self::fail('Chưa ghi được sổ giao dịch.',503);
  if($wpdb->query('COMMIT')===false)return AIM_Trading::error('Chưa rõ kết quả. Gửi lại đúng mã yêu cầu để đối chiếu.',503);
  return array('event'=>$ev,'replayed'=>false);
 }
 static function valuation($r){
  $symbol=strtoupper((string)$r->get_param('symbol'));
  $map=AIM_VN_Stock_Feed::map();
  if(!isset($map[$symbol]))return self::quote($r);
  if(!AIM_VN_Stock_Feed::configured())return AIM_Trading::error('Chưa kết nối nguồn SSI.',503);
  // Valuation is read-only and may use the last completed session.
  // Never pass this historical reference price into order execution.
  $request=new WP_REST_Request('GET');
  $request->set_param('symbol',$symbol);$request->set_param('board',$map[$symbol]);$request->set_param('frame','1d');
  $response=DTTL_SSI_Charts::history_route($request);
  if(is_wp_error($response))return $response;
  $data=$response->get_data();$bars=$data['bars']??array();$bar=$bars?end($bars):null;
  if(!$bar||!is_numeric($bar['close'])||$bar['close']<=0)return AIM_Trading::error('Chưa có giá SSI cho mã này.',503);
  $time=(int)$bar['time']*1000;
  if(!empty($data['provisionalAt'])){$dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$data['provisionalAt'],new DateTimeZone('Asia/Ho_Chi_Minh'));if($dt)$time=$dt->getTimestamp()*1000;}
  return array('symbol'=>$symbol,'bid'=>round($bar['close']*0.9995,2),'time'=>$time,'source'=>'SSI','valuation_only'=>true);
 }
 static function routes(){
  register_rest_route('aim-trading/v1','/stocks/valuation',array('methods'=>'GET','callback'=>array(__CLASS__,'valuation'),'permission_callback'=>array(__CLASS__,'data_permission')));
  register_rest_route('aim-trading/v1','/stocks/account',array('methods'=>'GET','callback'=>array(__CLASS__,'account'),'permission_callback'=>array('AIM_Trading','permission')));
  register_rest_route('aim-trading/v1','/stocks/quote',array('methods'=>'GET','callback'=>array(__CLASS__,'quote'),'permission_callback'=>array(__CLASS__,'data_permission')));
  register_rest_route('aim-trading/v1','/stocks/order',array('methods'=>'POST','callback'=>array(__CLASS__,'order'),'permission_callback'=>array('AIM_Trading','permission')));
 }
}
add_action('admin_init',array('AIM_Stock_Accounts','migrate'));
add_action('rest_api_init',array('AIM_Stock_Accounts','routes'));
