<?php
/** Versioned, deterministic teaching scenarios. No market quotes or real money. */
defined('ABSPATH') || exit;
require_once __DIR__.'/competition-engine.php';
require_once __DIR__.'/markets-engine.php';
final class AIM_Workshop_Engine {
 const VERSION='lesson-v1';
 static function catalog($at=null){$base=array(
  'reserve'=>array('title'=>'Một năm quản lý tiền','intro'=>'Giữ quỹ dự phòng trước chi phí bất ngờ. Mỗi lượt là một giai đoạn giả định.','price'=>0,'reward'=>10,'max_leverage'=>1,'prices'=>array(100,104,98,91,99,108,105),'income'=>array(1000,1000,1000,1000,1000,1000),'expense'=>array(500,500,4000,500,1000,500),'events'=>array('Có thu nhập mới: chọn phần vốn chịu biến động.','Thị trường vừa tăng; bạn có muốn nâng tỷ trọng?','Có khoản sửa nhà cần thanh toán trong kỳ này.','Giá vừa giảm; cân nhắc vốn còn lại.','Chi phí học tập đến hạn.','Kết thúc năm: giữ kỷ luật hay tăng rủi ro?')),
  'drawdown'=>array('title'=>'Thị trường giảm mạnh','intro'=>'Thực hành phân bổ khi thị trường biến động. Kết quả không phải dự báo hay khuyến nghị.','price'=>0,'reward'=>10,'max_leverage'=>1,'prices'=>array(100,92,83,88,76,86,95),'income'=>array(0,0,0,0,0,0),'expense'=>array(0,0,0,0,0,0),'events'=>array('Tin tức trái chiều, thị trường chưa rõ hướng.','Giá đã giảm một nhịp. Bạn còn bao nhiêu vốn?','Tiếp tục giữ kế hoạch hay đổi tỷ trọng?','Vừa có nhịp hồi; không biết kỳ tới sẽ thế nào.','Một đợt giảm nữa vừa xảy ra.','Kỳ cuối: đánh giá mức lỗ có thể chịu.')),
  'leverage'=>array('title'=>'Đòn bẩy và khoảng nhảy giá','intro'=>'Hợp đồng giả định đóng sau mỗi kỳ. Ký quỹ riêng, tối đa 3x; quan sát mất vốn và phí.','price'=>0,'reward'=>10,'max_leverage'=>3,'prices'=>array(100,107,99,62,78,104,89),'income'=>array(0,0,0,0,0,0),'expense'=>array(0,0,0,0,0,0),'events'=>array('Chọn Long hoặc Short và đòn bẩy đầu tiên.','Giá vừa tăng nhưng không bảo đảm tiếp tục.','Quan sát vốn trước kỳ biến động tiếp theo.','Khoảng nhảy giá đã xuất hiện. Đánh giá lại rủi ro.','Thị trường hồi lại. Không tăng vốn để che lỗ cũ.','Kỳ cuối: so sánh phí và biến động vốn.')),
  'discipline'=>array('title'=>'Kỷ luật qua hai chu kỳ','intro'=>'Bài chuyên sâu 10 kỳ, nhiều đợt giảm và chi phí vốn. Mua một lần, luyện lại không mất phí.','price'=>20,'reward'=>15,'max_leverage'=>3,'prices'=>array(100,109,95,80,97,111,85,73,91,105,99),'income'=>array(0,0,0,0,0,0,0,0,0,0),'expense'=>array(0,0,0,0,0,0,0,0,0,0),'events'=>array_fill(0,10,'Đánh giá vốn, mức giảm tối đa và phí trước khi phân bổ kỳ tiếp theo.'))
 );$time=$at??(int)round(microtime(true)*1000);foreach(AIM_Competition_Engine::periods() as $kind=>$label)$base[$kind==='week'?'season':'season_'.$kind]=AIM_Competition_Engine::definition($kind,$time);return $base;}
 static function season($at){return AIM_Competition_Engine::definition('week',$at);}
 static function definition($run){return $run['definition']??self::catalog()[$run['scenario']];}
 static function blank(){return array('preferences'=>array('theme'=>'dark','experience'=>'basic','favorites'=>array(),'chart'=>array('sma'=>false,'ema'=>false,'level'=>'0')),'alerts'=>array(),'alert_sequence'=>0,'runs'=>array(),'run_sequence'=>0,'completed'=>array(),'owned'=>array('classic'),'active_theme'=>'classic','unlocked'=>array(),'purchases'=>array(),'frames'=>array('none'),'active_frame'=>'none','avatar'=>'compass','profile'=>array('nickname'=>'','bio'=>'','public'=>false,'show_badges'=>true),'articles'=>array(),'article_sequence'=>0,'seasons'=>array(),'test_account'=>true);}
 static function shop(){return array('classic'=>array('title'=>'Nguyên bản','price'=>0,'accent'=>'#20c997'),'aurora'=>array('title'=>'Cực quang','price'=>15,'accent'=>'#a78bfa'),'ocean'=>array('title'=>'Đại dương','price'=>15,'accent'=>'#38bdf8'),'sunset'=>array('title'=>'Hoàng hôn','price'=>25,'accent'=>'#fb923c'));}
 static function avatars(){return array('compass'=>'🧭','rocket'=>'🚀','owl'=>'🦉','wave'=>'🌊','seed'=>'🌱','bull'=>'🐂');}
 static function frames(){return array('none'=>array('title'=>'Tối giản','price'=>0,'color'=>'#64748b'),'mint'=>array('title'=>'Ngọc lục bảo','price'=>10,'color'=>'#20c997'),'violet'=>array('title'=>'Tinh vân','price'=>20,'color'=>'#a78bfa'),'gold'=>array('title'=>'Ánh kim','price'=>30,'color'=>'#fbbf24'));}
 static function need($ok,$message){if(!$ok)throw new RuntimeException($message);}
 static function clean($text,$max){self::need(is_string($text)&&strlen($text)<=$max,'Nội dung quá dài hoặc không hợp lệ.');return trim(strip_tags($text));}
 static function apply($state,$a,$at){
  if(($a['type']??'')==='market_action')return AIM_Markets_Engine::apply($state,$a,$at);
  $s=array_replace_recursive(self::blank(),$state);$type=$a['type']??'';$events=array();$delta=0;
  if($type==='preferences'){
   $p=$a['preferences']??array();self::need(is_array($p),'Thiết lập không hợp lệ.');
   foreach(array('theme'=>array('light','dark'),'experience'=>array('basic','advanced')) as $key=>$allowed)if(isset($p[$key])){self::need(in_array($p[$key],$allowed,true),'Thiết lập không hợp lệ.');$s['preferences'][$key]=$p[$key];}
   if(isset($p['favorites'])){self::need(is_array($p['favorites'])&&count($p['favorites'])<=6,'Danh sách yêu thích không hợp lệ.');foreach($p['favorites'] as $symbol)self::need(is_string($symbol)&&isset(AIM_Trading::symbols()[$symbol]),'Mã yêu thích không hợp lệ.');$s['preferences']['favorites']=array_values(array_unique($p['favorites']));}
   if(isset($p['chart'])){self::need(is_array($p['chart'])&&is_bool($p['chart']['sma']??null),'Thiết lập biểu đồ không hợp lệ.');$level=$p['chart']['level']??'0';self::need(is_string($level)&&preg_match('/^\d{1,8}(\.\d{1,8})?$/D',$level)&&$level<=1e7,'Đường giá không hợp lệ.');self::need(!isset($p['chart']['ema'])||is_bool($p['chart']['ema']),'EMA không hợp lệ.');$s['preferences']['chart']=array('sma'=>$p['chart']['sma'],'ema'=>$p['chart']['ema']??($s['preferences']['chart']['ema']??false),'level'=>$level);}
  }elseif($type==='profile'){
   $s['profile']=array('nickname'=>self::clean($a['nickname']??'',80),'bio'=>self::clean($a['bio']??'',600),'public'=>($a['public']??false)===true,'show_badges'=>($a['show_badges']??true)===true);
  }elseif($type==='avatar'){
   $id=$a['item']??'';self::need(is_string($id)&&isset(self::avatars()[$id]),'Avatar không hợp lệ.');$s['avatar']=$id;
  }elseif($type==='buy_frame'||$type==='apply_frame'){
   $id=$a['item']??'';$item=is_string($id)?(self::frames()[$id]??null):null;self::need($item!==null,'Không có khung này.');
   if($type==='buy_frame'){self::need(!in_array($id,$s['frames'],true),'Đã sở hữu khung này.');$delta=-$item['price']*1000000;$s['frames'][]=$id;}
   else{self::need(in_array($id,$s['frames'],true),'Chưa sở hữu khung này.');$s['active_frame']=$id;}
  }elseif($type==='buy_theme'||$type==='apply_theme'){
   $id=$a['item']??'';$item=self::shop()[$id]??null;self::need($item!==null,'Không có mẫu này.');
   if($type==='buy_theme'){self::need(!in_array($id,$s['owned'],true),'Đã sở hữu mẫu này.');$delta=-$item['price']*1000000;$s['owned'][]=$id;}
   else{self::need(in_array($id,$s['owned'],true),'Chưa sở hữu mẫu này.');$s['active_theme']=$id;}
  }elseif($type==='unlock'){
   $id=$a['scenario']??'';$c=self::catalog()[$id]??null;self::need($c&&$c['price']>0,'Không có kịch bản trả phí này.');self::need(!in_array($id,$s['unlocked'],true),'Đã mở kịch bản này.');$delta=-$c['price']*1000000;$s['unlocked'][]=$id;$s['purchases'][$id]=array('paid'=>-$delta,'request_id'=>$a['request_id']??'', 'at'=>$at,'status'=>'paid');
  }elseif($type==='refund_request'||$type==='refund_review'){
   $id=$a['scenario']??'';$purchase=$s['purchases'][$id]??null;self::need($purchase&&$purchase['paid']>0&&in_array($id,$s['unlocked'],true),'Không có biên nhận mở khóa còn hiệu lực.');
   $reason=self::clean($a['reason']??'',1500);self::need(strlen($reason)>=10,'Mô tả lỗi hoặc lý do xử lý cụ thể, tối thiểu 10 byte.');
   if($type==='refund_request'){self::need(in_array($purchase['status'],array('paid','rejected'),true),'Yêu cầu đang chờ hoặc đã hoàn tiền.');$purchase['status']='pending';$purchase['report']=$reason;$purchase['reported_at']=$at;}
   else{self::need($purchase['status']==='pending','Yêu cầu không còn chờ xử lý.');$status=$a['status']??'';self::need(in_array($status,array('refunded','rejected'),true),'Kết quả xử lý không hợp lệ.');$purchase['status']=$status;$purchase['review_reason']=$reason;$purchase['reviewed_at']=$at;
    if($status==='refunded'){$delta=(int)$purchase['paid'];$s['unlocked']=array_values(array_diff($s['unlocked'],array($id)));foreach($s['runs'] as &$run)if($run['scenario']===$id&&!$run['finished']){$run['finished']=true;$run['refunded']=true;$run['completed_at']=$at;}unset($run);}
   }$s['purchases'][$id]=$purchase;
  }elseif($type==='start_run'){
   $id=$a['scenario']??'';$c=self::catalog($at)[$id]??null;self::need($c!==null,'Không có kịch bản.');self::need(!$c['price']||in_array($id,$s['unlocked'],true),'Cần mở khóa kịch bản trước.');
   foreach($s['runs'] as &$old)if(!empty($old['definition']['ends_at'])&&$at>=$old['definition']['ends_at']&&!$old['finished']){$old['finished']=true;$old['expired']=true;}unset($old);
   if(isset($c['season'])){self::need(!isset($s['seasons'][$c['season']]),'Mỗi mùa chỉ có một lượt, kể cả khi đã hết vốn.');$s['seasons'][$c['season']]=array('started'=>$at,'finished'=>false);}
   foreach($s['runs'] as $run)self::need($run['finished']||$run['scenario']!==$id,'Đang có lượt chưa hoàn thành; hãy tiếp tục lượt đó.');
   // Keep the most recent 12 runs. Completion/reward entitlement is retained separately.
   if(count($s['runs'])>=12){foreach($s['runs'] as $key=>$run)if($run['finished']){unset($s['runs'][$key]);break;}}
   self::need(count($s['runs'])<12,'Hoàn thành lượt đang chơi trước.');$rid=(string)++$s['run_sequence'];
   $s['runs'][$rid]=array('id'=>$rid,'scenario'=>$id,'version'=>self::VERSION,'definition'=>$c,'step'=>0,'equity'=>1000000,'peak'=>1000000,'nav'=>1000000,'drawdown'=>0,'fees'=>0,'liquidations'=>0,'unpaid'=>0,'baseline'=>1000000,'history'=>array(),'finished'=>false,'started'=>$at,'completed_at'=>0,'score'=>0,'reward'=>0);
  }elseif($type==='step'){
   $rid=(string)($a['run_id']??'');$r=$s['runs'][$rid]??null;self::need($r&&!$r['finished'],'Lượt chơi đã kết thúc hoặc không tồn tại.');self::need($r['version']===self::VERSION,'Phiên bản lượt chơi cần được giữ nguyên.');self::need(($a['step']??null)===$r['step'],'Bước đã thay đổi; tải lại lượt chơi.');
   $allocation=$a['allocation']??null;$lev=$a['leverage']??1;$side=$a['side']??'long';$c=self::definition($r);self::need(empty($c['ends_at'])||$at<$c['ends_at'],'Mùa đã kết thúc. Kết quả chưa hoàn thành không xếp hạng.');
   self::need(is_int($allocation)&&in_array($allocation,array(0,25,50,75,100),true)&&is_int($lev)&&$lev>=1&&$lev<=$c['max_leverage']&&in_array($side,array('long','short'),true),'Phân bổ hoặc đòn bẩy không hợp lệ.');self::need($c['max_leverage']>1||$side==='long','Kịch bản này chỉ dùng Long không đòn bẩy.');
   // Virtual cents only inside a run. Never adds to the trading wallet except first-completion reward.
   $i=$r['step'];$before=$r['equity'];$margin=(int)floor($before*$allocation/100);$notional=$margin*$lev;$entry=$c['prices'][$i];$exit=$c['prices'][$i+1];$direction=$side==='long'?1:-1;
   $pnl=(int)round($notional*($exit/$entry-1)*$direction);$fee=(int)ceil($notional*.001)+(int)ceil($notional*$exit/$entry*.001);$maintenance=(int)ceil($notional*$exit/$entry*.005);$liquidated=$notional>0&&$margin+$pnl-$fee<=$maintenance;
   $paid=max(0,$margin+$pnl-$fee);$income=$c['income'][$i]*100;$expense=$c['expense'][$i]*100;$funds=$before-$margin+$paid+$income;$unpaid=max(0,$expense-$funds);$after=max(0,$funds-$expense);
   $r['baseline']=max(0,$r['baseline']+$income-$expense);$r['equity']=$after;$r['fees']+=$fee;$r['liquidations']+=$liquidated?1:0;$r['unpaid']+=$unpaid;$r['nav']=$before>0?(int)round(($r['nav']??1000000)*($before-$margin+$paid)/$before):($r['nav']??1000000);$r['peak']=max($r['peak'],$r['nav']);$r['drawdown']=max($r['drawdown'],$r['peak']>0?($r['peak']-$r['nav'])/$r['peak']:0);
   $r['history'][]=array('step'=>$i,'event'=>$c['events'][$i],'allocation'=>$allocation,'leverage'=>$lev,'side'=>$side,'entry'=>$entry,'exit'=>$exit,'before'=>$before,'after'=>$after,'pnl'=>$paid-$margin,'fee'=>$fee,'income'=>$income,'expense'=>$expense,'unpaid'=>$unpaid,'liquidated'=>$liquidated);
   $r['step']++;$r['finished']=$r['step']===count($c['prices'])-1;$r['score']=max(0,(int)round(100-$r['drawdown']*50-$r['liquidations']*15-($r['unpaid']>0?20:0)));
   if($r['finished']){$r['completed_at']=$at;if(isset($c['season']))$s['seasons'][$c['season']]=array('started'=>$r['started'],'finished'=>true,'completed_at'=>$at,'return_pct'=>($r['equity']/1000000-1)*100,'drawdown_pct'=>$r['drawdown']*100,'risk_score'=>($r['equity']/1000000-1)*100-$r['drawdown']*100,'fees'=>$r['fees']);if(!isset($s['completed'][$r['scenario']])){$delta=$c['reward']*1000000;$r['reward']=$c['reward'];$s['completed'][$r['scenario']]=array('version'=>self::VERSION,'at'=>$at,'score'=>$r['score']);}else $s['completed'][$r['scenario']]['score']=max($s['completed'][$r['scenario']]['score'],$r['score']);}
   $s['runs'][$rid]=$r;
  }elseif($type==='alert_add'){
   self::need(count(array_filter($s['alerts'],function($x){return $x['status']==='active';}))<20,'Tối đa 20 cảnh báo đang bật.');$symbol=$a['symbol']??'';$direction=$a['direction']??'';$price=$a['price']??null;
   self::need(is_string($symbol)&&isset(AIM_Trading::symbols()[$symbol])&&in_array($direction,array('above','below'),true)&&is_string($price)&&preg_match('/^\d{1,8}(\.\d{1,8})?$/D',$price)&&$price>0&&$price<=1e7,'Cảnh báo không hợp lệ.');
   $id=(string)++$s['alert_sequence'];$s['alerts'][$id]=array('id'=>$id,'symbol'=>$symbol,'direction'=>$direction,'price'=>(float)$price,'status'=>'active','at'=>$at);if(count($s['alerts'])>60){foreach($s['alerts'] as $k=>$v)if($v['status']!=='active'){unset($s['alerts'][$k]);break;}}
  }elseif($type==='alert_cancel'){$id=(string)($a['id']??'');self::need(isset($s['alerts'][$id]),'Không có cảnh báo.');$s['alerts'][$id]['status']='cancelled';}
  elseif($type==='article_submit'||$type==='article_edit'){
   $editing=$type==='article_edit';$id=(string)($a['id']??'');if($editing)self::need(isset($s['articles'][$id])&&in_array($s['articles'][$id]['status'],array('revision','rejected'),true),'Chỉ sửa bài được yêu cầu sửa hoặc từ chối.');else self::need(count($s['articles'])<10,'Bản thử cho phép lưu 10 bài.');$title=self::clean($a['title']??'',240);$body=self::clean($a['body']??'',15000);$source=self::clean($a['source']??'',1000);self::need(strlen($title)>=10&&strlen($body)>=300&&filter_var($source,FILTER_VALIDATE_URL)&&strpos($source,'https://')===0,'Cần tiêu đề, nội dung tối thiểu 300 byte và nguồn HTTPS.');
   $hash=hash('sha256',preg_replace('/\s+/u',' ',strtolower($body)));foreach($s['articles'] as $other=>$article)if(!$editing||(string)$other!==$id)self::need($article['hash']!==$hash,'Nội dung này đã được gửi.');$previous=$editing?$s['articles'][$id]:null;if(!$editing)$id=(string)++$s['article_sequence'];$s['articles'][$id]=array('id'=>$id,'title'=>$title,'body'=>$body,'source'=>$source,'hash'=>$hash,'status'=>'pending','reason'=>'','at'=>$at,'reward'=>0,'revision'=>(int)($previous['revision']??0)+1,'previous_review'=>$previous?array('status'=>$previous['status'],'reason'=>$previous['reason']):null);
  }elseif($type==='ops_settings'){
   self::need(is_bool($a['spot_open']??null)&&is_bool($a['futures_open']??null),'Cần trạng thái mở mới cho hai thị trường.');$reason=self::clean($a['reason']??'',1000);self::need(strlen($reason)>=10,'Cần lý do thay đổi vận hành.');$s['operations']=array('spot_open'=>$a['spot_open'],'futures_open'=>$a['futures_open'],'reason'=>$reason,'at'=>$at);
  }else throw new RuntimeException('Thao tác không hợp lệ.');
  $event=array('kind'=>$type,'at'=>$at,'cash_delta'=>$delta);foreach(array('scenario','run_id','item','id','reason','spot_open','futures_open','status') as $key)if(isset($a[$key]))$event[$key]=$a[$key];if($type==='start_run')$event['run_id']=$rid;if($type==='step'){$event['finished']=$r['finished'];$event['scenario']=$r['scenario'];}$events[]=$event;return array('state'=>$s,'events'=>$events,'delta'=>$delta);
 }
 static function alerts($state,$quotes,$at){$s=$state;$events=array();foreach($s['alerts'] as $id=>$a){$q=$quotes[$a['symbol']]??null;if($a['status']!=='active'||!$q||($at-$q['received_at']>5000||$at<$q['received_at']))continue;$price=$q['bid'];if($a['direction']==='above'?$price>=$a['price']:$price<=$a['price']){$s['alerts'][$id]['status']='triggered';$s['alerts'][$id]['triggered_at']=$at;$s['alerts'][$id]['observed']=$price;$events[]=array('kind'=>'alert_triggered','alert_id'=>$id,'symbol'=>$a['symbol'],'price'=>$price,'at'=>$at,'cash_delta'=>0);}}return array('state'=>$s,'events'=>$events,'delta'=>0);}
}
