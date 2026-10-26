<?php
defined('ABSPATH') || exit;
/** Pilot competitions. All writes share the trading pool lock and journal. */
final class AIM_Competitions {
 static function now(){return (int)round(microtime(true)*1000);}
 static function read_state($row){$s=$row?json_decode($row['state'],true):AIM_Workshop_Engine::blank();if(!is_array($s))throw new RuntimeException('Dữ liệu thi đấu cần kiểm tra.');return $s;}
 static function accounts(){global $wpdb;$t=AIM_Trading::table('workshop');$rows=$wpdb->get_results("SELECT user_id,state FROM $t WHERE user_id>0 ORDER BY user_id LIMIT 5001",ARRAY_A);if($wpdb->last_error||count($rows??array())>5000)throw new RuntimeException('Chưa tổng hợp được bảng hạng đầy đủ; cần kiểm tra dữ liệu hoặc nâng giới hạn bản thử.');foreach($rows as &$r){$r['user_id']=(int)$r['user_id'];$r['state']=self::read_state($r);}unset($r);return $rows;}
 static function selected($p,$at){$kind=$p['period']??'week';$anchor=$p['anchor']??$at;if(!is_int($anchor)||$anchor<946684800000||$anchor>4133980800000)throw new RuntimeException('Ngày chọn kỳ không hợp lệ.');return AIM_Competition_Engine::period($kind,$anchor);}
 static function board($request){
  try{global $wpdb;$p=$request?$request->get_params():array();if(isset($p['anchor'])&&is_string($p['anchor'])&&ctype_digit($p['anchor']))$p['anchor']=(int)$p['anchor'];$at=self::now();$period=self::selected($p,$at);$pilot=($p['mode']??'community')==='pilot';$accounts=self::accounts();$global=AIM_Workshop::row(0);if($wpdb->last_error)throw new RuntimeException('Chưa đọc được giải thưởng.');$g=self::read_state($global);$campaign=$g['competitions'][$period['id']]??null;
   $rank=AIM_Competition_Engine::rank($accounts,$period,$pilot);$frozen=!$pilot&&isset($campaign['snapshot']);if($frozen)$rank=$campaign['snapshot'];$profiles=array();$own=null;foreach($accounts as $a){$profiles[$a['user_id']]=$a['state'];if($a['user_id']===get_current_user_id())$own=$a['state'];}
   $myrank=null;foreach($rank['rows'] as &$r){$uid=$r['user_id'];$s=$profiles[$uid]??array();$public=!empty($s['profile']['public']);$r['mine']=$uid===get_current_user_id();if($r['mine'])$myrank=$r['rank'];$r['nickname']=$public?($s['profile']['nickname']?:'Người học AIM'):($r['mine']?'Bạn':'Người chơi ẩn danh');$r['avatar']=$public||$r['mine']?($s['avatar']??'compass'):'compass';$r['frame']=$public||$r['mine']?($s['active_frame']??'none'):'none';unset($r['user_id']);}unset($r);
   $me=$own['seasons'][$period['id']]??null;$history=array_values(array_filter($own['seasons']??array(),function($v){return !empty($v['finished']);}));$avg=$history?array_sum(array_column($history,'return_pct'))/count($history):null;
   $prizes=$campaign['prizes']??AIM_Competition_Engine::default_prizes();$status=$pilot?'pilot':($campaign['status']??'automatic');
   return array_merge($period,array('now'=>$at,'mode'=>$pilot?'pilot':'community','revision'=>(int)($global['revision']??0),'rows'=>array_slice($rank['rows'],0,100),'participants'=>$rank['participants'],'completed'=>$rank['completed'],'unfinished'=>$rank['unfinished'],'excluded'=>$rank['excluded'],'my_rank'=>$myrank,'me'=>$me,'my_stats'=>array('finished'=>count($history),'average_return'=>$avg,'best_return'=>$history?max(array_column($history,'return_pct')):null,'pilot'=>!empty($own['test_account'])),'prizes'=>$prizes,'status'=>$status,'frozen'=>$frozen,'settled_at'=>$campaign['settled_at']??null,'deliveries'=>$campaign['deliveries']??array(),'rule'=>'Xếp theo % lợi nhuận sau phí, cao xuống thấp; không dùng tổng tiền hay điểm rủi ro. Bằng nhau đến 8 số thập phân: hoàn thành trước, rồi mã tài khoản nhỏ hơn. Cùng 10.000 đơn vị vốn và một lượt mỗi kỳ. Múi giờ Việt Nam. Thưởng/cấp AIM không cộng vào vốn thi đấu.','notice'=>$pilot?'Bảng thử kỹ thuật: không nhận giải AIM hoặc quà tài trợ.':'Bảng thử thách dùng giá giả định, chưa phải bảng lợi suất danh mục giao dịch theo giá thật. Chỉ lượt hoàn thành trong kỳ mới có hạng.'));
  }catch(Throwable $x){return AIM_Trading::error($x->getMessage(),503);}
 }
 static function action($request,$at=null,$automatic=false){
  global $wpdb;$p=$request->get_json_params();$at=$at??self::now();$automatic=$automatic===true&&PHP_SAPI==='cli';$actor=$automatic?0:get_current_user_id();if(!$automatic&&!current_user_can('manage_options'))return AIM_Trading::error('Chỉ quản trị vận hành giải.',403);if(!AIM_Workshop::ready())return AIM_Trading::error('Khu luyện tập chưa sẵn sàng.',503);
  if(!is_array($p)||!is_string($p['request_id']??null)||!preg_match('/^[a-zA-Z0-9-]{16,50}$/D',$p['request_id']))return AIM_Trading::error('Mã yêu cầu không hợp lệ.');
  $req='cmp-'.$p['request_id'];$hash=hash('sha256',wp_json_encode($p));$e=AIM_Trading::table('workshop_events');$t=AIM_Trading::table('workshop');$a=AIM_Trading::table('accounts');$l=AIM_Trading::table('ledger');
  if($wpdb->query('START TRANSACTION')===false)return AIM_Trading::error('Chưa mở được giao dịch.',503);
  try{
   $pool=$wpdb->get_row("SELECT * FROM $a WHERE user_id=0 FOR UPDATE",ARRAY_A);if(!$pool||$wpdb->last_error)throw new RuntimeException('Chưa khóa được quỹ AIM.');
   $row=$wpdb->get_row("SELECT * FROM $t WHERE user_id=0 FOR UPDATE",ARRAY_A);if($wpdb->last_error)throw new RuntimeException('Chưa khóa được kỳ thi.');$g=self::read_state($row);
   $prior=$wpdb->get_row($wpdb->prepare("SELECT * FROM $e WHERE user_id=0 AND request_id=%s",$req),ARRAY_A);if($wpdb->last_error)throw new RuntimeException('Chưa đọc được mã thao tác.');if($prior){$wpdb->query('ROLLBACK');return $prior['payload_hash']===$hash?array('replayed'=>true):AIM_Trading::error('Mã cũ có nội dung khác.',409);}
   AIM_Workshop_Engine::need(($p['revision']??null)===(int)($row['revision']??0),'Bảng đã thay đổi. Tải lại trước khi thao tác.');$period=self::selected($p,$at);$id=$period['id'];$campaign=$g['competitions'][$id]??array('status'=>'unconfigured');$type=$p['type']??'';$reason=AIM_Workshop_Engine::clean($p['reason']??'',1500);AIM_Workshop_Engine::need(strlen($reason)>=10,'Cần lý do và kết quả kiểm tra trước khi xử lý.');$date=current_time('mysql',true);
   if($type==='configure'){
    AIM_Workshop_Engine::need($at<$period['ends_at']&&!isset($campaign['snapshot']),'Kỳ đã đóng cấu hình.');$accounts=self::accounts();foreach($accounts as $account)AIM_Workshop_Engine::need(!isset($account['state']['seasons'][$id]),'Đã có người tham gia, không đổi giải giữa kỳ. Chọn kỳ kế tiếp.');
    $campaign=array('status'=>'configured','period'=>$period,'prizes'=>AIM_Competition_Engine::prizes($p['prizes']??null),'configured_at'=>$at,'configured_by'=>$actor,'reason'=>$reason);
   }elseif($type==='freeze'){
    AIM_Workshop_Engine::need($at>=$period['ends_at'],'Chưa hết kỳ để chốt kết quả.');AIM_Workshop_Engine::need(!isset($campaign['snapshot']),'Kỳ đã chốt kết quả.');AIM_Workshop_Engine::need(($p['reviewed']??false)===true,'Cần xác nhận đã kiểm tra tài khoản và kết quả.');$rank=AIM_Competition_Engine::rank(self::accounts(),$period,false);
    $campaign['snapshot']=$rank;$campaign['status']='frozen';$campaign['prizes']=$campaign['prizes']??AIM_Competition_Engine::default_prizes();$campaign['frozen_at']=$at;$campaign['frozen_by']=$actor;$campaign['review_reason']=$reason;
   }elseif($type==='settle'){
    AIM_Workshop_Engine::need($at>=$period['ends_at']&&($campaign['status']??'')==='frozen','Cần chốt kỳ trước; mỗi kỳ chỉ trao AIM một lần.');$winners=array_slice($campaign['snapshot']['rows'],0,count($campaign['prizes']));$total=0;foreach($winners as $w){AIM_Workshop_Engine::need($w['user_id']!==$actor,'Quản trị có giải cần người quản trị khác trao.');$total+=$campaign['prizes'][$w['rank']-1]['aim']*1000000;}
    AIM_Workshop_Engine::need((int)$pool['cash']>=$total,'Quỹ chưa đủ trao toàn bộ giải; chưa trừ hay cộng AIM.');usort($winners,function($x,$y){return $x['user_id']<=>$y['user_id'];});
    foreach($winners as $w){$uid=$w['user_id'];$cash=$campaign['prizes'][$w['rank']-1]['aim']*1000000;$wallet=$wpdb->get_row($wpdb->prepare("SELECT * FROM $a WHERE user_id=%d FOR UPDATE",$uid),ARRAY_A);if(!$wallet||$wpdb->last_error)throw new RuntimeException('Chưa đọc được tài khoản nhận giải.');$member=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d FOR UPDATE",$uid),ARRAY_A);if(!$member||$wpdb->last_error)throw new RuntimeException('Chưa đọc được hồ sơ nhận giải.');$s=self::read_state($member);AIM_Workshop_Engine::need(empty($s['test_account']),'Tài khoản thử không được nhận giải.');
     if($cash){if($wpdb->query($wpdb->prepare("UPDATE $a SET cash=cash+(%d),updated=%s WHERE user_id=%d",$cash,$date,$uid))===false)throw new RuntimeException('Chưa ghi được thưởng.');if($wpdb->insert($l,array('user_id'=>$uid,'request_id'=>'rank-'.$id.'-'.$uid,'kind'=>'competition_reward','cash_delta'=>$cash,'pool_delta'=>-$cash,'cash_after'=>(int)$wallet['cash']+$cash,'created'=>$date))===false)throw new RuntimeException('Chưa ghi được sổ thưởng.');}
     $event=array('kind'=>'competition_reward','period_id'=>$id,'rank'=>$w['rank'],'cash_delta'=>$cash,'at'=>$at);if($wpdb->insert($e,array('user_id'=>$uid,'actor_id'=>$actor,'request_id'=>$req,'payload_hash'=>$hash,'events'=>wp_json_encode(array($event)),'test_account'=>0,'created'=>$date))===false)throw new RuntimeException('Chưa ghi được lịch sử nhận giải.');
     if($wpdb->update($t,array('revision'=>(int)$member['revision']+1,'updated'=>$date),array('user_id'=>$uid))===false)throw new RuntimeException('Chưa cập nhật hồ sơ nhận giải.');
    }
    if($total&&$wpdb->query($wpdb->prepare("UPDATE $a SET cash=cash-(%d),updated=%s WHERE user_id=0",$total,$date))===false)throw new RuntimeException('Chưa ghi được quỹ trả giải.');$campaign['status']='settled';$campaign['settled_at']=$at;$campaign['settled_by']=$actor;$campaign['paid']=$total;
   }elseif($type==='gift_delivered'){
    AIM_Workshop_Engine::need(($campaign['status']??'')==='settled','Cần hoàn tất trao AIM trước khi ghi nhận quà.');$rank=$p['rank']??0;AIM_Workshop_Engine::need(is_int($rank)&&$rank>=1&&$rank<=count($campaign['prizes'])&&!empty($campaign['prizes'][$rank-1]['gift'])&&isset($campaign['snapshot']['rows'][$rank-1]),'Không có người nhận/quà tài trợ ở hạng này.');AIM_Workshop_Engine::need(empty($campaign['deliveries'][$rank]),'Quà đã được ghi nhận trao.');$campaign['deliveries'][$rank]=array('at'=>$at,'actor'=>$actor,'note'=>$reason);
   }else throw new RuntimeException('Thao tác giải không hợp lệ.');
   $g['competitions'][$id]=$campaign;$record=array('state'=>wp_json_encode($g),'revision'=>(int)($row['revision']??0)+1,'updated'=>$date);$ok=$row?$wpdb->update($t,$record,array('user_id'=>0)):$wpdb->insert($t,array_merge(array('user_id'=>0),$record));if($ok===false)throw new RuntimeException('Chưa lưu được kỳ thi.');
   $event=array('kind'=>'competition_'.$type,'period_id'=>$id,'reason'=>$reason,'at'=>$at,'cash_delta'=>0);if($wpdb->insert($e,array('user_id'=>0,'actor_id'=>$actor,'request_id'=>$req,'payload_hash'=>$hash,'events'=>wp_json_encode(array($event)),'test_account'=>1,'created'=>$date))===false)throw new RuntimeException('Chưa lưu được nhật ký vận hành.');
   if($wpdb->query('COMMIT')===false)return AIM_Trading::error('Chưa rõ kết quả, thử lại đúng mã thao tác.',503);return array('status'=>$campaign['status'],'revision'=>$record['revision']);
  }catch(Throwable $x){$wpdb->query('ROLLBACK');return AIM_Trading::error($x->getMessage(),409);}
 }
 // CLI only: catches up closed periods after downtime. Each phase is journaled and locked.
 static function tick($at=null){
  if(PHP_SAPI!=='cli')return AIM_Trading::error('Chỉ worker được tự trao giải.',403);
  $at=$at??self::now();
  try{
   $periods=array();
   foreach(self::accounts() as $account)foreach($account['state']['seasons']??array() as $id=>$season){
    $kind=preg_match('/^(month|quarter|year)-/',$id,$m)?$m[1]:'week';
    $period=AIM_Competition_Engine::period($kind,(int)($season['started']??0));
    if($period['id']===$id&&$period['ends_at']<=$at)$periods[$id]=$period;
   }
   uasort($periods,function($x,$y){return $x['ends_at']<=>$y['ends_at'];});$done=0;
   foreach($periods as $id=>$period){
    $g=self::read_state(AIM_Workshop::row(0));if(($g['competitions'][$id]['status']??'')==='settled')continue;
    foreach(array('freeze','settle') as $type){
     $row=AIM_Workshop::row(0);$g=self::read_state($row);$status=$g['competitions'][$id]['status']??'';
     if($status==='settled'||($type==='freeze'&&$status==='frozen'))continue;
     $request=new WP_REST_Request();
     $request->set_header('Content-Type','application/json');
     $request->set_body(wp_json_encode(array('type'=>$type,'period'=>$period['period'],'anchor'=>$period['starts_at'],'request_id'=>'auto-'.$type.'-'.substr(hash('sha256',$id),0,24),'revision'=>(int)($row['revision']??0),'reviewed'=>true,'reason'=>'Worker tự chốt kỳ theo % lãi và trao giải đã công bố.')));
     $result=self::action($request,$at,true);if(is_wp_error($result))return $result;
    }
    if(++$done>=8)break;
   }
   return array('settled'=>$done);
  }catch(Throwable $x){return AIM_Trading::error($x->getMessage(),503);}
 }
 static function routes(){register_rest_route('aim-trading/v1','/workshop/competition',array('methods'=>'GET','callback'=>array(__CLASS__,'board'),'permission_callback'=>array('AIM_Trading','permission')));register_rest_route('aim-trading/v1','/workshop/competition_action',array('methods'=>'POST','callback'=>array(__CLASS__,'action'),'permission_callback'=>array('AIM_Trading','admin_permission')));}
}
add_action('rest_api_init',array('AIM_Competitions','routes'));
