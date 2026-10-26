<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/futures-engine.php';
final class AIM_Futures {
 static function symbols(){return array('BTCUSDT','ETHUSDT');}
 static function migrate(){
  if(get_option('aimtr_futures_schema')==='1')return;
  global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$c=$wpdb->get_charset_collate();
  $t=AIM_Trading::table('futures');$e=AIM_Trading::table('futures_events');
  dbDelta("CREATE TABLE $t (user_id bigint unsigned NOT NULL, state longtext NOT NULL, revision bigint unsigned NOT NULL DEFAULT 0, updated datetime NOT NULL, PRIMARY KEY  (user_id)) ENGINE=InnoDB $c;");
  dbDelta("CREATE TABLE $e (id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, request_id varchar(64) NOT NULL, payload_hash varchar(64) NOT NULL, events longtext NOT NULL, created datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY request_once (user_id,request_id)) ENGINE=InnoDB $c;");
  foreach(array($t,$e) as $table)if(strtoupper((string)$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table)))!=='INNODB')return;
  update_option('aimtr_futures_schema','1',false);
 }
 static function ready(){return get_option('aimtr_futures_schema')==='1';}
 static function remote($path,$params,$ttl=2){
  $key='aimtr_fut_'.md5($path.wp_json_encode($params));$cache=get_transient($key);if(is_array($cache))return $cache;
  if(get_transient('aimtr_fut_backoff'))return AIM_Trading::error('Nguồn Futures đang giới hạn truy cập.',503);
  $r=wp_remote_get('https://fapi.binance.com/fapi/v1/'.$path.'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986),array('timeout'=>3,'redirection'=>0,'limit_response_size'=>1048576));
  if(is_wp_error($r))return AIM_Trading::error('Không kết nối được Binance Futures. Không dùng giá Spot thay thế.',503);
  $code=wp_remote_retrieve_response_code($r);if(in_array($code,array(418,429),true))set_transient('aimtr_fut_backoff',1,60);
  if($code!==200)return AIM_Trading::error('Nguồn Futures chưa sẵn sàng ('.$code.').',503);
  $d=json_decode(wp_remote_retrieve_body($r),true);if(!is_array($d)||isset($d['code']))return AIM_Trading::error('Dữ liệu Futures lỗi.',503);
  $out=array('data'=>$d,'received'=>(int)round(microtime(true)*1000));set_transient($key,$out,$ttl);return $out;
 }
 static function quote($symbol){
  if(!in_array($symbol,self::symbols(),true))return AIM_Trading::error('Mã Futures chưa hỗ trợ.');
  $m=self::remote('premiumIndex',array('symbol'=>$symbol));if(is_wp_error($m))return $m;
  $b=self::remote('depth',array('symbol'=>$symbol,'limit'=>100));if(is_wp_error($b))return $b;
  $x=$m['data'];$d=$b['data'];$now=(int)round(microtime(true)*1000);
  $q=array('symbol'=>$symbol,'mark'=>(float)($x['markPrice']??0),'index'=>(float)($x['indexPrice']??0),'bid'=>(float)($d['bids'][0][0]??0),'ask'=>(float)($d['asks'][0][0]??0),'time'=>(int)($x['time']??0),'next_funding'=>(int)($x['nextFundingTime']??0),'rate'=>(float)($x['lastFundingRate']??0),'received'=>min($m['received'],$b['received']));
  foreach(array('mark','index','bid','ask') as $k)if(!is_finite($q[$k])||$q[$k]<=0||$q[$k]>1e7)return AIM_Trading::error('Giá Futures không hợp lệ.',503);
  if(($x['symbol']??'')!==$symbol||$q['ask']<$q['bid']||abs($now-$q['time'])>10000||$now-$q['received']>5000||$q['next_funding']<=0||!is_finite($q['rate'])||abs($q['rate'])>1)return AIM_Trading::error('Giá Futures đã cũ hoặc thiếu thông tin.',503);
  $q['bids']=$d['bids']??array();$q['asks']=$d['asks']??array();$q['book_time']=(int)($d['T']??0);$q['book_id']=(int)($d['lastUpdateId']??0);
  if($q['book_id']<=0||abs($now-$q['book_time'])>10000)return AIM_Trading::error('Sổ lệnh Futures đã cũ hoặc thiếu mốc thời gian.',503);
  try{AIM_Futures_Engine::book($q);}catch(Throwable $e){return AIM_Trading::error($e->getMessage(),503);}
  return $q;
 }
 static function snapshot($state,$extra=''){
  $symbols=array();foreach($state['positions'] as $p)$symbols[$p['symbol']]=true;foreach($state['orders']??array() as $o)$symbols[$o['symbol']]=true;if($extra)$symbols[$extra]=true;
  $quotes=array();$funding=array();
  foreach(array_keys($symbols) as $symbol){
   $q=self::quote($symbol);if(is_wp_error($q))return $q;$quotes[$symbol]=$q;$start=null;$due=false;
   foreach($state['positions'] as $p)if($p['symbol']===$symbol){$start=$start===null?$p['cursor']:min($start,$p['cursor']);if($p['next_funding']<=$q['time'])$due=true;}
   $funding[$symbol]=array();if(!$due)continue;
   $f=self::remote('fundingRate',array('symbol'=>$symbol,'startTime'=>$start+1,'endTime'=>$q['time'],'limit'=>1000),2);if(is_wp_error($f))return $f;
   if(count($f['data'])>=1000)return AIM_Trading::error('Lịch sử funding cần đối soát bổ sung; tạm dừng thay đổi vị thế.',503);
   $last=$start;foreach($f['data'] as $v){$at=(int)($v['fundingTime']??0);$mark=(float)($v['markPrice']??0);$rate=(float)($v['fundingRate']??0);
    if(($v['symbol']??'')!==$symbol||$at<=$last||$at>$q['time']||!is_finite($mark)||$mark<=0||$mark>1e7||!is_finite($rate)||abs($rate)>1)return AIM_Trading::error('Lịch sử funding không hợp lệ.',503);
    $funding[$symbol][]=array('time'=>$at,'mark'=>$mark,'rate'=>$rate);$last=$at;
   }
  }
  return array('quotes'=>$quotes,'funding'=>$funding);
 }
 static function state($uid){global $wpdb;$r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.AIM_Trading::table('futures').' WHERE user_id=%d',$uid),ARRAY_A);if($wpdb->last_error)return AIM_Trading::error('Chưa đọc được tài khoản phái sinh.',503);return $r?:array('user_id'=>$uid,'state'=>wp_json_encode(AIM_Futures_Engine::blank()),'revision'=>0);}
 static function account(){
  if(!self::ready())return AIM_Trading::error('Mở trang quản trị để nâng cấp dữ liệu phái sinh.',503);
  global $wpdb;$uid=get_current_user_id();$r=self::state($uid);if(is_wp_error($r))return $r;
  $h=$wpdb->get_results($wpdb->prepare('SELECT id,request_id,events,created FROM '.AIM_Trading::table('futures_events').' WHERE user_id=%d ORDER BY id DESC LIMIT 100',$uid),ARRAY_A);
  return array('state'=>json_decode($r['state'],true),'revision'=>(int)$r['revision'],'history'=>$h?:array(),'worker'=>self::worker(),'policy'=>array('name'=>AIM_Futures_Engine::cross(json_decode($r['state'],true))?'cross-linear-v1':AIM_Futures_Engine::POLICY,'fee'=>AIM_Futures_Engine::FEE,'mmr'=>AIM_Futures_Engine::MMR,'max_leverage'=>20));
 }
 static function worker(){ $h=get_option('aimtr_futures_worker',array());$at=(int)($h['at']??0);return array('online'=>$at>0&&time()>=$at&&time()-$at<90,'message'=>$h['message']??'Chưa chạy bộ xử lý phái sinh'); }
 static function fail($message,$status=409){global $wpdb;$wpdb->query('ROLLBACK');return AIM_Trading::error($message,$status);}
 static function mutate($request,$worker_uid=0){
  global $wpdb;$uid=$worker_uid?:get_current_user_id();$p=$request->get_json_params();$req=$p['request_id']??'';
  if(!self::ready())return AIM_Trading::error('Chưa nâng cấp dữ liệu phái sinh.',503);
  if(!is_array($p)||!is_string($req)||!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$req))return AIM_Trading::error('Mã yêu cầu không hợp lệ.');
  $type=$p['type']??'';if(!in_array($type,array('open','close','margin','protect','trailing','set_mode','transfer_in','transfer_out','tick','place_order','cancel_order','sweep'),true)||(!$worker_uid&&in_array($type,array('tick','sweep'),true)))return AIM_Trading::error('Thao tác không hợp lệ.');
  if(in_array($type,array('open','place_order'),true)&&(!is_string($p['symbol']??null)||!in_array($p['symbol'],self::symbols(),true)))return AIM_Trading::error('Mã Futures không hợp lệ.');
  foreach(array('skip_pending','skip_settle','allow_pending','now','opened_at','execution_limit') as $internal)if(array_key_exists($internal,$p))return AIM_Trading::error('Tham số nội bộ không được phép.');
  $hash=hash('sha256',wp_json_encode($p));$t=AIM_Trading::table('futures');$e=AIM_Trading::table('futures_events');$a=AIM_Trading::table('accounts');
  $prior=$wpdb->get_row($wpdb->prepare("SELECT * FROM $e WHERE user_id=%d AND request_id=%s",$uid,$req),ARRAY_A);
  if($wpdb->last_error)return AIM_Trading::error('Chưa tra được yêu cầu.',503);
  if($prior)return $prior['payload_hash']===$hash?array('replayed'=>true,'events'=>json_decode($prior['events'],true)):AIM_Trading::error('Mã yêu cầu đã dùng cho nội dung khác.',409);
  if(in_array($type,array('open','place_order'),true)&&!self::worker()['online'])return AIM_Trading::error('Bộ xử lý phái sinh chưa hoạt động. Tạm dừng mở mới.',503);
  $before=self::state($uid);if(is_wp_error($before))return $before;$state=json_decode($before['state'],true);
  if(!is_array($state)||!isset($state['cash'],$state['positions']))return AIM_Trading::error('Dữ liệu vị thế cần kiểm tra.',503);
  if(in_array($type,array('open','place_order'),true)&&($p['margin_mode']??'isolated')!==($state['margin_mode']??'isolated'))return AIM_Trading::error('Chế độ ký quỹ vừa thay đổi. Tải lại và xác nhận lại lệnh.',409);
  $offline=in_array($type,array('cancel_order','sweep'),true);$snapshot=$offline?array('quotes'=>array(),'funding'=>array()):self::snapshot($state,in_array($type,array('open','place_order'),true)?($p['symbol']??''):'');if(is_wp_error($snapshot))return $snapshot;
  if($wpdb->query('START TRANSACTION')===false)return AIM_Trading::error('Không mở được giao dịch dữ liệu.',503);
  $pool=$wpdb->get_row("SELECT * FROM $a WHERE user_id=0 FOR UPDATE",ARRAY_A);
  $spot=$wpdb->get_row($wpdb->prepare("SELECT * FROM $a WHERE user_id=%d FOR UPDATE",$uid),ARRAY_A);
  $locked=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d FOR UPDATE",$uid),ARRAY_A);
  $prior=$wpdb->get_row($wpdb->prepare("SELECT * FROM $e WHERE user_id=%d AND request_id=%s",$uid,$req),ARRAY_A);
  if($wpdb->last_error)return self::fail('Chưa khóa được dữ liệu.',503);
  if($prior){$wpdb->query('ROLLBACK');return $prior['payload_hash']===$hash?array('replayed'=>true,'events'=>json_decode($prior['events'],true)):AIM_Trading::error('Mã yêu cầu đã dùng cho nội dung khác.',409);}
  if(!$pool||!$spot)return self::fail('Khởi tạo tài khoản giao ngay trước.');
  if((int)($locked['revision']??0)!==(int)$before['revision'])return self::fail('Vị thế vừa thay đổi. Tải lại rồi thử lại cùng yêu cầu.');
  $execution_at=(int)round(microtime(true)*1000);
  foreach($snapshot['quotes'] as $q)if($execution_at-$q['received']>5000||abs($execution_at-$q['time'])>10000||abs($execution_at-$q['book_time'])>10000||$q['next_funding']<=$execution_at)return self::fail('Chờ giá mới sau mốc funding. Thử lại cùng yêu cầu.',503);
  foreach($offline?array():$state['positions'] as $position)if($position['next_funding']<=$execution_at&&$position['next_funding']>$snapshot['quotes'][$position['symbol']]['time'])return self::fail('Đang qua mốc funding. Thử lại cùng yêu cầu.',503);
  try{
   $action=$p;$action['now']=$execution_at;$action['opened_at']=$execution_at;$opening_allowed=AIM_Workshop::allow_open('futures',true);if(in_array($type,array('open','place_order'),true)&&!$opening_allowed)throw new RuntimeException('Vận hành đang tạm dừng mở vị thế mới. Vẫn có thể đóng, bảo vệ và hủy lệnh.');$action['allow_pending']=$opening_allowed&&(int)$pool['cash']>=100000*AIM_Trading::UNIT;
   $result=AIM_Futures_Engine::apply($state,in_array($type,array('transfer_in','transfer_out'),true)?array_merge($action,array('type'=>'tick')):$action,$snapshot['quotes'],$snapshot['funding']);
   $next=$result['state'];$events=$result['events'];$spot_delta=0;
   if(in_array($type,array('transfer_in','transfer_out'),true)){
    $amount=$p['amount']??0;if(!is_int($amount)||$amount<=0||$amount>100000*AIM_Trading::UNIT)throw new RuntimeException('Số AIM chuyển không hợp lệ.');
    if($type==='transfer_in'){
     $reserved=AIM_Limit_Orders::reserved($uid);if((int)$spot['cash']-$reserved['cash']<$amount)throw new RuntimeException('AIM giao ngay khả dụng không đủ.');
     $spot_delta=-$amount;$next['cash']+=$amount;
    }else{if(AIM_Futures_Engine::available($next,$snapshot['quotes'])<$amount)throw new RuntimeException('AIM phái sinh khả dụng không đủ.');$spot_delta=$amount;$next['cash']-=$amount;}
    $events[]=array('kind'=>$type,'amount'=>$amount,'spot_delta'=>$spot_delta,'pool_delta'=>0);
   }
   $pool_delta=array_sum(array_column($events,'pool_delta'));
   if(in_array($type,array('open','place_order'),true)&&(int)$pool['cash']<100000*AIM_Trading::UNIT)throw new RuntimeException('Quỹ đối ứng dưới mức dự phòng. Tạm dừng mở mới.');
  }catch(Throwable $x){return self::fail($x->getMessage());}
  $date=current_time('mysql',true);$data=array('state'=>wp_json_encode($next),'revision'=>(int)$before['revision']+1,'updated'=>$date);
  $ok=$locked?$wpdb->update($t,$data,array('user_id'=>$uid)):$wpdb->insert($t,array_merge(array('user_id'=>$uid),$data));
  $ok2=$wpdb->query($wpdb->prepare("UPDATE $a SET cash=cash+(%d),updated=%s WHERE user_id=0",$pool_delta,$date));
  $ok3=$spot_delta?$wpdb->query($wpdb->prepare("UPDATE $a SET cash=cash+(%d),updated=%s WHERE user_id=%d",$spot_delta,$date,$uid)):true;
  $ok4=$events?$wpdb->insert($e,array('user_id'=>$uid,'request_id'=>$req,'payload_hash'=>$hash,'events'=>wp_json_encode($events),'created'=>$date)):true;
  $ok5=true;
  if($spot_delta)$ok5=$wpdb->insert(AIM_Trading::table('ledger'),array('user_id'=>$uid,'request_id'=>'ft-'.substr(hash('sha256',$req),0,60),'kind'=>$type==='transfer_in'?'futures_transfer_in':'futures_transfer_out','cash_delta'=>$spot_delta,'pool_delta'=>0,'cash_after'=>(int)$spot['cash']+$spot_delta,'created'=>$date));
  if($ok===false||$ok2===false||$ok3===false||$ok4===false||$ok5===false)return self::fail('Chưa ghi nhận giao dịch; kiểm tra kết quả trước khi thử lại.',503);
  if($wpdb->query('COMMIT')===false)return AIM_Trading::error('Chưa rõ kết quả; thử lại đúng mã yêu cầu.',503);
  return array('events'=>$events,'revision'=>$data['revision']);
 }
 static function tick(){
  if(!self::ready())return;
  global $wpdb;$key='aimtr_futures_'.md5($wpdb->prefix);if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$key))!==1)return;
  try{
   $cursor=(int)get_option('aimtr_futures_cursor',0);$t=AIM_Trading::table('futures');
   $ids=$wpdb->get_col($wpdb->prepare("SELECT user_id FROM $t WHERE user_id>%d ORDER BY user_id LIMIT 1",$cursor));
   if(!$ids){$ids=$wpdb->get_col("SELECT user_id FROM $t ORDER BY user_id LIMIT 1");}
   $errors=0;foreach($ids?:array() as $uid){$r=new WP_REST_Request('POST');$r->set_body(wp_json_encode(array('type'=>'tick','request_id'=>'fut-tick-'.wp_generate_uuid4())));$r->set_header('Content-Type','application/json');$result=self::mutate($r,(int)$uid);if(is_wp_error($result)){$errors++;$sweep=new WP_REST_Request('POST');$sweep->set_body(wp_json_encode(array('type'=>'sweep','request_id'=>'fut-sweep-'.wp_generate_uuid4())));$sweep->set_header('Content-Type','application/json');self::mutate($sweep,(int)$uid);}update_option('aimtr_futures_cursor',(int)$uid,false);}
   update_option('aimtr_futures_worker',array('at'=>time(),'message'=>$errors?'Có vị thế chờ nguồn giá/funding hoặc sổ lệnh; không dùng giá cũ.':'Bộ xử lý phái sinh đang hoạt động'),false);
  }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$key));}
 }
 static function chart($request){
  $symbol=$request->get_param('symbol');$interval=$request->get_param('interval');
  if(!in_array($symbol,self::symbols(),true)||!in_array($interval,array('1m','15m','1h','4h','1d'),true))return AIM_Trading::error('Mã hoặc khung nến Futures không hợp lệ.');
  $r=self::remote('klines',array('symbol'=>$symbol,'interval'=>$interval,'limit'=>120),15);if(is_wp_error($r))return $r;
  $rows=$r['data'];$last=0;$now=(int)round(microtime(true)*1000);
  if(!count($rows)||count($rows)>120||$now-$r['received']>30000||$r['received']>$now)return AIM_Trading::error('Nến Futures đã cũ hoặc thiếu.',503);
  foreach($rows as $v){
   if(!is_array($v)||count($v)<7)return AIM_Trading::error('Dữ liệu nến Futures không hợp lệ.',503);
   foreach(array(0,1,2,3,4,5,6) as $i)if(!is_numeric($v[$i])||!is_finite((float)$v[$i]))return AIM_Trading::error('Dữ liệu nến Futures không hợp lệ.',503);
   if($v[0]<=$last||$v[0]>$now||$v[6]<$v[0]||$v[3]<=0||$v[2]>1e7||$v[2]<max($v[1],$v[3],$v[4])||$v[3]>min($v[1],$v[4])||$v[5]<0)return AIM_Trading::error('Thứ tự hoặc OHLC nến Futures không hợp lệ.',503);$last=$v[0];
  }
  $duration=array('1m'=>60000,'15m'=>900000,'1h'=>3600000,'4h'=>14400000,'1d'=>86400000)[$interval];
  if($now-$last>$duration+60000)return AIM_Trading::error('Nguồn nến Futures chưa cập nhật.',503);
  return array('symbol'=>$symbol,'interval'=>$interval,'candles'=>$rows,'received_at'=>$r['received'],'source'=>'Binance USD-M Futures · Last Price');
 }
 static function routes(){
  register_rest_route('aim-trading/v1','/futures/chart',array('methods'=>'GET','permission_callback'=>array('AIM_Trading','public_market_permission'),'callback'=>array(__CLASS__,'chart')));
  foreach(array('account'=>array('GET','account'),'action'=>array('POST','mutate')) as $name=>$route)register_rest_route('aim-trading/v1','/futures/'.$name,array('methods'=>$route[0],'callback'=>array(__CLASS__,$route[1]),'permission_callback'=>array('AIM_Trading','permission')));
  register_rest_route('aim-trading/v1','/futures/quote',array('methods'=>'GET','callback'=>function($r){return self::quote($r->get_param('symbol'));},'permission_callback'=>array('AIM_Trading','public_market_permission')));
 }
 static function admin(){
  if(!self::ready()||!current_user_can('manage_options'))return;
  global $wpdb;$rows=$wpdb->get_results('SELECT user_id,state FROM '.AIM_Trading::table('futures').' ORDER BY user_id LIMIT 100',ARRAY_A);
  echo '<h2>Phái sinh — quỹ hệ thống đối ứng</h2><p><a class="button" href="'.esc_url(home_url('/aim-trading/?mode=futures')).'">Mở giao dịch phái sinh</a></p><p>Quỹ nhận phía ngược lại của mỗi vị thế. Bảng dưới dùng giá mở, không phải giá trị thị trường hiện tại. Không có nút sửa giá hoặc lãi/lỗ. Tối đa 100 tài khoản đầu tiên trong bản thử.</p><table class="widefat striped"><tr><th>Tài khoản</th><th>Vị thế</th><th>Mã</th><th>Người chơi</th><th>Quỹ đối ứng</th><th>Lượng coin</th><th>Giá mở</th><th>Chế độ</th><th>Ký quỹ riêng AIM</th></tr>';
  foreach($rows?:array() as $row){$s=json_decode($row['state'],true);foreach($s['positions']??array() as $p){echo '<tr>';foreach(array($row['user_id'],$p['id'],$p['symbol'],strtoupper($p['side']),$p['side']==='long'?'SHORT':'LONG',number_format_i18n($p['units']/1e8,8),number_format_i18n($p['entry'],4),($s['margin_mode']??'isolated')==='cross'?'Cross':'Isolated',number_format_i18n($p['margin']/1e6,6)) as $v)echo '<td>'.esc_html($v).'</td>';echo '</tr>';}}
  echo '</table><h3>Nhật ký đối ứng phái sinh gần nhất</h3><table class="widefat striped"><tr><th>UTC</th><th>User</th><th>Nghiệp vụ</th><th>Vị thế</th><th>Thu/chi quỹ AIM</th><th>Quỹ bù AIM</th></tr>';
  $events=$wpdb->get_results('SELECT user_id,events,created FROM '.AIM_Trading::table('futures_events').' ORDER BY id DESC LIMIT 50',ARRAY_A);
  foreach($events?:array() as $r)foreach(json_decode($r['events'],true)?:array() as $v){echo '<tr>';foreach(array($r['created'],$r['user_id'],$v['kind'],$v['position_id']??'',number_format_i18n(($v['pool_delta']??0)/1e6,6),number_format_i18n(($v['subsidy']??0)/1e6,6)) as $cell)echo '<td>'.esc_html($cell).'</td>';echo '</tr>';}
  echo '</table>';
 }
}
add_action('admin_init',array('AIM_Futures','migrate'));
add_action('rest_api_init',array('AIM_Futures','routes'));
