<?php
defined('ABSPATH') || exit;
/** Calendar periods and rankings. Trading wallet cash never enters this score. */
final class AIM_Competition_Engine {
 static function periods(){return array('week'=>'Tuần','month'=>'Tháng','quarter'=>'Quý','year'=>'Năm');}
 static function period($kind,$at){
  if(!isset(self::periods()[$kind]))throw new RuntimeException('Kỳ xếp hạng không hợp lệ.');
  $d=(new DateTimeImmutable('@'.(int)floor($at/1000)))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->setTime(0,0);
  if($kind==='week'){
   $cut=new DateTimeImmutable('2026-11-07 21:00:00',new DateTimeZone('Asia/Ho_Chi_Minh'));
   $instant=(new DateTimeImmutable('@'.(int)floor($at/1000)))->setTimezone($cut->getTimezone());
   if($instant<$cut){$start=$d->modify('monday this week');$end=$start->modify('+7 days');if($start->format('Y-m-d')==='2026-11-02')$end=$cut;$week_id=$start->format('Y-m-d');}
   else{$start=$d->modify('saturday this week')->setTime(21,0);if($start>$instant)$start=$start->modify('-7 days');$end=$start->modify('+7 days');$week_id=$start->modify('+2 days')->format('Y-m-d');}
   $label='Tuần '.$start->format('d/m/Y');
  }
  elseif($kind==='month'){$start=$d->modify('first day of this month');$end=$start->modify('+1 month');$label='Tháng '.$start->format('m/Y');}
  elseif($kind==='quarter'){$q=(int)floor(((int)$d->format('n')-1)/3);$start=$d->setDate((int)$d->format('Y'),$q*3+1,1);$end=$start->modify('+3 months');$label='Quý '.($q+1).'/'.$start->format('Y');}
  else{$start=$d->setDate((int)$d->format('Y'),1,1);$end=$start->modify('+1 year');$label='Năm '.$start->format('Y');}
  return array('id'=>$kind==='week'?$week_id:$kind.'-'.$start->format('Y-m-d'),'period'=>$kind,'label'=>$label,'starts_at'=>$start->getTimestamp()*1000,'ends_at'=>$end->getTimestamp()*1000,'scenario'=>$kind==='week'?'season':'season_'.$kind);
 }
 static function definition($kind,$at){
  $p=self::period($kind,$at);$n=array('week'=>8,'month'=>12,'quarter'=>16,'year'=>24)[$kind];$prices=array(100);
  for($i=0;$i<$n;$i++){$change=(hexdec(substr(hash('sha256','aim-season-v1-'.$p['id'].'-'.$i),0,4))%2401-1200)/10000;$prices[]=round(end($prices)*(1+$change),4);}
  return array('title'=>'Thi đấu · '.$p['label'],'intro'=>'Cùng 10.000 đơn vị vốn, một lượt mỗi kỳ. Xếp hạng theo % lợi nhuận sau phí; mức giảm vốn chỉ tham khảo. Giá giả định, không phải giá thị trường.','price'=>0,'reward'=>0,'max_leverage'=>3,'prices'=>$prices,'income'=>array_fill(0,$n,0),'expense'=>array_fill(0,$n,0),'events'=>array_fill(0,$n,'Chọn mức rủi ro trước khi biết giá kỳ sau. Kết quả được xếp theo % lợi nhuận sau phí.'),'season'=>$p['id'],'period'=>$kind,'starts_at'=>$p['starts_at'],'ends_at'=>$p['ends_at']);
 }
 // Rank completed results for awards; optionally include live runs for the display only.
 static function weekly_result($state,$id,$record,$live=false){
  if(!empty($record['finished']))return $record;
  if(!$live)return null;
  foreach($state['runs']??array() as $run){
   if(($run['definition']['season']??'')!==$id||!empty($run['expired'])||!empty($run['refunded']))continue;
   return array('started'=>$run['started'],'finished'=>false,'completed_at'=>0,'return_pct'=>((int)$run['equity']/1000000-1)*100,'drawdown_pct'=>(float)($run['drawdown']??0)*100,'fees'=>(int)($run['fees']??0));
  }
  return null;
 }
 static function rank($accounts,$period,$pilot=false,$live=false){
  $rows=array();$started=0;$unfinished=0;$excluded=0;$completed=0;
  $aggregate=$period['period']!=='week'&&$period['ends_at']>1793466000000;
  foreach($accounts as $account){
   $s=$account['state'];$records=array();$seen=false;$pending=false;
   if($aggregate){
    foreach($s['seasons']??array() as $id=>$record){
     if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',(string)$id))continue;
     $week=self::period('week',(int)($record['started']??0));
     // A week belongs to the month/quarter in which it closes, counted once.
     if($week['ends_at']>($period['as_of']??(int)round(microtime(true)*1000))||$week['id']!==$id||$week['ends_at']-1<$period['starts_at']||$week['ends_at']-1>=$period['ends_at'])continue;
     $seen=true;$r=self::weekly_result($s,$id,$record,false);
     if(!$r){$pending=true;continue;}
     if(($r['completed_at']??0)>=$week['ends_at']||($r['completed_at']??0)<($r['started']??0))continue;
     $records[]=$r;
    }
   }else{
    $record=$s['seasons'][$period['id']]??null;if(!$record)continue;$seen=true;
    $r=self::weekly_result($s,$period['id'],$record,$live);$pending=empty($record['finished']);
    if($r&&($r['started']??0)>=$period['starts_at']&&((empty($r['finished']))||(($r['completed_at']??0)<$period['ends_at']&&($r['completed_at']??0)>=($r['started']??0))))$records[]=$r;
   }
   if(!$seen)continue;if((!empty($s['test_account']))!==$pilot){$excluded++;continue;}$started++;if($pending)$unfinished++;
   if(!$records)continue;$profit=0;$fees=0;$drawdown=0;$at=0;$valid=true;$provisional=false;
   foreach($records as $r){$p=$r['return_pct']??null;if(!is_numeric($p)||!is_finite((float)$p)||$p< -100){$valid=false;break;}$profit+=(float)$p;$fees+=(int)($r['fees']??0);$drawdown=max($drawdown,(float)($r['drawdown_pct']??0));$at=max($at,(int)($r['completed_at']??0));$provisional=$provisional||empty($r['finished']);}
   if(!$valid)continue;if(!$provisional)$completed++;
   $rows[]=array('user_id'=>(int)$account['user_id'],'return_pct'=>round($profit,8),'drawdown_pct'=>$drawdown,'fees'=>$fees,'completed_at'=>$at,'provisional'=>$provisional,'weeks'=>$aggregate?count($records):1);
  }
  usort($rows,function($a,$b){return ($b['return_pct']<=>$a['return_pct'])?:(($a['completed_at']?:PHP_INT_MAX)<=>($b['completed_at']?:PHP_INT_MAX))?:($a['user_id']<=>$b['user_id']);});
  foreach($rows as $i=>&$r)$r['rank']=$i+1;unset($r);
  return array('rows'=>$rows,'participants'=>$started,'completed'=>$completed,'unfinished'=>$unfinished,'excluded'=>$excluded,'aggregate'=>$aggregate);
 }
 static function default_prizes(){return self::prizes(array_map(function($n){return array("aim"=>$n);},range(100,10,-10)));}
 static function prizes($input){
  if(!is_array($input)||!in_array(count($input),array(5,10),true))throw new RuntimeException('Cần cấu hình đủ 10 hạng (chấp nhận kỳ cũ 5 hạng).');$out=array();
  foreach(array_values($input) as $i=>$p){$aim=$p['aim']??null;if(!is_int($aim)||$aim<0||$aim>1000000)throw new RuntimeException('Mỗi giải AIM phải là số nguyên từ 0 đến 1.000.000.');
   $name=AIM_Workshop_Engine::clean($p['sponsor']??'',240);$gift=AIM_Workshop_Engine::clean($p['gift']??'',900);if(($name==='')!==($gift===''))throw new RuntimeException('Giải tài trợ cần cả tên đơn vị và mô tả quà.');
   $out[]=array('rank'=>$i+1,'aim'=>$aim,'sponsor'=>$name,'gift'=>$gift);
  }return $out;
 }
}


/** Read-only performance accounting. No orders, wallet writes or lesson outcomes. */
final class AIM_Sim_Performance_Engine {
 const U=1000000;
 static function internal($kind){return in_array($kind,['futures_transfer_in','futures_transfer_out','forex_transfer_in','forex_transfer_out','cm_transfer_in','cm_transfer_out'],true);}
 static function trade($kind){return in_array($kind,['buy','sell','stock_buy','stock_sell'],true);}
 static function replay($ledger,$derivatives,$at){
  $cash=0;$assets=[];$books=[];$flows=[];$traded=false;$last_trade=0;$fees=0;
  foreach($ledger as $row){$time=(int)$row['at'];if($time>=$at)break;$kind=$row['kind'];$cash=(int)$row['cash_after'];
   if(self::trade($kind)){
    $stock=strpos($kind,'stock_')===0;$market=$stock?'stock':'spot';$key=$market.':'.$row['symbol'];$units=$stock?(float)$row['quantity']:(int)round((float)$row['quantity']*1e8);
    $sign=in_array($kind,['buy','stock_buy'],true)?1:-1;$assets[$key]=($assets[$key]??0)+$sign*$units;
    if($assets[$key]< -0.000001)throw new RuntimeException('Lịch sử tài sản không khớp; cần đối soát.');
    if(abs($assets[$key])<0.000001)unset($assets[$key]);$traded=true;$last_trade=max($last_trade,$time);$fees+=(int)$row['fee'];
   }elseif(!self::internal($kind)&&(int)$row['cash_delta']!==0)$flows[]=['at'=>$time,'amount'=>(int)$row['cash_delta']];
  }
  foreach($derivatives as $market=>$rows){$book=['cash'=>0,'positions'=>[],'orders'=>[],'mode'=>'isolated'];
   foreach($rows as $row){$time=(int)$row['at'];if($time>=$at)break;
    foreach($row['events'] as $event){$kind=$event['kind']??'';$id=(string)($event['position_id']??$event['id']??'');
     if($kind==='transfer_in'||$kind==='transfer_out'){$book['cash']+=($kind==='transfer_in'?1:-1)*(int)$event['amount'];continue;}
     if($kind==='margin_mode'){$book['mode']=$event['mode'];continue;}
     if($kind==='order_placed'){$oid=(string)$event['order_id'];$amount=(int)$event['amount'];$book['cash']-=$amount;$book['orders'][$oid]=$amount;continue;}
     if(in_array($kind,['order_cancelled','order_expired','order_rejected'],true)){$oid=(string)$event['order_id'];$amount=$book['orders'][$oid]??(int)($event['amount']??0);$book['cash']+=$amount;unset($book['orders'][$oid]);continue;}
     if($kind==='open'){
      if(isset($event['order_id'])){$oid=(string)$event['order_id'];$book['cash']+=$book['orders'][$oid]??0;unset($book['orders'][$oid]);}
      $margin=$market==='futures'&&($event['margin_mode']??$book['mode'])==='cross'?0:(int)$event['margin'];$fee=(int)($event['fee']??0);
      $book['positions'][$id]=['symbol'=>$event['symbol'],'side'=>$event['side'],'units'=>(float)$event['units'],'entry'=>(float)$event['price'],'margin'=>$margin,'mode'=>$event['margin_mode']??'isolated'];$book['cash']-=$margin+$fee;$fees+=$fee;$traded=true;$last_trade=max($last_trade,$time);continue;
     }
     if(isset($event['pnl'])&&isset($event['units'])){
      if(!isset($book['positions'][$id]))throw new RuntimeException('Thiếu lịch sử mở vị thế mô phỏng.');$p=&$book['positions'][$id];$margin=(int)($event['released_margin']??$event['margin']??0);$fee=(int)($event['fee']??0);$aid=(int)($event['subsidy']??0);
      $book['cash']+=isset($event['returned'])?(int)$event['returned']:$margin+(int)$event['pnl']-$fee+$aid;$p['units']-=(float)$event['units'];$p['margin']-=$margin;
      if($p['units']< -0.000001)throw new RuntimeException('Khối lượng vị thế không khớp.');if($p['units']<0.000001)unset($book['positions'][$id]);unset($p);
      if($aid)$flows[]=['at'=>$time,'amount'=>$aid];$fees+=$fee;$traded=true;$last_trade=max($last_trade,$time);continue;
     }
     if($kind==='funding'){$delta=(int)($event['cash_delta']??0);if(!isset($book['positions'][$id]))throw new RuntimeException('Thiếu vị thế nhận funding.');if(($book['positions'][$id]['mode']??$book['mode'])==='cross')$book['cash']+=$delta;else $book['positions'][$id]['margin']+=$delta;continue;}
     if($kind==='margin'){$amount=(int)$event['amount'];if(!isset($book['positions'][$id]))throw new RuntimeException('Thiếu vị thế ký quỹ.');$book['cash']-=$amount;$book['positions'][$id]['margin']+=$amount;continue;}
     if($kind==='cross_subsidy'){$amount=(int)$event['amount'];$book['cash']+=$amount;$flows[]=['at'=>$time,'amount'=>$amount];}
    }
   }$books[$market]=$book;
  }
  return ['cash'=>$cash,'assets'=>$assets,'books'=>$books,'flows'=>$flows,'traded'=>$traded,'last_trade'=>$last_trade,'fees'=>$fees];
 }
 static function equity($book,$at,$price){
  $equity=(float)$book['cash'];
  foreach($book['assets'] as $key=>$units){[$market,$symbol]=explode(':',$key,2);$mark=$price($market,$symbol,$at,'long');if(!is_numeric($mark)||!is_finite((float)$mark)||$mark<=0)throw new RuntimeException('Chưa có giá định giá hợp lệ.');
   if($market==='spot')$equity+=$units/1e8*$mark*self::U;
   else{$vn=isset(AIM_VN_Stock_Feed::map()[$symbol]);$equity+=$units*$mark*self::U/($vn?25000000:1000);}
  }
  foreach($book['books'] as $market=>$account){$equity+=(float)$account['cash']+array_sum($account['orders']);
   foreach($account['positions'] as $position){$mark=$price($market,$position['symbol'],$at,$position['side']);if(!is_numeric($mark)||!is_finite((float)$mark)||$mark<=0)throw new RuntimeException('Chưa có giá định giá vị thế.');
    $scale=$market==='futures'?self::U/1e8:self::U/1000;$pnl=($mark-$position['entry'])*$position['units']*$scale*($position['side']==='long'?1:-1);$equity+=$position['margin']+$pnl;
   }
  }
  if(!is_finite($equity))throw new RuntimeException('Giá trị tài khoản vượt giới hạn.');return $equity;
 }
 static function dietz($start,$end,$flows,$from,$to){
  if($to<=$from)return null;$net=0.;$weighted=0.;foreach($flows as $flow){if($flow['at']<$from||$flow['at']>=$to)continue;$amount=(float)$flow['amount'];$net+=$amount;$weighted+=$amount*max(0,min(1,($to-$flow['at'])/($to-$from)));}
  $base=$start+$weighted;if($base<=0)return null;$result=100*($end-$start-$net)/$base;return is_finite($result)?round($result,8):null;
 }
 static function result($ledger,$events,$period,$at,$price){
  $end=min($at,$period['ends_at']);$first=self::replay($ledger,$events,$period['starts_at']);$last=self::replay($ledger,$events,$end);
  $from=$period['starts_at'];$opening=self::equity($first,$from,$price);if($opening<=0){$dates=[];foreach($last['flows'] as $f)if($f['at']>=$from&&$f['at']<$end&&$f['amount']>0)$dates[]=$f['at'];if($dates){$from=min($dates);$opening=self::equity(self::replay($ledger,$events,$from),$from,$price);}}$closing=self::equity($last,$end,$price);$pct=self::dietz($opening,$closing,$last['flows'],$from,$end);
  return ['return_pct'=>$pct,'traded'=>$last['traded'],'last_trade'=>$last['last_trade'],'fees'=>$last['fees']-$first['fees'],'provisional'=>$at<$period['ends_at'],'eligible'=>$pct!==null&&$last['traded'],'weeks'=>1,'completed_at'=>$end];
 }
}

final class AIM_Sim_Performance {
 static $data=null,$prices=[],$allow_network=true,$as_of=null,$price_started_at=null;
 static function now(){return (int)round(microtime(true)*1000);}
 static function period($kind,$at){$p=AIM_Competition_Engine::period($kind,$at);$p['id']='sim-'.$p['id'];$p['source']='simulation';return $p;}
 static function time($date){$n=strtotime($date.' UTC');if($n===false)throw new RuntimeException('Ngày sổ giao dịch không hợp lệ.');return $n*1000;}
 static function load($refresh=false,$inside=false){
  if(self::$data!==null&&!$refresh)return self::$data;global $wpdb;$out=[];
  if(!$inside&&$wpdb->query('START TRANSACTION READ ONLY')===false)throw new RuntimeException('Chưa đọc được ảnh chụp dữ liệu mô phỏng.');
  try{
   $rows=$wpdb->get_results('SELECT user_id,cash,positions FROM '.AIM_Trading::table('accounts').' WHERE user_id>0 ORDER BY user_id LIMIT 5001',ARRAY_A);
   if($wpdb->last_error||count($rows??[])>5000)throw new RuntimeException('Cần kiểm tra giới hạn tổng hợp tài khoản.');
   foreach($rows as $r){$uid=(int)$r['user_id'];$out[$uid]=['user_id'=>$uid,'stored'=>$r,'ledger'=>[],'events'=>['futures'=>[],'forex'=>[],'commodity'=>[]],'state'=>[],'stored_derivatives'=>[]];}
   $rows=$wpdb->get_results('SELECT id,user_id,kind,symbol,quantity,fee,cash_delta,cash_after,created FROM '.AIM_Trading::table('ledger').' WHERE user_id>0 ORDER BY created,id LIMIT 100001',ARRAY_A);
   if($wpdb->last_error||count($rows??[])>100000)throw new RuntimeException('Sổ giao dịch cần phân trang trước khi tổng hợp.');
   foreach($rows as $r){$uid=(int)$r['user_id'];if(isset($out[$uid])){$r['at']=self::time($r['created']);$out[$uid]['ledger'][]=$r;}}
   foreach(['futures'=>'aimtr_futures_schema','forex'=>'aimtr_forex_schema','commodity'=>'aimtr_commodity_schema'] as $market=>$schema){
    if(get_option($schema)!=='1')continue;
    $rows=$wpdb->get_results('SELECT id,user_id,events,created FROM '.AIM_Trading::table($market.'_events').' WHERE user_id>0 ORDER BY created,id LIMIT 100001',ARRAY_A);
    if($wpdb->last_error||count($rows??[])>100000)throw new RuntimeException('Chưa đọc đủ lịch sử '.$market.'.');
    foreach($rows as $r){$uid=(int)$r['user_id'];if(!isset($out[$uid]))continue;$events=json_decode($r['events'],true);if(!is_array($events))throw new RuntimeException('Lịch sử vị thế cần đối soát.');$out[$uid]['events'][$market][]=['at'=>self::time($r['created']),'events'=>$events];}
    $states=$wpdb->get_results('SELECT user_id,state FROM '.AIM_Trading::table($market).' WHERE user_id>0 LIMIT 5001',ARRAY_A);if($wpdb->last_error)throw new RuntimeException('Chưa đọc được số dư '.$market);foreach($states?:[] as $row)if(isset($out[(int)$row['user_id']]))$out[(int)$row['user_id']]['stored_derivatives'][$market]=json_decode($row['state'],true);

   }
   $profiles=$wpdb->get_results('SELECT user_id,state FROM '.AIM_Trading::table('workshop').' WHERE user_id>0 LIMIT 5001',ARRAY_A);
   if($wpdb->last_error)throw new RuntimeException('Chưa đọc được tên hiển thị.');foreach($profiles??[] as $r)if(isset($out[(int)$r['user_id']]))$out[(int)$r['user_id']]['state']=json_decode($r['state'],true)?:[];
   if(!$inside)$wpdb->query('COMMIT');self::$data=$out;return $out;
  }catch(Throwable $e){if(!$inside)$wpdb->query('ROLLBACK');throw $e;}
 }
 static function accounts(){return array_values(self::load());}
 static function positive($value){return is_numeric($value)&&is_finite((float)$value)&&$value>0;}
 static function price($market,$symbol,$at,$side='long'){
  $now=self::now();$current=self::$as_of!==null&&$at===self::$as_of;$stamp=$current?'live':(string)(int)floor($at/60000);$key=$market.'|'.$symbol.'|'.$stamp.'|'.$side;
  if(isset(self::$prices[$key]))return self::$prices[$key];$cache='aimtr_sim_px_'.substr(hash('sha256',$key),0,32);$cached=get_transient($cache);if(self::positive($cached))return self::$prices[$key]=(float)$cached;
  if(!self::$allow_network)throw new RuntimeException('Chờ hoàn tất định giá trước khi chốt bảng.');$priorError=get_transient($cache.'_error');if(is_string($priorError)&&$priorError!=='')throw new RuntimeException($priorError);if(self::$price_started_at===null)self::$price_started_at=microtime(true);if(microtime(true)-self::$price_started_at>12)throw new RuntimeException('Đang cập nhật giá; tự làm mới ở lượt kế tiếp.');try{$price=null;
  if($market==='spot'||$market==='futures'){
   if($current){$q=$market==='spot'?AIM_Trading::quote($symbol):AIM_Futures::quote($symbol);if(is_wp_error($q))throw new RuntimeException($q->get_error_message());$price=$market==='spot'?$q['bid']:$q['mark'];}
   else{$minute=(int)floor($at/60000)*60000;$args=['symbol'=>$symbol,'interval'=>'1m','startTime'=>$minute,'limit'=>1];$r=$market==='spot'?AIM_Trading::remote('klines',$args,86400):AIM_Futures::remote('markPriceKlines',$args,86400);if(is_wp_error($r))throw new RuntimeException($r->get_error_message());$bar=$r['data'][0]??[];if((int)($bar[0]??0)!==$minute)throw new RuntimeException('Thiếu giá tại mốc chốt Crypto.');$price=$bar[1]??null;}
  }elseif($market==='stock'&&isset(AIM_VN_Stock_Feed::map()[$symbol])){
   if($current&&AIM_VN_Stock_Feed::session($now)){$q=AIM_VN_Stock_Feed::quote($symbol);if(is_wp_error($q))throw new RuntimeException($q->get_error_message());$price=$q['bid'];}else{
   $historyKey='aimtr_sim_ssi_'.$symbol;$bars=get_transient($historyKey);
   if(!is_array($bars)){$req=new WP_REST_Request('GET');$req->set_param('symbol',$symbol);$req->set_param('board',AIM_VN_Stock_Feed::map()[$symbol]);$req->set_param('frame','1d');$r=DTTL_SSI_Charts::history_route($req);if(is_wp_error($r))throw new RuntimeException($r->get_error_message());$d=$r->get_data();$bars=$d['bars']??[];if($bars)set_transient($historyKey,$bars,300);}
   $last=0;foreach($bars as $bar){$time=(int)$bar['time'];if($time>100000000000)$time=(int)floor($time/1000);$date=(new DateTimeImmutable('@'.$time))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->setTime(15,0)->getTimestamp()*1000;if($date<=$at&&$date>=$last&&self::positive($bar['close']??null)){$price=round($bar['close']*0.9995,2);$last=$date;}}
   if(!$last||$at-$last>10*86400000)throw new RuntimeException('Chưa có giá đóng cửa SSI đúng kỳ cho '.$symbol.'.');}
  }elseif($current){
   if($market==='stock'){$q=AIM_Stock_Feed::quote($symbol);if(is_wp_error($q))throw new RuntimeException($q->get_error_message());$price=$q['bid'];}
   else{$class=$market==='forex'?'AIM_Forex_Feed':'AIM_Commodity_Feed';$r=$class::snapshot([$symbol]);if(is_wp_error($r))throw new RuntimeException($r->get_error_message());$q=$r['quotes'][$symbol]??null;$price=$q[$side==='long'?'bid':'ask']??null;}
  }elseif(class_exists('AIM_Reference_Feed')&&AIM_Reference_Feed::selected()){
   // Use only the configured source's published history. Never invent a historical mark.
   $rows=AIM_Reference_Feed::history($symbol);if(is_wp_error($rows))throw new RuntimeException($rows->get_error_message());$last=0;foreach($rows as $r)if(($r['time']??PHP_INT_MAX)<=$at&&$r['time']>$last){$last=$r['time'];$price=$r['close'];}if(!$last||$at-$last>96*3600000)throw new RuntimeException('Nguồn chưa đủ giá lịch sử cho '.$symbol.'.');
  }
  if(!self::positive($price))throw new RuntimeException('Chờ giá định giá '.$symbol.'.');$price=(float)$price;set_transient($cache,$price,$current?25:86400);self::$prices[$key]=$price;return $price;}catch(Throwable $e){set_transient($cache.'_error',$e->getMessage(),120);throw $e;}
 }
 static function one($account,$period,$at){
  $book=AIM_Sim_Performance_Engine::replay($account['ledger'],$account['events'],min($at,$period['ends_at']));if(!$book['traded'])return null;
  try{$full=AIM_Sim_Performance_Engine::replay($account['ledger'],$account['events'],self::now()+1000);if(isset($account['stored'])&&abs($full['cash']-(int)$account['stored']['cash'])>1)throw new RuntimeException('Số dư và sổ giao dịch cần đối soát.');foreach($account['stored_derivatives']??[] as $market=>$stored)if(is_array($stored)&&abs(($full['books'][$market]['cash']??0)-(int)($stored['cash']??0))>1)throw new RuntimeException('Sổ '.$market.' cần đối soát.');$row=AIM_Sim_Performance_Engine::result($account['ledger'],$account['events'],$period,$at,[__CLASS__,'price']);$row['pending']=$row['return_pct']===null;$row['reason']=$row['pending']?'Chưa có vốn đo lường hợp lệ.':'';}
  catch(Throwable $e){$row=['return_pct'=>null,'pending'=>true,'reason'=>$e->getMessage(),'fees'=>0,'weeks'=>1,'provisional'=>$at<$period['ends_at'],'completed_at'=>min($at,$period['ends_at']),'eligible'=>false];}
  $row['user_id']=$account['user_id'];$row['drawdown_pct']=null;return $row;
 }
 static function rank($period,$at=null,$global=[],$strict=false){
  $at=$at??self::now();self::$as_of=$at;$data=self::load();$aggregate=$period['period']!=='week';$rows=[];
  if(!$aggregate){foreach($data as $account){$r=self::one($account,$period,$at);if($r)$rows[$account['user_id']]=$r;}}
  else{
   $week=self::period('week',$period['starts_at']);$iterations=0;
   while($week['starts_at']<min($period['ends_at'],$at)&&++$iterations<=55){
    if($week['ends_at']-1>=$period['starts_at']&&$week['ends_at']-1<$period['ends_at']){
     $weekly=$global['competitions'][$week['id']]['snapshot']??self::rank($week,$at,$global,false);
     foreach($weekly['rows'] as $r){$uid=$r['user_id'];if(!isset($rows[$uid]))$rows[$uid]=['user_id'=>$uid,'return_pct'=>0.,'pending'=>false,'reason'=>'','fees'=>0,'weeks'=>0,'provisional'=>false,'completed_at'=>0,'eligible'=>true,'drawdown_pct'=>null];
      $rows[$uid]['pending']=$rows[$uid]['pending']||!empty($r['pending']);if(!empty($r['pending']))$rows[$uid]['reason']=$r['reason']??'Chờ định giá';else $rows[$uid]['return_pct']+=(float)$r['return_pct'];$rows[$uid]['fees']+=(int)$r['fees'];$rows[$uid]['weeks']++;$rows[$uid]['provisional']=$rows[$uid]['provisional']||!empty($r['provisional']);$rows[$uid]['completed_at']=max($rows[$uid]['completed_at'],(int)$r['completed_at']);
     }
    }$week=self::period('week',$week['ends_at']);
   }
  }
  $rows=array_values($rows);$pending=0;foreach($rows as &$r){if(!empty($r['pending'])){$r['return_pct']=null;$r['eligible']=false;$pending++;}else $r['return_pct']=round((float)$r['return_pct'],8);}unset($r);
  usort($rows,function($a,$b){if(($a['return_pct']===null)!==($b['return_pct']===null))return $a['return_pct']===null?1:-1;return ($b['return_pct']<=>$a['return_pct'])?:($a['user_id']<=>$b['user_id']);});$rank=0;foreach($rows as &$r)$r['rank']=$r['return_pct']===null?null:++$rank;unset($r);
  if($strict&&$pending)throw new RuntimeException('Còn '.$pending.' tài khoản chờ giá/đối soát; chưa chốt hoặc trao giải.');
  return ['rows'=>$rows,'participants'=>count($rows),'completed'=>count($rows)-$pending,'unfinished'=>$pending,'excluded'=>0,'aggregate'=>$aggregate,'pending_count'=>$pending];
 }
 static function active_today($at){$d=(new DateTimeImmutable('@'.(int)($at/1000)))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->setTime(0,0)->getTimestamp()*1000;$count=0;foreach(self::load() as $account){$active=false;foreach($account['ledger'] as $r)if($r['at']>=$d&&$r['at']<=$at&&AIM_Sim_Performance_Engine::trade($r['kind'])){$active=true;break;}if(!$active)foreach($account['events'] as $rows)foreach($rows as $row)if($row['at']>=$d&&$row['at']<=$at)foreach($row['events'] as $e)if(($e['kind']??'')==='open'||isset($e['pnl']))$active=true;if($active)$count++;}return $count;}
 static function nickname($uid,$profile,$mine=false){$u=get_userdata($uid);$name=$u?trim(wp_strip_all_tags($u->display_name)):'';if($name!==''&&!is_email($name))return $name;return 'Người chơi '.strtoupper(substr(hash_hmac('sha256',(string)$uid,wp_salt('auth')),0,4));}
}
