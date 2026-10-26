<?php
/** Persistent paper limit orders. All mutations lock pool -> account -> order. */
defined('ABSPATH') || exit;
final class AIM_Limit_Orders {
 const SCHEMA='4';
 static function migrate(){
  if(get_option('aimtr_orders_schema')===self::SCHEMA)return true;
  global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';
  $t=AIM_Trading::table('orders');$c=$wpdb->get_charset_collate();
  dbDelta("CREATE TABLE $t (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 user_id bigint(20) unsigned NOT NULL,
 request_id varchar(64) NOT NULL,
 symbol varchar(20) NOT NULL,
 side varchar(4) NOT NULL,
 quantity varchar(32) NOT NULL,
 units bigint(20) NOT NULL,
 limit_price varchar(32) NOT NULL,
 order_type varchar(12) NOT NULL DEFAULT 'limit',
 stop_price varchar(32) NOT NULL DEFAULT '0',
 trigger_reason varchar(12) NOT NULL DEFAULT '',
 expires_at bigint unsigned NOT NULL DEFAULT 0,
 replaces_id bigint(20) unsigned NOT NULL DEFAULT 0,
 reserved_cash bigint(20) NOT NULL DEFAULT 0,
 status varchar(16) NOT NULL DEFAULT 'pending',
 ledger_id bigint(20) unsigned NOT NULL DEFAULT 0,
 created datetime NOT NULL,
 updated datetime NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY request_once (user_id,request_id),
 KEY queue (status,id),
 KEY user_status (user_id,status)
) ENGINE=InnoDB $c;");
  $exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($t)));
  if($exists!==$t)return AIM_Trading::error('Chưa tạo được bảng lệnh chờ.',503);
  foreach(array('order_type','stop_price','trigger_reason','replaces_id','expires_at') as $column){if(!$wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM $t LIKE %s",$column)))return AIM_Trading::error('Chưa nâng cấp đủ cột lệnh chờ.',503);}
  update_option('aimtr_orders_schema',self::SCHEMA,false);return true;
 }
 static function ready(){return get_option('aimtr_orders_schema')===self::SCHEMA;}
 static function worker(){
  $h=get_option('aimtr_worker',array());$at=(int)($h['at']??0);
  return array('online'=>$at>0&&time()-$at<90&&time()>=$at,'at'=>$at,'message'=>$h['message']??'Chưa bật bộ xử lý nền');
 }
 static function crosses($side,$price,$limit){return $price>0&&$limit>0&&($side==='buy'?$price<=$limit:$price>=$limit);}
 static function trigger($order,$price){
  if(($order['order_type']??'limit')!=='tpsl')return self::crosses($order['side'],$price,(float)$order['limit_price'])?'limit':'';
  if($order['side']!=='sell'||$price<=0)return '';
  if(in_array($order['trigger_reason']??'',array('tp','sl'),true))return $order['trigger_reason'];
  if($price<=(float)$order['stop_price'])return 'sl';
  return $price>=(float)$order['limit_price']?'tp':'';
 }
 static function reserved($uid,$except=0){
  global $wpdb;$out=array('cash'=>0,'units'=>array());
  if(!self::ready())return $out;
  $rows=$wpdb->get_results($wpdb->prepare('SELECT side,symbol,units,reserved_cash FROM '.AIM_Trading::table('orders').' WHERE user_id=%d AND status=%s AND id<>%d',$uid,'pending',$except),ARRAY_A);
  if($wpdb->last_error||!is_array($rows))return array('cash'=>PHP_INT_MAX,'units'=>array_fill_keys(array_keys(AIM_Trading::symbols()),PHP_INT_MAX));
  foreach($rows as $r){if($r['side']==='buy')$out['cash']+=(int)$r['reserved_cash'];else $out['units'][$r['symbol']]=($out['units'][$r['symbol']]??0)+(int)$r['units'];}
  return $out;
 }
 static function summary($uid,$cash){
  global $wpdb;$r=self::reserved($uid);
  $orders=self::ready()?$wpdb->get_results($wpdb->prepare('SELECT id,symbol,side,quantity,limit_price,order_type,stop_price,trigger_reason,replaces_id,expires_at,reserved_cash,status,ledger_id,created,updated FROM '.AIM_Trading::table('orders').' WHERE user_id=%d ORDER BY (status=\'pending\') DESC,id DESC LIMIT 100',$uid),ARRAY_A):array();
  return array('available_cash'=>max(0,$cash-$r['cash']),'reserved_cash'=>$r['cash'],'reserved_units'=>(object)$r['units'],'orders'=>$orders?:array(),'worker'=>self::worker());
 }
 static function rollback($message,$status=409){global $wpdb;$wpdb->query('ROLLBACK');return AIM_Trading::error($message,$status);}
 static function lock($uid){
  global $wpdb;$a=AIM_Trading::table('accounts');
  if($wpdb->query('START TRANSACTION')===false)return false;
  $pool=$wpdb->get_row("SELECT * FROM $a WHERE user_id=0 FOR UPDATE",ARRAY_A);
  $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $a WHERE user_id=%d FOR UPDATE",$uid),ARRAY_A);
  return $pool&&$row?$row:false;
 }
 static function place($request,$replace_id=0){
  global $wpdb;$uid=get_current_user_id();$p=$request->get_json_params();
  if(!self::ready())return AIM_Trading::error('Cần hoàn tất cập nhật cơ sở dữ liệu trước.',503);
  $symbol=$p['symbol']??null;$side=$p['side']??null;$req=$p['request_id']??null;$raw=$p['quantity']??null;$limit=$p['limit_price']??null;$type=$p['order_type']??'limit';$stop=$p['stop_price']??'0';$expires=$p['expires_at']??0;
  if(!is_int($expires)||$expires<0||$expires>4102444800)return AIM_Trading::error('Thời hạn lệnh không hợp lệ.');
  if(!in_array($type,array('limit','tpsl'),true)||!is_string($stop)||!preg_match('/^\d{1,8}(\.\d{1,8})?$/D',$stop))return AIM_Trading::error('Loại lệnh hoặc giá cắt lỗ không hợp lệ.');
  if($type==='limit'&&(float)$stop!==0.0)return AIM_Trading::error('Lệnh Limit không dùng giá SL.');
  if(!is_string($symbol)||!isset(AIM_Trading::symbols()[$symbol])||!in_array($side,array('buy','sell'),true)||!is_string($req)||!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$req)||!is_string($raw)||!preg_match('/^\d{1,8}(\.\d{1,8})?$/D',$raw)||!is_string($limit)||!preg_match('/^\d{1,8}(\.\d{1,8})?$/D',$limit))return AIM_Trading::error('Thông tin lệnh Limit không hợp lệ.');
  if($type==='tpsl'&&($side!=='sell'||(float)$stop<=0||(float)$stop>10000000||(float)$stop>=(float)$limit))return AIM_Trading::error('TP/SL chỉ bảo vệ coin đang giữ; cần 0 < SL < TP.');
  $units=(int)round((float)$raw*100000000);$price=(float)$limit;
  $grossFloat=$units/100000000*$price*AIM_Trading::UNIT;
  if(!is_finite($grossFloat)||$grossFloat>100000000000)return AIM_Trading::error('Giá trị lệnh vượt 100.000 AIM.');
  $gross=(int)round($grossFloat);
  if($units<=0||$units>100000000000000||$price<=0||$price>10000000||$gross<1000000||$gross>100000000000)return AIM_Trading::error('Lệnh từ 1–100.000 AIM; giá và khối lượng tối đa 8 số lẻ.');
  $reserve=$side==='buy'?$gross+(int)ceil($gross/1000):0;
  $reference=null;
  if($type==='tpsl')$reference=AIM_Trading::quote($symbol);
  $t=AIM_Trading::table('orders');$row=self::lock($uid);
  if(!$row)return self::rollback('Hãy khởi tạo tài khoản thử trước.');
  $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND request_id=%s",$uid,$req),ARRAY_A);
  if($wpdb->last_error)return self::rollback('Chưa tra được kết quả lệnh.',503);
  if($existing){
   $same=$existing['symbol']===$symbol&&$existing['side']===$side&&(int)$existing['units']===$units&&(float)$existing['limit_price']===$price&&(int)($existing['expires_at']??0)===$expires&&($existing['order_type']??'limit')===$type&&(float)($existing['stop_price']??0)===(float)$stop&&(int)($existing['replaces_id']??0)===$replace_id;
   $wpdb->query('ROLLBACK');
   return $same?array('order_id'=>(int)$existing['id'],'replayed'=>true,'account'=>AIM_Trading::account()):AIM_Trading::error('Mã yêu cầu đã dùng cho nội dung khác.',409);
  }
  if($side==='buy'&&!AIM_Workshop::allow_open('spot',true))return self::rollback('Vận hành đang tạm dừng mua mới. Vẫn có thể bán và hủy lệnh.');
  if($expires&&($expires<time()+15||$expires>time()+30*86400))return self::rollback('Chọn hạn sau ít nhất 15 giây và trong 30 ngày tới.');
  if(is_wp_error($reference)){$wpdb->query('ROLLBACK');return $reference;}
  if($replace_id){
   $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d AND user_id=%d FOR UPDATE",$replace_id,$uid),ARRAY_A);
   if(!$old)return self::rollback('Không tìm thấy lệnh của tài khoản.',404);
   if(!empty($old['trigger_reason']))return self::rollback('TP/SL đã kích hoạt và đang chờ thanh khoản. Có thể hủy, không thể sửa.');
   if(self::due($old))return self::rollback('Lệnh cũ đã hết hạn, không thể sửa.');
   if($old['status']!=='pending')return self::rollback('Lệnh đã khớp, hủy hoặc được sửa trước đó. Làm mới danh sách.');
   if($old['symbol']!==$symbol||$old['side']!==$side||($old['order_type']??'limit')!==$type)return self::rollback('Chỉ sửa giá và khối lượng của cùng tài sản, chiều và loại lệnh.');
  }
  if($type==='tpsl'&&(microtime(true)*1000-$reference['received_at']>5000||(float)$stop>=$reference['bid']||$price<=$reference['bid']))return self::rollback('Cần đặt SL dưới và TP trên giá Bid hiện tại; hãy làm mới giá.');
  if(!self::worker()['online'])return self::rollback('Bộ xử lý nền chưa sẵn sàng. Chưa nhận lệnh Limit mới.',503);
  $counts=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) AS total,SUM(user_id=%d) AS own FROM $t WHERE status='pending'",$uid),ARRAY_A);
  if(!$counts||$wpdb->last_error||(int)$counts['total']-($replace_id?1:0)>=100||(int)$counts['own']-($replace_id?1:0)>=20)return self::rollback('Bản thử giới hạn 20 lệnh chờ/tài khoản và 100 lệnh toàn hệ thống.');
  $r=self::reserved($uid,$replace_id);$positions=json_decode($row['positions'],true)?:array();
  if($side==='buy'&&(int)$row['cash']-$r['cash']<$reserve)return self::rollback('AIM khả dụng không đủ để giữ chỗ cả phí.');
  if($side==='sell'&&($positions[$symbol]['units']??0)-($r['units'][$symbol]??0)<$units)return self::rollback('Không đủ tài sản khả dụng để đặt bán.');
  $date=current_time('mysql',true);
  if($replace_id&&$wpdb->update($t,array('status'=>'replaced','updated'=>$date),array('id'=>$replace_id,'status'=>'pending'))!==1)return self::rollback('Chưa sửa được lệnh. Lệnh cũ được giữ nguyên.',500);
  $ok=$wpdb->insert($t,array('user_id'=>$uid,'request_id'=>$req,'symbol'=>$symbol,'side'=>$side,'quantity'=>$raw,'units'=>$units,'limit_price'=>$limit,'order_type'=>$type,'stop_price'=>$stop,'replaces_id'=>$replace_id,'expires_at'=>$expires,'reserved_cash'=>$reserve,'status'=>'pending','created'=>$date,'updated'=>$date));$id=$wpdb->insert_id;
  if($ok===false)return self::rollback('Chưa lưu được lệnh Limit.',500);
  if($wpdb->query('COMMIT')===false)return AIM_Trading::error('Chưa xác nhận kết quả. Làm mới danh sách lệnh trước khi thử lại.',500);
  return array('order_id'=>$id,'account'=>AIM_Trading::account());
 }
 static function amend($request){
  $id=(int)$request->get_param('id');
  if($id<=0)return AIM_Trading::error('Mã lệnh không hợp lệ.');
  return self::place($request,$id);
 }
 static function cancel($request){
  global $wpdb;$uid=get_current_user_id();$id=(int)$request->get_param('id');
  if(!self::ready())return AIM_Trading::error('Bảng lệnh chưa sẵn sàng.',503);
  if(!self::lock($uid))return self::rollback('Tài khoản chưa sẵn sàng.');
  $t=AIM_Trading::table('orders');$o=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d AND user_id=%d FOR UPDATE",$id,$uid),ARRAY_A);
  if(!$o)return self::rollback('Không tìm thấy lệnh của tài khoản.',404);
  if($o['status']==='replaced')return self::rollback('Lệnh này đã được thay bằng lệnh mới. Hãy chọn lệnh mới để hủy.');
  if($o['status']==='filled')return self::rollback('Lệnh đã khớp trước khi yêu cầu hủy đến máy chủ.');
  $final=$o['status']==='pending'?(self::due($o)?'expired':'cancelled'):$o['status'];
  if($o['status']==='pending'&&$wpdb->update($t,array('status'=>$final,'updated'=>current_time('mysql',true)),array('id'=>$id))===false)return self::rollback('Chưa hủy được lệnh.',500);
  if($wpdb->query('COMMIT')===false)return AIM_Trading::error('Chưa xác nhận hủy. Hãy làm mới danh sách.',500);
  return array('order_id'=>$id,'status'=>$final,'account'=>AIM_Trading::account());
 }
 static function due($o){return !empty($o['expires_at'])&&(int)$o['expires_at']<=time();}
 static function expire_one($id,$uid){
  global $wpdb;if(!self::lock($uid))return self::rollback('Không khóa được tài khoản.',503);
  $t=AIM_Trading::table('orders');$o=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d AND user_id=%d FOR UPDATE",$id,$uid),ARRAY_A);
  if(!$o)return self::rollback('Không tìm thấy lệnh.',404);
  $expired=$o['status']==='pending'&&self::due($o);
  if($expired&&$wpdb->update($t,array('status'=>'expired','updated'=>current_time('mysql',true)),array('id'=>$id,'status'=>'pending'))!==1)return self::rollback('Chưa ghi nhận hết hạn.',503);
  if($wpdb->query('COMMIT')===false)return AIM_Trading::error('Chưa xác nhận hết hạn.',503);
  return array('expired'=>$expired);
 }
 static function cancel_many($request){
  global $wpdb;$uid=get_current_user_id();$p=$request->get_json_params();$ids=$p['ids']??null;
  if(!is_array($ids)||!count($ids)||count($ids)>20)return AIM_Trading::error('Chọn từ 1 đến 20 lệnh.');
  foreach($ids as $id)if(!is_int($id)||$id<=0)return AIM_Trading::error('Mã lệnh không hợp lệ.');
  $ids=array_values(array_unique($ids));if(!self::ready()||!self::lock($uid))return self::rollback('Tài khoản chưa sẵn sàng.',503);
  $t=AIM_Trading::table('orders');$cancelled=0;$expired=0;$skipped=0;
  foreach($ids as $id){
   $o=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d AND user_id=%d FOR UPDATE",$id,$uid),ARRAY_A);
   if(!$o)return self::rollback('Danh sách có lệnh không thuộc tài khoản.',404);
   if($o['status']!=='pending'){$skipped++;continue;}
   $status=self::due($o)?'expired':'cancelled';
   if($wpdb->update($t,array('status'=>$status,'updated'=>current_time('mysql',true)),array('id'=>$id,'status'=>'pending'))!==1)return self::rollback('Chưa hủy được nhóm lệnh; mọi thay đổi đã hoàn tác.',503);
   if($status==='expired')$expired++;else $cancelled++;
  }
  if($wpdb->query('COMMIT')===false)return AIM_Trading::error('Chưa rõ kết quả hủy. Kiểm tra lại danh sách.',503);
  return array('cancelled'=>$cancelled,'expired'=>$expired,'skipped'=>$skipped,'account'=>AIM_Trading::account());
 }
 static function tick(){
  global $wpdb;
  if(PHP_SAPI!=='cli')return AIM_Trading::error('Chỉ chạy qua PHP CLI.',403);
  if(!self::ready())return AIM_Trading::error('Mở trang quản trị WordPress một lần để nâng cấp bảng lệnh.',503);
  // MySQL advisory lock also prevents workers on two hosts processing concurrently.
  $key='aimtr_worker_'.substr(hash('sha256',DB_NAME.$wpdb->prefix),0,32);
  if((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$key))!=='1')return AIM_Trading::error('Đã có một lượt xử lý khác đang chạy.',409);
  try{
   $cursor=(int)get_option('aimtr_worker_cursor',0);
   $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.AIM_Trading::table('orders')." WHERE status='pending' ORDER BY (id>%d) DESC,id LIMIT 100",$cursor),ARRAY_A);
   if($wpdb->last_error||!is_array($rows))return AIM_Trading::error('Không đọc được hàng chờ.',500);
   $filled=0;$wait=0;$errors=0;$expired=0;$started=microtime(true);$depth_batch=array();
   foreach($rows as $o){
    if(microtime(true)-$started>35)break;
    $cursor=(int)$o['id'];
    if(self::due($o)){$e=self::expire_one((int)$o['id'],(int)$o['user_id']);if(is_wp_error($e))$errors++;elseif($e['expired'])$expired++;continue;}
    $r=new WP_REST_Request('POST');$r->set_header('content-type','application/json');
    $r->set_body(wp_json_encode(array('symbol'=>$o['symbol'],'side'=>$o['side'],'quantity'=>$o['quantity'],'request_id'=>'limit-fill-'.str_pad((string)$o['id'],20,'0',STR_PAD_LEFT).'-v1')));
    $result=AIM_Trading::trade($r,$o,$depth_batch);
    if(is_wp_error($result)){if(($result->get_error_data()['status']??0)===409)$wait++;else $errors++;}else $filled++;
   }
   update_option('aimtr_worker_cursor',$cursor,false);
   $msg='Khớp '.$filled.' · Hết hạn '.$expired.' · Chờ '.$wait.' · Lỗi nguồn/hệ thống '.$errors;
   update_option('aimtr_worker',array('at'=>time(),'message'=>$msg),false);
   return array('expired'=>$expired,'filled'=>$filled,'waiting'=>$wait,'errors'=>$errors,'message'=>$msg);
  }finally{$wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$key));}
 }
 static function routes(){
  $permission=array('AIM_Trading','permission');
  register_rest_route('aim-trading/v1','/orders/cancel-many',array('methods'=>'POST','permission_callback'=>$permission,'callback'=>array(__CLASS__,'cancel_many')));
  register_rest_route('aim-trading/v1','/orders',array('methods'=>'POST','permission_callback'=>$permission,'callback'=>array(__CLASS__,'place')));
  register_rest_route('aim-trading/v1','/orders/(?P<id>\d+)/amend',array('methods'=>'POST','permission_callback'=>$permission,'callback'=>array(__CLASS__,'amend')));
  register_rest_route('aim-trading/v1','/orders/(?P<id>\d+)/cancel',array('methods'=>'POST','permission_callback'=>$permission,'callback'=>array(__CLASS__,'cancel')));
 }
}
