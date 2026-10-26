<?php
/**
 * Plugin Name: AIM Trading
 * Description: Sàn thực hành AIM: dữ liệu crypto thị trường, biểu đồ và tài khoản giao dịch thử lưu trên máy chủ.
 * Version: 0.4.25
 * Requires PHP: 7.4
 */
defined('ABSPATH') || exit;
require_once __DIR__.'/spot-execution.php';
require_once __DIR__.'/limit-orders.php';
require_once __DIR__.'/futures.php';
require_once __DIR__.'/workshop.php';
require_once __DIR__.'/wallet-bridge.php';
require_once __DIR__.'/forex-feed.php';
require_once __DIR__.'/forex-diagnostics.php';
require_once __DIR__.'/forex-accounts.php';
require_once __DIR__.'/stock-accounts.php';
require_once __DIR__.'/commodity-accounts.php';
require_once __DIR__.'/market-launch.php';
require_once __DIR__.'/oil-reference.php';
final class AIM_Trading {
 const VERSION = '0.4.25';
 const UNIT = 1000000;
 const START = 100000000;
 const FEE = 0.001;
 static function symbols(){return array('BTCUSDT'=>'Bitcoin','ETHUSDT'=>'Ethereum','BNBUSDT'=>'BNB','SOLUSDT'=>'Solana','XRPUSDT'=>'XRP','ADAUSDT'=>'Cardano');}
 static function table($s){global $wpdb;return $wpdb->prefix.'aimtr_'.$s;}
 static function activate(){
  global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$collate=$wpdb->get_charset_collate();
  dbDelta('CREATE TABLE '.self::table('accounts')." (user_id bigint(20) unsigned NOT NULL, cash bigint(20) NOT NULL DEFAULT 0, positions longtext NOT NULL, updated datetime NOT NULL, PRIMARY KEY  (user_id)) ENGINE=InnoDB $collate;");
  dbDelta('CREATE TABLE '.self::table('ledger')." (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, user_id bigint(20) unsigned NOT NULL, request_id varchar(64) NOT NULL, kind varchar(20) NOT NULL, symbol varchar(20) NOT NULL DEFAULT '', quantity varchar(32) NOT NULL DEFAULT '0', price varchar(32) NOT NULL DEFAULT '0', fee bigint(20) NOT NULL DEFAULT 0, cash_delta bigint(20) NOT NULL, pool_delta bigint(20) NOT NULL, realized bigint(20) NOT NULL DEFAULT 0, cash_after bigint(20) NOT NULL, quoted_at bigint(20) NOT NULL DEFAULT 0, created datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY request_once (user_id,request_id)) ENGINE=InnoDB $collate;");
  $wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.self::table('accounts').' (user_id,cash,positions,updated) VALUES (0,%d,%s,%s)',10000000000000,'{}',current_time('mysql',true)));
  AIM_Limit_Orders::migrate();
  AIM_Spot_Execution::migrate();
  if(!get_page_by_path('aim-trading')){ $id=wp_insert_post(array('post_title'=>'AIM Trading','post_name'=>'aim-trading','post_type'=>'page','post_status'=>'publish','post_content'=>'[aim_trading]'));if(!is_wp_error($id))update_option('aimtr_page',$id,false); }
 }
 // One-time capital issue into the administrator's clearing account, never the player's wallet.
 static function fund_liquidity(){
  if(!current_user_can('manage_options'))return;
  global $wpdb;$a=self::table('accounts');$l=self::table('ledger');
  if($wpdb->query('START TRANSACTION')===false)return;
  try{
   $pool=$wpdb->get_row("SELECT * FROM $a WHERE user_id=0 FOR UPDATE",ARRAY_A);
   if(!$pool||$wpdb->last_error)throw new RuntimeException('Chưa đọc được quỹ thanh khoản.');
   $prior=$wpdb->get_row("SELECT * FROM $l WHERE user_id=0 AND request_id='liquidity-capital-1b-v1'",ARRAY_A);
   if($wpdb->last_error)throw new RuntimeException('Chưa đọc được sổ vốn.');
   if($prior){$wpdb->query('ROLLBACK');return;}
   $target=1000000000*self::UNIT;$delta=max(0,$target-(int)$pool['cash']);$date=current_time('mysql',true);
   if($wpdb->query($wpdb->prepare("UPDATE $a SET cash=cash+(%d),updated=%s WHERE user_id=0",$delta,$date))===false)throw new RuntimeException('Chưa cấp được vốn.');
   if($wpdb->insert($l,array('user_id'=>0,'request_id'=>'liquidity-capital-1b-v1','kind'=>'capital_issue','cash_delta'=>0,'pool_delta'=>$delta,'cash_after'=>(int)$pool['cash']+$delta,'created'=>$date))===false)throw new RuntimeException('Chưa ghi được sổ vốn.');
   if($wpdb->query('COMMIT')===false)throw new RuntimeException('Chưa rõ kết quả cấp vốn.');
  }catch(Throwable $x){$wpdb->query('ROLLBACK');error_log('AIM liquidity: '.$x->getMessage());}
 }
 static function error($msg,$status=400){return new WP_Error('aimtr_error',$msg,array('status'=>$status));}
 static function remote($path,$args=array(),$ttl=3){
  $key='aimtr_'.md5($path.wp_json_encode($args));$cached=get_transient($key);if(is_array($cached))return $cached;
  if(get_transient('aimtr_api_backoff'))return self::error('Nguồn giá đang tạm nghỉ. Chưa thể khớp lệnh.',503);
  $url='https://data-api.binance.vision/api/v3/'.$path;
  if($args)$url.='?'.http_build_query($args,'','&',PHP_QUERY_RFC3986);
  $r=wp_remote_get($url,array('timeout'=>8,'redirection'=>0,'limit_response_size'=>1048576));
  if(is_wp_error($r))return self::error('Chưa kết nối được nguồn giá Binance. Lệnh đang tạm dừng.',503);
  $code=wp_remote_retrieve_response_code($r);
  if(in_array($code,array(418,429),true)){set_transient('aimtr_api_backoff',1,60);return self::error('Nguồn giá giới hạn truy cập. Tự thử lại sau.',503);}
  if($code!==200)return self::error('Nguồn giá chưa sẵn sàng ('.$code.'). Không sử dụng giá thay thế.',503);
  $data=json_decode(wp_remote_retrieve_body($r),true);if(!is_array($data)||isset($data['code']))return self::error('Dữ liệu giá không hợp lệ.',503);
  $result=array('data'=>$data,'received_at'=>(int)round(microtime(true)*1000));set_transient($key,$result,$ttl);return $result;
 }
 static function market(){
  $r=self::remote('ticker/24hr',array('symbols'=>wp_json_encode(array_keys(self::symbols()))),5);if(is_wp_error($r))return $r;
  $rows=array();foreach($r['data'] as $v){if(!isset(self::symbols()[$v['symbol']??'']))continue;
   $rows[]=array('symbol'=>$v['symbol'],'name'=>self::symbols()[$v['symbol']],'price'=>(float)$v['lastPrice'],'change'=>(float)$v['priceChangePercent'],'high'=>(float)$v['highPrice'],'low'=>(float)$v['lowPrice'],'volume'=>(float)$v['quoteVolume'],'close_time'=>(int)$v['closeTime']);}
  if(count($rows)!==count(self::symbols()))return self::error('Bảng giá chưa đầy đủ.',503);
  return array('markets'=>$rows,'received_at'=>$r['received_at'],'server_at'=>(int)round(microtime(true)*1000),'source'=>'Binance Spot','interval_seconds'=>5);
 }
 static function quote($symbol){
  $r=self::remote('depth',array('symbol'=>$symbol,'limit'=>100),1);if(is_wp_error($r))return $r;
  try{$book=AIM_Spot_Execution::book($r['data']);}catch(RuntimeException $e){return self::error($e->getMessage(),503);}
  $at=$r['received_at']??0;$id=$r['data']['lastUpdateId']??0;$age=microtime(true)*1000-$at;
  if(!is_numeric($id)||(float)$id<=0||$age< -1000||$age>5000)return self::error('Báo giá Spot đã cũ hoặc thiếu mã sổ.',503);
  return array('bid'=>$book['bids'][0]['price'],'ask'=>$book['asks'][0]['price'],'bids'=>$r['data']['bids'],'asks'=>$r['data']['asks'],'received_at'=>$at,'update_id'=>$id,'execution_policy'=>'spot-depth-v1');
 }
 static function instrument_open($symbol){
  $r=self::remote('exchangeInfo',array('symbol'=>$symbol),60);if(is_wp_error($r))return $r;
  if(($r['data']['symbols'][0]['status']??'')!=='TRADING')return self::error('Mã này đang ngừng giao dịch tại nguồn. Chưa thể khớp lệnh.',409);
  return true;
 }
 static function public_market_permission(){return true;}
 static function permission(){return get_current_user_id()>0&&current_user_can('read');}
 static function admin_permission(){return current_user_can('manage_options');}
 static function init_account($uid){
  global $wpdb;if($uid<=0)return self::error('Đăng nhập trước khi nhận AIM.',401);$a=self::table('accounts');$l=self::table('ledger');
  if($wpdb->get_var($wpdb->prepare("SELECT user_id FROM $a WHERE user_id=%d",$uid)))return true;
  if($wpdb->query('START TRANSACTION')===false)return self::error('Không mở được giao dịch dữ liệu.',503);
  $pool=$wpdb->get_row("SELECT * FROM $a WHERE user_id=0 FOR UPDATE",ARRAY_A);
  if(!$pool){$wpdb->query('ROLLBACK');return self::error('Quỹ thử nghiệm chưa sẵn sàng.',503);}
  $exists=$wpdb->get_var($wpdb->prepare("SELECT user_id FROM $a WHERE user_id=%d FOR UPDATE",$uid));
  if($exists){$wpdb->query('COMMIT');return true;}
  $grant=class_exists('AIM_Wallet_Automation')&&AIM_Wallet_Automation::wallet_first($uid)?0:self::START;
  if($grant===0&&!AIM_Wallet_Automation::eligible($uid)){$wpdb->query('ROLLBACK');return self::error('Xác minh email và mở ví AIM trước khi vào giao dịch.',403);}
  if((int)$pool['cash']<$grant){$wpdb->query('ROLLBACK');return self::error('Quỹ đang chờ bổ sung vốn thử nghiệm.',503);}
  $ok=$wpdb->insert($a,array('user_id'=>$uid,'cash'=>$grant,'positions'=>'{}','updated'=>current_time('mysql',true)));
  $ok2=$wpdb->query($wpdb->prepare("UPDATE $a SET cash=cash-%d WHERE user_id=0",$grant));
  $ok3=$wpdb->insert($l,array('user_id'=>$uid,'request_id'=>'initial-v1','kind'=>$grant?'credit':'wallet_account','cash_delta'=>$grant,'pool_delta'=>-$grant,'cash_after'=>$grant,'created'=>current_time('mysql',true)));
  if($ok===false||$ok2===false||$ok3===false){$wpdb->query('ROLLBACK');return self::error('Không tạo được tài khoản thử.',500);}
  if($wpdb->query('COMMIT')===false)return self::error('Chưa rõ kết quả cấp vốn. Tải lại để kiểm tra; không cộng vốn lần hai.',503);return true;
 }
 static function account(){
  global $wpdb;$uid=get_current_user_id();$a=self::table('accounts');$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $a WHERE user_id=%d",$uid),ARRAY_A);
  if(!$row)return array('active'=>false,'cash'=>0,'positions'=>(object)array(),'ledger'=>array(),'linked_wallet'=>!empty(get_user_meta($uid,'_aim_privy_wallet',true)));
  $hist=$wpdb->get_results($wpdb->prepare('SELECT id,kind,symbol,quantity,price,fee,cash_delta,realized,cash_after,quoted_at,execution,created FROM '.self::table('ledger').' WHERE user_id=%d ORDER BY id DESC LIMIT 100',$uid),ARRAY_A);
  $realized=(int)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(realized),0) FROM '.self::table('ledger').' WHERE user_id=%d',$uid));
  return array_merge(AIM_Limit_Orders::summary($uid,(int)$row['cash']),array('active'=>true,'cash'=>(int)$row['cash'],'positions'=>json_decode($row['positions'])?:new stdClass(),'ledger'=>$hist,'realized_total'=>$realized,'linked_wallet'=>!empty(get_user_meta($uid,'_aim_privy_wallet',true))));
 }
 static function trade($request,$pending=null,&$depth_batch=null){
  global $wpdb;$uid=$pending?(int)$pending['user_id']:get_current_user_id();$p=$request->get_json_params();
  $symbol=is_string($p['symbol']??null)?$p['symbol']:'';$side=$p['side']??'';$req=$p['request_id']??'';$raw=$p['quantity']??'';
  if(!isset(self::symbols()[$symbol])||!in_array($side,array('buy','sell'),true)||!is_string($req)||!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$req)||!is_string($raw)||!preg_match('/^\d{1,8}(\.\d{1,8})?$/D',$raw))return self::error('Thông tin lệnh không hợp lệ.');
  if(!$pending&&strpos($req,'limit-fill-')===0)return self::error('Mã yêu cầu dành riêng cho hệ thống.');
  $units=(int)round((float)$raw*100000000);$qty=$units/100000000;
  if($units<=0||$qty>1000000)return self::error('Khối lượng cần lớn hơn 0, tối đa 8 số lẻ.');
  $l=self::table('ledger');$a=self::table('accounts');
  $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $l WHERE user_id=%d AND request_id=%s",$uid,$req),ARRAY_A);
  if($wpdb->last_error)return self::error('Chưa tra được kết quả lệnh.',503);
  if($existing)return self::replay_trade($existing,$symbol,$side,$units,$pending);
  if($pending&&AIM_Limit_Orders::due($pending)){$e=AIM_Limit_Orders::expire_one((int)$pending['id'],$uid);return is_wp_error($e)?$e:self::error('Lệnh đã hết hạn.',409);}
  $open=self::instrument_open($symbol);if(is_wp_error($open))return $open;
  $q=self::quote($symbol);if(is_wp_error($q))return $q;
  $best=$side==='buy'?$q['ask']:$q['bid'];
  if($wpdb->query('START TRANSACTION')===false)return self::error('Không mở được giao dịch dữ liệu.',503);
  $pool=$wpdb->get_row("SELECT * FROM $a WHERE user_id=0 FOR UPDATE",ARRAY_A);
  $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $a WHERE user_id=%d FOR UPDATE",$uid),ARRAY_A);
  $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $l WHERE user_id=%d AND request_id=%s",$uid,$req),ARRAY_A);
  if($wpdb->last_error){$wpdb->query('ROLLBACK');return self::error('Chưa tra được kết quả lệnh.',503);}
  if($existing){$wpdb->query('ROLLBACK');return self::replay_trade($existing,$symbol,$side,$units,$pending);}
  if(!$row||!$pool){$wpdb->query('ROLLBACK');return self::error('Hãy khởi tạo tài khoản thử trước.',409);}
  if(microtime(true)*1000-$q['received_at']>5000){$wpdb->query('ROLLBACK');return self::error('Báo giá đã cũ. Vui lòng thử lại.',409);}
  if($pending){
   $locked=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('orders').' WHERE id=%d FOR UPDATE',$pending['id']),ARRAY_A);
   if(!$locked||$locked['status']!=='pending'){$wpdb->query('ROLLBACK');return self::error('Lệnh không còn chờ.',409);}
   if(AIM_Limit_Orders::due($locked)){if($wpdb->update(self::table('orders'),array('status'=>'expired','updated'=>current_time('mysql',true)),array('id'=>$pending['id'],'status'=>'pending'))!==1){$wpdb->query('ROLLBACK');return self::error('Chưa ghi nhận hết hạn.',503);}if($wpdb->query('COMMIT')===false)return self::error('Chưa rõ kết quả hết hạn.',503);return self::error('Lệnh đã hết hạn.',409);}
  }
  $trigger=$pending?AIM_Limit_Orders::trigger($locked,$best):'';
  if($pending&&!$trigger){$wpdb->query('ROLLBACK');return self::error('Chưa chạm giá giới hạn.',409);}
  $bookkey=$uid.':'.$symbol.':'.$q['update_id'];
  $book=is_array($depth_batch)&&isset($depth_batch[$bookkey])?$depth_batch[$bookkey]:AIM_Spot_Execution::book($q);
  try{$execution=AIM_Spot_Execution::fill($book,$side==='buy',$units,$pending&&$locked['order_type']==='limit'?(float)$locked['limit_price']:0);}
  catch(AIM_Spot_Liquidity $e){
   if($pending&&$locked['order_type']==='tpsl'&&!$locked['trigger_reason']){
    if($wpdb->update(self::table('orders'),array('trigger_reason'=>$trigger,'updated'=>current_time('mysql',true)),array('id'=>$locked['id'],'status'=>'pending'))!==1){$wpdb->query('ROLLBACK');return self::error('Chưa lưu kích hoạt TP/SL.',503);}
    if($wpdb->query('COMMIT')===false)return self::error('Chưa xác nhận kích hoạt TP/SL.',503);
   }else $wpdb->query('ROLLBACK');
   return self::error($e->getMessage(),409);
  }
  $price=$execution['price'];$execution['book_id']=$q['update_id'];$execution['received_at']=$q['received_at'];
  $grossFloat=$qty*$price*self::UNIT;
  if(!is_finite($grossFloat)||$grossFloat>9000000000000000){$wpdb->query('ROLLBACK');return self::error('Giá trị khớp vượt giới hạn tính toán.',409);}
  $gross=(int)round($grossFloat);
  if($gross<=0||(!$pending&&($gross<self::UNIT||$gross>100000*self::UNIT))){$wpdb->query('ROLLBACK');return self::error('Giá trị lệnh từ 1 đến 100.000 AIM thử.');}
  $fee=(int)ceil($gross/1000);$delta=$side==='buy'?-$gross-$fee:$gross-$fee;
  if($side==='buy'&&!AIM_Workshop::allow_open('spot',true)){$wpdb->query('ROLLBACK');return self::error('Vận hành đang tạm dừng mua mới. Vẫn có thể bán và hủy lệnh.',409);}
  $reserved=AIM_Limit_Orders::reserved($uid,$pending?(int)$pending['id']:0);
  if((int)$row['cash']-$reserved['cash']+$delta<0||(int)$pool['cash']-$delta<0){$wpdb->query('ROLLBACK');return self::error('Số dư khả dụng không đủ cho lệnh và phí.',409);}
  $positions=json_decode($row['positions'],true)?:array();$pos=$positions[$symbol]??array('units'=>0,'cost'=>0);$realized=0;
  if($side==='buy'){$pos['units']+=$units;$pos['cost']+=$gross+$fee;}
  else{if($pos['units']-($reserved['units'][$symbol]??0)<$units){$wpdb->query('ROLLBACK');return self::error('Không đủ tài sản để bán. Bản giao ngay chưa hỗ trợ Short.',409);}
   $basis=$units===$pos['units']?$pos['cost']:(int)round($pos['cost']*$units/$pos['units']);$realized=$gross-$fee-$basis;$pos['units']-=$units;$pos['cost']-=$basis;}
  if($pos['units']>0)$positions[$symbol]=$pos;else unset($positions[$symbol]);
  $after=(int)$row['cash']+$delta;$date=current_time('mysql',true);
  $ok1=$wpdb->update($a,array('cash'=>$after,'positions'=>wp_json_encode((object)$positions),'updated'=>$date),array('user_id'=>$uid));
  $ok2=$wpdb->query($wpdb->prepare("UPDATE $a SET cash=cash+(%d),updated=%s WHERE user_id=0",-$delta,$date));
  $ok3=$wpdb->insert($l,array('user_id'=>$uid,'request_id'=>$req,'kind'=>$side,'symbol'=>$symbol,'quantity'=>$raw,'price'=>(string)$price,'fee'=>$fee,'cash_delta'=>$delta,'pool_delta'=>-$delta,'realized'=>$realized,'cash_after'=>$after,'quoted_at'=>$q['received_at'],'execution'=>wp_json_encode($execution),'created'=>$date));
  $id=$wpdb->insert_id;
  $ok4=true;
  if($pending)$ok4=$wpdb->update(self::table('orders'),array('status'=>'filled','trigger_reason'=>$trigger,'ledger_id'=>$id,'updated'=>$date),array('id'=>$pending['id'],'status'=>'pending'));
  if($ok4===false||$ok4===0||$ok1===false||$ok2===false||$ok3===false){$wpdb->query('ROLLBACK');return self::error('Lệnh chưa được ghi nhận. Vui lòng kiểm tra lịch sử rồi thử lại.',500);}
  if($wpdb->query('COMMIT')===false)return self::error('Chưa xác nhận được kết quả. Kiểm tra lịch sử trước khi đặt lại.',500);
  if(is_array($depth_batch))$depth_batch[$bookkey]=$book;
  return array('order_id'=>$id,'price'=>$price,'fee'=>$fee,'execution'=>$execution,'account'=>$pending?null:self::account());
 }
 static function replay_trade($existing,$symbol,$side,$units,$pending=null){
  if($existing['symbol']!==$symbol||$existing['kind']!==$side||(int)round((float)$existing['quantity']*100000000)!==$units)return self::error('Mã yêu cầu đã dùng cho nội dung khác.',409);
  return array('order_id'=>(int)$existing['id'],'replayed'=>true,'price'=>(float)$existing['price'],'fee'=>(int)$existing['fee'],'execution'=>json_decode($existing['execution']??'null',true),'account'=>$pending?null:self::account());
 }
 static function request_status($request){
  global $wpdb;$uid=get_current_user_id();$id=$request->get_param('request_id');$kind=$request->get_param('kind');
  if(!is_string($id)||!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$id)||!in_array($kind,array('market','limit'),true))return self::error('Mã tra cứu không hợp lệ.');
  $table=self::table($kind==='market'?'ledger':'orders');
  $o=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id=%d AND request_id=%s",$uid,$id),ARRAY_A);
  if($wpdb->last_error)return self::error('Chưa tra cứu được. Yêu cầu được giữ để kiểm tra lại.',503);
  if(!$o)return array('found'=>false,'request_id'=>$id);
  return array('found'=>true,'request_id'=>$id,'order_id'=>(int)$o['id'],'status'=>$kind==='market'?'filled':$o['status'],'symbol'=>$o['symbol'],'side'=>$kind==='market'?$o['kind']:$o['side'],'quantity'=>$o['quantity'],'account'=>self::account());
 }
 static function routes(){
  AIM_Limit_Orders::routes();
  register_rest_route('aim-trading/v1','/markets/catalog',array('methods'=>'GET','callback'=>function(){return AIM_Markets_Engine::public_catalog();},'permission_callback'=>array(__CLASS__,'permission')));
  $public=array(__CLASS__,'public_market_permission');$private=array(__CLASS__,'permission');
  register_rest_route('aim-trading/v1','/market',array('methods'=>'GET','callback'=>array(__CLASS__,'market'),'permission_callback'=>$public));
  register_rest_route('aim-trading/v1','/chart',array('methods'=>'GET','permission_callback'=>$public,'callback'=>function($r){$s=$r->get_param('symbol');$i=$r->get_param('interval');if(!isset(self::symbols()[$s])||!in_array($i,array('1m','15m','1h','4h','1d'),true))return self::error('Mã hoặc khung thời gian không hợp lệ.');$x=self::remote('klines',array('symbol'=>$s,'interval'=>$i,'limit'=>120),15);return is_wp_error($x)?$x:array('candles'=>$x['data'],'received_at'=>$x['received_at']);}));
  register_rest_route('aim-trading/v1','/quote',array('methods'=>'GET','permission_callback'=>$public,'callback'=>function($r){$s=$r->get_param('symbol');if(!isset(self::symbols()[$s]))return self::error('Mã không hợp lệ.');return self::quote($s);}));
  register_rest_route('aim-trading/v1','/request-status',array('methods'=>'GET','permission_callback'=>$private,'callback'=>array(__CLASS__,'request_status')));
  register_rest_route('aim-trading/v1','/account',array('methods'=>'GET','permission_callback'=>$private,'callback'=>array(__CLASS__,'account')));
  register_rest_route('aim-trading/v1','/start',array('methods'=>'POST','permission_callback'=>$private,'callback'=>function(){ $v=self::init_account(get_current_user_id());return is_wp_error($v)?$v:self::account();}));
  register_rest_route('aim-trading/v1','/trade',array('methods'=>'POST','permission_callback'=>$private,'callback'=>array(__CLASS__,'trade')));
 }
 static function page(){
  if(!is_page('aim-trading'))return;
  require_once __DIR__.'/i18n.php';
  ob_start('aimtr_language_output');
  nocache_headers();header('X-Robots-Tag: noindex, nofollow');
  $config=array('is_admin'=>current_user_can('manage_options'),'user_id'=>get_current_user_id(),'api'=>esc_url_raw(rest_url('aim-trading/v1/')),'nonce'=>wp_create_nonce('wp_rest'),'logged'=>is_user_logged_in(),'name'=>is_user_logged_in()?wp_get_current_user()->display_name:'Khách','login'=>home_url('/tai-khoan/'),'home'=>home_url('/'),'wallet'=>home_url('/tai-khoan/'),'version'=>self::VERSION);
  $config['lang']=aimtr_page_language();
  $config['wallet_first']=class_exists('AIM_Wallet_Automation')&&AIM_Wallet_Automation::wallet_first(get_current_user_id());
  $mode=isset($_GET['mode'])&&is_string($_GET['mode'])?$_GET['mode']:'';
  if(current_user_can('manage_options')&&isset($_GET['preview'])&&$_GET['preview']==='guest'){$config['logged']=false;$config['is_admin']=false;$config['user_id']=0;$config['nonce']='';$config['name']='Khách';}
  if($mode==='funding'||(!self::permission()&&$mode==='workshop')){include __DIR__.'/funding-terminal.php';exit;}
  if(in_array($mode,array('stock','forex','commodity'),true)){$config['market']=$mode;include __DIR__.'/exchange-terminal.php';exit;}
  if($mode==='workshop'&&isset($_GET['market'])&&is_string($_GET['market'])&&in_array($_GET['market'],array('stock','forex','commodity'),true)){$config['market']=$_GET['market'];include __DIR__.'/markets-terminal.php';exit;}
  if(isset($_GET['mode'])&&$_GET['mode']==='workshop'){include __DIR__.'/workshop-terminal.php';exit;}
  if(isset($_GET['mode'])&&$_GET['mode']==='futures')include __DIR__.'/futures-terminal.php';else include __DIR__.'/terminal.php';exit;
 }
 static function admin(){
  global $wpdb;if(!current_user_can('manage_options'))return;$a=self::table('accounts');$l=self::table('ledger');
  echo '<div class="wrap"><h1>AIM Trading — bản thử 0.1</h1><p><a class="button button-primary" href="'.esc_url(home_url('/aim-trading/')).'">Mở sàn thực hành</a></p><p>Crypto giao ngay · Market / Limit · Phí 0,1% · Dữ liệu Binance Spot. Có cầu nối nạp/rút ví AIM testnet; dự trữ kho và ký trả cần kiểm tra riêng.</p>';
  echo '<p>Quỹ thanh khoản admin: <strong>'.esc_html(number_format_i18n((float)$wpdb->get_var("SELECT cash FROM $a WHERE user_id=0")/self::UNIT,2)).' AIM</strong>. Vốn cấp ban đầu: 100 AIM / tài khoản. Giá trị tham chiếu: 1 đơn vị USDT trong báo giá = 1 AIM thử; không phải cam kết quy đổi.</p>';
  echo '<p>Không có chức năng sửa giá hoặc kết quả. Lệnh lấy bid/ask tại máy chủ và dừng khi nguồn lỗi. Limit giữ chỗ AIM/tài sản; chỉ nhận lệnh mới khi bộ xử lý nền hoạt động. Các thị trường khác đang phát triển.</p><h2>50 nghiệp vụ gần nhất</h2><table class="widefat striped"><tr><th>Mã</th><th>User</th><th>Loại</th><th>Tài sản</th><th>Thu/chi AIM</th><th>Đối ứng quỹ</th><th>UTC</th></tr>';
  foreach($wpdb->get_results("SELECT * FROM $l ORDER BY id DESC LIMIT 50",ARRAY_A) as $r){echo '<tr>';foreach(array($r['id'],$r['user_id'],$r['kind'],$r['symbol'],number_format_i18n($r['cash_delta']/self::UNIT,6),number_format_i18n($r['pool_delta']/self::UNIT,6),$r['created']) as $v)echo '<td>'.esc_html($v).'</td>';echo '</tr>';}
  echo '</table>';AIM_Futures::admin();echo '</div>';
 }
}
register_activation_hook(__FILE__,array('AIM_Trading','activate'));
add_action('rest_api_init',array('AIM_Trading','routes'));
add_action('template_redirect',array('AIM_Trading','page'));
add_action('admin_menu',function(){add_menu_page('AIM Trading','AIM Trading','manage_options','aim-trading',array('AIM_Trading','admin'),'dashicons-chart-line',59);});

add_action('admin_init',array('AIM_Limit_Orders','migrate'));
add_action('admin_init',array('AIM_Trading','fund_liquidity'),30);

// Retire the old simulator entry without deleting its recoverable content.
add_action('template_redirect',function(){
 if(is_page('danh-muc-mo-phong')&&!is_preview()){
  wp_safe_redirect(home_url('/aim-trading/'),302,'AIM Trading');exit;
 }
},1);

// A visible return path after the website's existing registration/verification flow.
add_filter('the_content',function($content){
 if(!is_page('tai-khoan')||!in_the_loop()||!is_main_query())return $content;
 $url=home_url('/aim-trading/?mode=funding');
 $notice='Xác minh email và mở ví AIM. Nạp AIM từ ví vào mô phỏng để giao dịch; rút số dư khả dụng về cùng ví.';
 return '<aside class="aimtr-account-entry" style="padding:16px;margin:0 0 20px;border:1px solid #17b890;border-radius:8px"><strong>AIM Trading · Tập giao dịch miễn phí</strong><p>'.esc_html($notice).'</p><a href="'.esc_url($url).'">'.(is_user_logged_in()?'Lấy AIM tập giao dịch →':'Quay lại AIM Trading →').'</a></aside>'.$content;
},20);

require_once __DIR__.'/wallet-automation.php';
require_once __DIR__.'/wallet-automation-admin.php';

require_once __DIR__.'/article-rewards.php';
