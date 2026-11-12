<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/forex-engine.php';
/** Persisted admin pilot. Public trading stays disabled pending feed and rollover validation. */
final class AIM_Forex_Accounts {
 static function enabled(){return (defined('AIM_FOREX_ADMIN_PILOT')&&AIM_FOREX_ADMIN_PILOT===true)||(class_exists('AIM_Market_Launch')&&AIM_Market_Launch::enabled('forex'));}
 static function ready(){return get_option('aimtr_forex_schema')==='1';}
 static function migrate(){
  if(self::ready())return;global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$c=$wpdb->get_charset_collate();$t=AIM_Trading::table('forex');$e=AIM_Trading::table('forex_events');
  dbDelta("CREATE TABLE $t (user_id bigint unsigned NOT NULL, state longtext NOT NULL, revision bigint unsigned NOT NULL DEFAULT 0, active tinyint unsigned NOT NULL DEFAULT 0, updated datetime NOT NULL, PRIMARY KEY  (user_id), KEY active_user (active,user_id)) ENGINE=InnoDB $c;");
  dbDelta("CREATE TABLE $e (id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, request_id varchar(64) NOT NULL, payload_hash varchar(64) NOT NULL, events longtext NOT NULL, created datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY request_once (user_id,request_id)) ENGINE=InnoDB $c;");
  foreach(array($t,$e) as $table)if(strtoupper((string)$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table)))!=='INNODB')return;
  update_option('aimtr_forex_schema','1',false);
 }
 static function state($uid){global $wpdb;$r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.AIM_Trading::table('forex').' WHERE user_id=%d',$uid),ARRAY_A);if($wpdb->last_error)return AIM_Trading::error('Chưa đọc được tài khoản Forex.',503);if($r){$s=json_decode($r['state'],true);if(is_array($s)&&($s['policy']??'')==='forex-isolated-v1'&&empty($s['positions'])){$s['policy']=AIM_Forex_Engine::POLICY;$r['state']=wp_json_encode($s);}}return $r?:array('user_id'=>$uid,'state'=>wp_json_encode(AIM_Forex_Engine::blank()),'revision'=>0);}
 static function worker(){ $h=get_option('aimtr_forex_worker',array());$at=(int)($h['at']??0);return array('online'=>$at>0&&time()>=$at&&time()-$at<90,'message'=>$h['message']??'Chưa có nhịp xử lý Forex'); }
 static function account(){
  $base=array('storage_ready'=>self::ready(),'pilot_enabled'=>self::enabled(),'feed_configured'=>(bool)AIM_Forex_Feed::configured(),'public_trading'=>class_exists('AIM_Market_Launch')&&AIM_Market_Launch::enabled('forex'),'worker'=>self::worker(),'policy'=>AIM_Forex_Engine::POLICY,'notice'=>'Thử nghiệm riêng cho admin; chưa tính swap. Không mở cho thành viên.');
  if(!self::ready())return $base;global $wpdb;$uid=get_current_user_id();$r=self::state($uid);if(is_wp_error($r))return $r;
  $history=$wpdb->get_results($wpdb->prepare('SELECT request_id,events,created FROM '.AIM_Trading::table('forex_events').' WHERE user_id=%d AND events<>\'[]\' ORDER BY id DESC LIMIT 50',$uid),ARRAY_A);if($wpdb->last_error)return AIM_Trading::error('Chưa đọc được lịch sử Forex.',503);
  foreach($history as &$item)$item['events']=json_decode($item['events'],true);
  return array_merge($base,array('state'=>json_decode($r['state'],true),'revision'=>(int)$r['revision'],'history'=>$history));
 }
 static function fail($message,$status=409){global $wpdb;$wpdb->query('ROLLBACK');return AIM_Trading::error($message,$status);}
 static function replay($prior,$hash){return hash_equals($prior['payload_hash'],$hash)?array('replayed'=>true,'events'=>json_decode($prior['events'],true)):AIM_Trading::error('Mã yêu cầu đã dùng cho nội dung khác.',409);}
 static function mutate($request,$worker_uid=0){
  if(!$worker_uid&&!(class_exists('AIM_Market_Launch')?AIM_Market_Launch::permission():current_user_can('manage_options')))return AIM_Trading::error('Chỉ quản trị viên được dùng bản thử Forex.',403);
  global $wpdb;$uid=$worker_uid?:get_current_user_id();$p=$request->get_json_params();$req=is_array($p)?($p['request_id']??''):'';
  if(!self::ready())return AIM_Trading::error('Chưa nâng cấp dữ liệu Forex.',503);
  if($uid<=0||!is_array($p)||!is_string($req)||!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$req))return AIM_Trading::error('Yêu cầu Forex không hợp lệ.');
  $type=$p['type']??'';$fields=array('open'=>array('symbol','side','lots','leverage','tp','sl'),'close'=>array('id','units'),'protect'=>array('id','tp','sl'),'transfer_in'=>array('amount'),'transfer_out'=>array('amount'),'tick'=>array());
  if(!isset($fields[$type])||($type==='tick'&&!$worker_uid)||($worker_uid&&$type!=='tick'))return AIM_Trading::error('Thao tác Forex không hợp lệ.');
  if(array_diff(array_keys($p),array_merge(array('type','request_id'),$fields[$type])))return AIM_Trading::error('Yêu cầu chứa tham số không được phép.');
  $hash=hash('sha256',wp_json_encode($p));$t=AIM_Trading::table('forex');$e=AIM_Trading::table('forex_events');$a=AIM_Trading::table('accounts');
  $prior=$wpdb->get_row($wpdb->prepare("SELECT * FROM $e WHERE user_id=%d AND request_id=%s",$uid,$req),ARRAY_A);if($wpdb->last_error)return AIM_Trading::error('Chưa tra được yêu cầu Forex.',503);if($prior)return self::replay($prior,$hash);
  if(in_array($type,array('open','transfer_in'),true)&&!self::enabled())return AIM_Trading::error('Chưa bật thử nghiệm Forex trên VPS.',503);
  if($type==='open'&&(!AIM_Forex_Feed::configured()||!self::worker()['online']))return AIM_Trading::error('Cần nguồn giá và bộ xử lý Forex đang hoạt động.',503);
  if($type==='open'&&class_exists('AIM_Market_Launch')&&AIM_Market_Launch::active()&&!AIM_Market_Launch::allowed($p['symbol']??''))return AIM_Trading::error('Mã này chưa được kích hoạt nguồn giá.',503);
  if($type==='open'&&!in_array($p['symbol']??null,AIM_Forex_Feed::symbols(),true))return AIM_Trading::error('Mã Forex không hỗ trợ.');
  $before=self::state($uid);if(is_wp_error($before))return $before;$s=json_decode($before['state'],true);
  if(!is_array($s)||($s['policy']??'')!==AIM_Forex_Engine::POLICY||!isset($s['cash'],$s['positions']))return AIM_Trading::error('Dữ liệu Forex cần đối soát.',503);
  // Isolated free-cash transfers do not require quotes or touch collateral.
  $transfer=in_array($type,array('transfer_in','transfer_out'),true);$needs_quotes=!$transfer&&($type==='open'||count($s['positions'])>0);
  $snapshot=$needs_quotes?AIM_Forex_Feed::snapshot(array_values(array_unique(array_merge(array_column($s['positions'],'symbol'),$type==='open'?array($p['symbol']):array())))):array('quotes'=>array(),'specs'=>array());if(is_wp_error($snapshot))return $snapshot;
  if($wpdb->query('START TRANSACTION')===false)return AIM_Trading::error('Chưa mở được giao dịch dữ liệu Forex.',503);
  $pool=$wpdb->get_row("SELECT * FROM $a WHERE user_id=0 FOR UPDATE",ARRAY_A);if($wpdb->last_error)return self::fail('Chưa khóa được quỹ.',503);
  $spot=$wpdb->get_row($wpdb->prepare("SELECT * FROM $a WHERE user_id=%d FOR UPDATE",$uid),ARRAY_A);if($wpdb->last_error)return self::fail('Chưa khóa được tài khoản.',503);
  $locked=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d FOR UPDATE",$uid),ARRAY_A);if($wpdb->last_error)return self::fail('Chưa khóa được vị thế.',503);
  $prior=$wpdb->get_row($wpdb->prepare("SELECT * FROM $e WHERE user_id=%d AND request_id=%s",$uid,$req),ARRAY_A);if($wpdb->last_error)return self::fail('Chưa đối chiếu yêu cầu.',503);
  if($prior){$wpdb->query('ROLLBACK');return self::replay($prior,$hash);}
  if(!$pool||!$spot)return self::fail('Cần tài khoản giao ngay và quỹ đối ứng.');
  if((int)($locked['revision']??0)!==(int)$before['revision'])return self::fail('Vị thế vừa thay đổi. Tải lại rồi thử lại cùng yêu cầu.');
  try{
   $now=(int)round(microtime(true)*1000);$next=$s;$events=array();$spot_delta=0;
   if($transfer){
    $amount=$p['amount']??null;AIM_Forex_Engine::need(is_int($amount)&&$amount>0&&$amount<=100000*AIM_Forex_Engine::U,'Số AIM chuyển không hợp lệ.');
    if($type==='transfer_in'){$reserved=AIM_Limit_Orders::reserved($uid);if(is_wp_error($reserved))throw new RuntimeException('Chưa đọc được AIM đang giữ cho lệnh chờ.');AIM_Forex_Engine::need((int)$spot['cash']-$reserved['cash']>=$amount,'Không đủ AIM giao ngay khả dụng.');$next['cash']+=$amount;$spot_delta=-$amount;}
    else{AIM_Forex_Engine::need($next['cash']>=$amount,'Không đủ AIM Forex khả dụng.');$next['cash']-=$amount;$spot_delta=$amount;}
    $events[]=array('kind'=>$type,'amount'=>$amount,'spot_delta'=>$spot_delta,'pool_delta'=>0);
   }elseif($type==='tick'){$r=AIM_Forex_Engine::evaluate($s,$snapshot['quotes'],$now);$next=$r['state'];$events=$r['events'];}
   elseif($type==='open'){
    // Settle existing exits before adding exposure; commit only the automatic exits if any fire.
    $r=AIM_Forex_Engine::evaluate($s,$snapshot['quotes'],$now);$next=$r['state'];$events=$r['events'];
    if(!$events){$symbol=$p['symbol'];AIM_Forex_Engine::need(isset($snapshot['quotes'][$symbol],$snapshot['specs'][$symbol]),'Nguồn không cung cấp mã này.');AIM_Forex_Engine::need((int)$pool['cash']>=100000*AIM_Forex_Engine::U,'Quỹ dưới mức dự phòng; tạm dừng mở mới.');$r=AIM_Forex_Engine::open($next,$snapshot['quotes'][$symbol],$snapshot['specs'][$symbol],$p['side']??null,$p['lots']??null,$p['leverage']??null,$now,$p['tp']??null,$p['sl']??null);$next=$r['state'];$events=$r['events'];}
    else $events[]=array('kind'=>'open_skipped','reason'=>'Đã xử lý vị thế cũ; kiểm tra kết quả trước khi đặt lệnh mới.','pool_delta'=>0);
   }else{
    $id=$p['id']??null;AIM_Forex_Engine::need(is_int($id)&&isset($s['positions'][$id]),'Vị thế không tồn tại.');$position=$s['positions'][$id];$q=$snapshot['quotes'][$position['symbol']]??null;
    if($type==='close'){$r=AIM_Forex_Engine::close($s,$id,$p['units']??null,$q,$now);$next=$r['state'];$events=$r['events'];}
    else{$next=AIM_Forex_Engine::amend($s,$id,$q,$now,$p['tp']??null,$p['sl']??null);$events[]=array('kind'=>'protect','id'=>$id,'tp'=>$next['positions'][$id]['tp'],'sl'=>$next['positions'][$id]['sl'],'pool_delta'=>0);}
   }
   $pool_delta=array_sum(array_column($events,'pool_delta'));
   AIM_Forex_Engine::need((int)$pool['cash']+$pool_delta>=0,'Quỹ đối ứng không đủ thanh toán; giữ nguyên vị thế và số dư.');
   $before_cap=$s['cash']+array_sum(array_column($s['positions'],'margin'));$after_cap=$next['cash']+array_sum(array_column($next['positions'],'margin'));
   AIM_Forex_Engine::need($before_cap===$after_cap+$pool_delta+$spot_delta,'Đối soát AIM không khớp.');
  }catch(Throwable $x){return self::fail($x->getMessage());}
  $date=current_time('mysql',true);$data=array('state'=>wp_json_encode($next),'revision'=>(int)$before['revision']+1,'active'=>count($next['positions'])?1:0,'updated'=>$date);
  $ok=$locked?$wpdb->update($t,$data,array('user_id'=>$uid)):$wpdb->insert($t,array_merge(array('user_id'=>$uid),$data));if($ok===false)return self::fail('Chưa lưu được vị thế Forex.',503);
  if($pool_delta&&$wpdb->query($wpdb->prepare("UPDATE $a SET cash=cash+(%d),updated=%s WHERE user_id=0",$pool_delta,$date))!==1)return self::fail('Chưa hạch toán được quỹ.',503);
  if($spot_delta&&$wpdb->query($wpdb->prepare("UPDATE $a SET cash=cash+(%d),updated=%s WHERE user_id=%d",$spot_delta,$date,$uid))!==1)return self::fail('Chưa chuyển được AIM.',503);
  // Record even no-op ticks, so a repeated request never acquires a different result.
  if($wpdb->insert($e,array('user_id'=>$uid,'request_id'=>$req,'payload_hash'=>$hash,'events'=>wp_json_encode($events),'created'=>$date))===false)return self::fail('Chưa ghi được lịch sử Forex.',503);
  if($spot_delta&&$wpdb->insert(AIM_Trading::table('ledger'),array('user_id'=>$uid,'request_id'=>'fx-'.substr(hash('sha256',$req),0,60),'kind'=>$type==='transfer_in'?'forex_transfer_in':'forex_transfer_out','cash_delta'=>$spot_delta,'pool_delta'=>0,'cash_after'=>(int)$spot['cash']+$spot_delta,'created'=>$date))===false)return self::fail('Chưa ghi được sổ chuyển AIM.',503);
  if($wpdb->query('COMMIT')===false)return AIM_Trading::error('Chưa rõ kết quả. Thử lại đúng mã yêu cầu.',503);
  return array('events'=>$events,'revision'=>$data['revision']);
 }
 static function tick(){
  if(!self::ready())return;global $wpdb;$key='aimtr_forex_'.md5($wpdb->prefix);if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$key))!==1)return;
  try{
   if(!AIM_Forex_Feed::configured()){update_option('aimtr_forex_worker',array('at'=>0,'message'=>'Chưa cấu hình nguồn Forex; không khớp lệnh.'),false);return;}
   $cursor=(int)get_option('aimtr_forex_cursor',0);$t=AIM_Trading::table('forex');$row=$wpdb->get_row($wpdb->prepare("SELECT user_id FROM $t WHERE active=1 AND user_id>%d ORDER BY user_id LIMIT 1",$cursor),ARRAY_A);if($wpdb->last_error)throw new RuntimeException('Chưa đọc được hàng đợi Forex.');
   if(!$row)$row=$wpdb->get_row("SELECT user_id FROM $t WHERE active=1 ORDER BY user_id LIMIT 1",ARRAY_A);if($wpdb->last_error)throw new RuntimeException('Chưa đọc được hàng đợi Forex.');
   $message='Bộ xử lý Forex đang hoạt động';
   if($row){$uid=(int)$row['user_id'];$r=new WP_REST_Request('POST');$r->set_body(wp_json_encode(array('type'=>'tick','request_id'=>'fx-tick-'.wp_generate_uuid4())));$r->set_header('Content-Type','application/json');$out=self::mutate($r,$uid);if(is_wp_error($out))$message=$out->get_error_message();update_option('aimtr_forex_cursor',$uid,false);}
   update_option('aimtr_forex_worker',array('at'=>time(),'message'=>$message),false);
  }catch(Throwable $x){update_option('aimtr_forex_worker',array('at'=>0,'message'=>'Bộ xử lý Forex cần kiểm tra.'),false);}
  finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$key));}
 }
 static function routes(){foreach(array('account'=>array('GET','account'),'action'=>array('POST','mutate')) as $name=>$r)register_rest_route('aim-trading/v1','/forex/'.$name,array('methods'=>$r[0],'callback'=>array(__CLASS__,$r[1]),'permission_callback'=>array('AIM_Market_Launch','permission')));}
}
add_action('admin_init',array('AIM_Forex_Accounts','migrate'));
add_action('rest_api_init',array('AIM_Forex_Accounts','routes'));
