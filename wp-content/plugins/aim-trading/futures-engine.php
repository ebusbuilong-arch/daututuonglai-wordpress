<?php
/** Pure isolated linear-contract accounting. Prices are supplied by the trusted server adapter. */
defined('ABSPATH') || exit;
final class AIM_Futures_Liquidity extends RuntimeException {}
final class AIM_Futures_Engine {
 const U=1000000;
 const POLICY='isolated-linear-v1';
 const FEE=0.0005;
 const MMR=0.005;
 static function blank(){return array('cash'=>0,'margin_mode'=>'isolated','positions'=>array(),'orders'=>array(),'order_sequence'=>0,'sequence'=>0,'realized'=>0,'fees'=>0,'funding'=>0,'subsidy'=>0);}
 static function amount($x){if(!is_finite((float)$x)||abs($x)>9e14)throw new RuntimeException('Giá trị vượt giới hạn.');return (int)round($x);}
 static function units($raw){if(!is_string($raw)||!preg_match('/^\d{1,6}(\.\d{1,8})?$/D',$raw))throw new RuntimeException('Khối lượng tối đa 8 số lẻ.');$u=self::amount((float)$raw*1e8);if($u<=0)throw new RuntimeException('Khối lượng phải lớn hơn 0.');return $u;}
 static function pnl($p,$price,$units=null){return self::amount(($price-$p['entry'])*(($units??$p['units'])/1e8)*self::U*($p['side']==='long'?1:-1));}
 static function fee($units,$price){return (int)ceil($units/1e8*$price*self::U*self::FEE);}
 static function risk($p,$mark){
  $notional=$p['units']/1e8*$mark*self::U;$pnl=self::pnl($p,$mark);$equity=$p['margin']+$pnl;
  $required=(int)ceil($notional*self::MMR)+self::fee($p['units'],$mark);
  $q=$p['units']/1e8;$m=$p['margin']/self::U;
  $liq=$p['side']==='long'?($q*$p['entry']-$m)/($q*(1-self::MMR-self::FEE)):($q*$p['entry']+$m)/($q*(1+self::MMR+self::FEE));
  return array('pnl'=>$pnl,'equity'=>$equity,'maintenance'=>$required,'liquidation'=>max(0,$liq),'liquidatable'=>$equity<=$required);
 }
 // Each account execution pass consumes its own observed book; never invent tail liquidity.
 static function book($q){
  $out=array();
  foreach(array('bids','asks') as $side){
   $rows=$q[$side]??null;if(!is_array($rows)||!count($rows)||count($rows)>1000)throw new RuntimeException('Thiếu độ sâu sổ lệnh.');
   $last=null;$out[$side]=array();
   foreach($rows as $row){
    if(!is_array($row)||count($row)!==2||!is_numeric($row[0])||!is_string($row[1]))throw new RuntimeException('Sổ lệnh không hợp lệ.');
    $price=(float)$row[0];$u=self::units($row[1]);
    if(!is_finite($price)||$price<=0||$price>1e7||($last!==null&&($side==='bids'?$price>=$last:$price<=$last)))throw new RuntimeException('Thứ tự giá sổ lệnh không hợp lệ.');
    $out[$side][]=array('price'=>$price,'units'=>$u);$last=$price;
   }
  }
  if($out['asks'][0]['price']<$out['bids'][0]['price']||$out['asks'][0]['price']!=(float)$q['ask']||$out['bids'][0]['price']!=(float)$q['bid'])throw new RuntimeException('Sổ lệnh và giá đầu bảng không khớp.');
  return $out;
 }
 static function execution(&$q,$buy,$units,$limit=0){
  if($units<=0)throw new RuntimeException('Khối lượng không hợp lệ.');
  if(!isset($q['_depth']))$q['_depth']=self::book($q);
  $side=$buy?'asks':'bids';$rows=$q['_depth'][$side];$left=$units;$sum=0.0;$fills=array();
  foreach($rows as $i=>$row){
   if($limit>0&&($buy?$row['price']>$limit:$row['price']<$limit))break;
   $take=min($left,$row['units']);if(!$take)continue;
   $sum+=$take*$row['price'];$left-=$take;$rows[$i]['units']-=$take;$fills[]=array('price'=>$row['price'],'units'=>$take);if(!$left)break;
  }
  if($left)throw new AIM_Futures_Liquidity('Chưa đủ khối lượng trong sổ lệnh quan sát ở giá cho phép.');
  $q['_depth'][$side]=$rows;$price=count($fills)===1?$fills[0]['price']:$sum/$units;$best=(float)$q[$buy?'ask':'bid'];
  return array('price'=>$price,'best_price'=>$best,'slippage_bps'=>max(0,($buy?$price-$best:$best-$price)/$best*10000),'fills'=>$fills,'book_time'=>$q['book_time']??$q['time'],'book_id'=>$q['book_id']??0,'execution_policy'=>'depth-v1');
 }
 static function waiting($s){foreach($s['positions'] as $p)if(!empty($p['exit_pending']))return true;return !empty($s['cross_liquidating']);}
 static function close_book(&$s,$id,$units,&$quotes,$reason,&$events,$automatic=false){
  $p=$s['positions'][$id];if($units<=0||$units>$p['units'])throw new RuntimeException('Không đủ vị thế để đóng.');
  try{$fill=self::execution($quotes[$p['symbol']],$p['side']==='short',$units);}
  catch(AIM_Futures_Liquidity $x){
   if(!$automatic)throw $x;
   if(($p['exit_pending']??'')!==$reason){$s['positions'][$id]['exit_pending']=$reason;$events[]=array('kind'=>'exit_waiting','position_id'=>$id,'symbol'=>$p['symbol'],'reason'=>$reason,'pool_delta'=>0);}
   return false;
  }
  self::close($s,$id,$units,$fill['price'],$reason,$events);$last=count($events)-1;$events[$last]=array_merge($events[$last],$fill);return true;
 }
 static function bracket($a,$side,$mark){
  $out=array('tp'=>0.0,'sl'=>0.0);foreach(array('tp','sl') as $key){$v=$a[$key]??'0';if(!is_string($v)||!preg_match('/^\d{1,8}(\.\d{1,8})?$/D',$v)||(float)$v>1e7)throw new RuntimeException('TP/SL kèm lệnh không hợp lệ.');$out[$key]=(float)$v;}
  if(($out['tp']>0&&($side==='long'?$out['tp']<=$mark:$out['tp']>=$mark))||($out['sl']>0&&($side==='long'?$out['sl']>=$mark:$out['sl']<=$mark)))throw new RuntimeException('TP/SL kèm lệnh đã bị vượt hoặc nằm sai phía mốc tham chiếu.');return $out;
 }
 static function cross($s){return ($s['margin_mode']??'isolated')==='cross';}
 static function cross_risk($s,$quotes){
  $reserved=array_sum(array_column($s['orders']??array(),'reserved'));$pnl=0;$initial=0;$maintenance=0;
  foreach($s['positions'] as $p){$q=$quotes[$p['symbol']]??null;if(!$q)throw new RuntimeException('Thiếu giá để đánh giá toàn bộ tài khoản Cross.');$notional=$p['units']/1e8*$q['mark']*self::U;$pnl+=self::pnl($p,$q['mark']);$initial+=(int)ceil($notional/$p['leverage']);$maintenance+=(int)ceil($notional*self::MMR)+self::fee($p['units'],$q['mark']);}
  $wallet=$s['cash']+$reserved;$equity=$wallet+$pnl;
  // Gains support account equity, but are not withdrawable/spendable before realization.
  $available=max(0,min($s['cash']-$initial,$equity-$initial-$reserved));
  return array('wallet'=>$wallet,'equity'=>$equity,'pnl'=>$pnl,'initial'=>$initial,'maintenance'=>$maintenance,'reserved'=>$reserved,'available'=>$available,'liquidatable'=>count($s['positions'])>0&&$equity<=$maintenance);
 }
 static function available($s,$quotes){if(self::waiting($s))return 0;return self::cross($s)?self::cross_risk($s,$quotes)['available']:$s['cash'];}
 static function cross_guard(&$s,&$quotes,&$events){
  if(!self::cross($s))return;
  $r=self::cross_risk($s,$quotes);
  if($r['liquidatable']||!empty($s['cross_liquidating'])){
   $s['cross_liquidating']=true;
   foreach(array_keys($s['orders']) as $id)self::release_order($s,$id,'order_cancelled',$events,'Hủy khi thanh lý Cross.');
   foreach(array_keys($s['positions']) as $id){$p=$s['positions'][$id];$q=$quotes[$p['symbol']];self::close_book($s,$id,$p['units'],$quotes,'cross_liquidation',$events,true);}
  }
  if(!$s['positions'])unset($s['cross_liquidating']);
  if(!$s['positions']&&$s['cash']<0){
   foreach(array_keys($s['orders']) as $id)self::release_order($s,$id,'order_cancelled',$events,'Giải phóng khoản giữ để đối soát Cross.');
   if($s['cash']<0){$amount=-$s['cash'];$s['cash']=0;$s['subsidy']+=$amount;$s['realized']+=$amount;$events[]=array('kind'=>'cross_subsidy','amount'=>$amount,'subsidy'=>$amount,'pool_delta'=>-$amount);}
  }
 }
 static function close(&$s,$id,$units,$price,$reason,&$events){
  $p=$s['positions'][$id];if($units<=0||$units>$p['units'])throw new RuntimeException('Không đủ vị thế để đóng.');
  $margin=$units===$p['units']?$p['margin']:self::amount($p['margin']*$units/$p['units']);
  $pnl=self::pnl($p,$price,$units);$fee=self::fee($units,$price);$raw=$margin+$pnl-$fee;
  // Isolated protection: only allocated margin is exposed; gap shortfall is explicit system subsidy.
  $paid=self::cross($s)?$raw:max(0,$raw);$subsidy=self::cross($s)?0:max(0,-$raw);$s['cash']+=$paid;$s['realized']+=$pnl-$fee+$subsidy;$s['fees']+=$fee;$s['subsidy']+=$subsidy;
  $s['positions'][$id]['units']-=$units;$s['positions'][$id]['margin']-=$margin;
  if(!$s['positions'][$id]['units'])unset($s['positions'][$id]);
  $events[]=array('kind'=>$reason,'margin_mode'=>$s['margin_mode']??'isolated','position_id'=>$id,'symbol'=>$p['symbol'],'side'=>$p['side'],'units'=>$units,'price'=>$price,'margin'=>$margin,'pnl'=>$pnl,'fee'=>$fee,'subsidy'=>$subsidy,'trailing_stop'=>$p['trailing']['stop']??0,'trailing_extreme'=>$p['trailing']['extreme']??0,'pool_delta'=>$margin-$paid);
 }
 static function trail(&$p,$q,&$events){
  if(empty($p['trailing']))return false;
  $t=&$p['trailing'];
  // Never use an older or duplicate source observation to move/trigger a trail.
  if($q['time']<=$t['time'])return false;$t['time']=$q['time'];$mark=$q['mark'];
  if(!$t['active']){
   if(!($p['side']==='long'?$mark>=$t['activation']:$mark<=$t['activation']))return false;
   $t['active']=true;$t['extreme']=$mark;
   $events[]=array('kind'=>'trailing_activated','position_id'=>$p['id'],'symbol'=>$p['symbol'],'price'=>$mark,'pool_delta'=>0);
  }
  $t['extreme']=$p['side']==='long'?max($t['extreme'],$mark):min($t['extreme'],$mark);
  $t['stop']=$t['extreme']*(1+($p['side']==='long'?-1:1)*$t['callback']/100);
  return $p['side']==='long'?$mark<=$t['stop']:$mark>=$t['stop'];
 }
 static function settle(&$s,&$quotes,$funding,&$events){
  foreach(array_keys($s['positions']) as $id){
   $p=&$s['positions'][$id];$q=$quotes[$p['symbol']]??null;if(!$q)throw new RuntimeException('Thiếu giá phái sinh.');
   $rows=$funding[$p['symbol']]??array();
   foreach($rows as $f){
    if($f['time']<=$p['cursor']||$f['time']>$q['time'])continue;
    $delta=-self::amount($p['units']/1e8*$f['mark']*self::U*$f['rate']*($p['side']==='long'?1:-1));
    if(self::cross($s))$s['cash']+=$delta;else $p['margin']+=$delta;$p['cursor']=$f['time'];$s['funding']+=$delta;$s['realized']+=$delta;
    $events[]=array('kind'=>'funding','position_id'=>$id,'symbol'=>$p['symbol'],'time'=>$f['time'],'rate'=>$f['rate'],'price'=>$f['mark'],'cash_delta'=>$delta,'pool_delta'=>-$delta);
   }
   if($p['next_funding']<=$q['time']&&$p['cursor']<$p['next_funding'])throw new RuntimeException('Chờ nguồn công bố funding đã đến hạn.');
   $p['next_funding']=$q['next_funding'];unset($p);
  }
  self::cross_guard($s,$quotes,$events);
  foreach(array_keys($s['positions']) as $id){
   if(!isset($s['positions'][$id]))continue;$p=&$s['positions'][$id];$q=$quotes[$p['symbol']];$risk=self::risk($p,$q['mark']);$price=$p['side']==='long'?$q['bid']:$q['ask'];$reason='';$trail=self::trail($p,$q,$events);
   if(!self::cross($s)&&$risk['liquidatable'])$reason='liquidation';
   elseif(!empty($p['exit_pending']))$reason=$p['exit_pending'];
   elseif($p['sl']>0&&($p['side']==='long'?$q['mark']<=$p['sl']:$q['mark']>=$p['sl']))$reason='stop_loss';
   elseif($p['tp']>0&&($p['side']==='long'?$q['mark']>=$p['tp']:$q['mark']<=$p['tp']))$reason='take_profit';
   elseif($trail)$reason='trailing_stop';
   $units=$p['units'];unset($p);if($reason){self::close_book($s,$id,$units,$quotes,$reason,$events,true);self::cross_guard($s,$quotes,$events);}
  }
 }
 static function release_order(&$s,$id,$kind,&$events,$reason=''){
  $o=$s['orders'][$id];$s['cash']+=$o['reserved'];unset($s['orders'][$id]);
  $events[]=array('kind'=>$kind,'order_id'=>(string)$id,'symbol'=>$o['symbol'],'side'=>$o['side'],'amount'=>$o['reserved'],'reason'=>$reason,'pool_delta'=>0);
 }
 static function pending(&$s,&$quotes,$at,&$events,$allow_open=true){
  foreach(array_keys($s['orders']) as $id){
   $o=$s['orders'][$id];if($o['expires_at']&&$o['expires_at']<=$at){self::release_order($s,$id,'order_expired',$events);continue;}
   $q=$quotes[$o['symbol']]??null;if(!$q||!$allow_open||self::waiting($s))continue;
   if($o['order_type']!=='limit'&&!$o['activated']){
    if(!($o['side']==='long'?$q['mark']>=$o['trigger']:$q['mark']<=$o['trigger']))continue;
    $o['activated']=true;$s['orders'][$id]=$o;$events[]=array('kind'=>'order_triggered','order_id'=>(string)$id,'symbol'=>$o['symbol'],'price'=>$q['mark'],'pool_delta'=>0);
   }
   $price=$o['side']==='long'?$q['ask']:$q['bid'];
   if($o['order_type']!=='stop_market'&&!($o['side']==='long'?$price<=$o['limit']:$price>=$o['limit']))continue;
   $candidate=$s;$candidate['cash']+=$o['reserved'];unset($candidate['orders'][$id]);
   try{
    // Reuse exactly the Market accounting; failed fills release the entire hold, never consume other reservations.
    $r=self::apply($candidate,array('type'=>'open','symbol'=>$o['symbol'],'side'=>$o['side'],'quantity'=>$o['quantity'],'leverage'=>$o['leverage'],'opened_at'=>$at,'skip_pending'=>true,'skip_settle'=>true,'tp'=>number_format((float)($o['tp']??0),8,'.',''),'sl'=>number_format((float)($o['sl']??0),8,'.',''),'execution_limit'=>$o['order_type']==='stop_market'?0:$o['limit']),$quotes);
    $s=$r['state'];$quotes=$r['remaining_quotes'];foreach($r['events'] as $e){$e['order_id']=(string)$id;$events[]=$e;}
   }catch(AIM_Futures_Liquidity $x){continue;}catch(RuntimeException $x){self::release_order($s,$id,'order_rejected',$events,$x->getMessage());}
  }
 }
 static function apply($state,$action,$quotes=array(),$funding=array()){
  $s=$state;$s['orders']=$s['orders']??array();$s['order_sequence']=$s['order_sequence']??0;$events=array();$type=$action['type']??'';$at=(int)($action['now']??$action['opened_at']??0);
  if(!$at&&$quotes)$at=max(array_column($quotes,'time'));
  if($type==='set_mode'&&($s['positions']||$s['orders']))throw new RuntimeException('Đóng hết vị thế và hủy lệnh chờ trước khi đổi chế độ.');
  $offline=in_array($type,array('cancel_order','sweep'),true);
  if(!$offline&&empty($action['skip_settle']))self::settle($s,$quotes,$funding,$events);
  if(empty($action['skip_pending']))self::pending($s,$quotes,$at,$events,!$offline&&($action['allow_pending']??true));
  if(in_array($type,array('open','place_order'),true)&&self::waiting($s))throw new RuntimeException('Có vị thế chờ đóng do thiếu thanh khoản; tạm dừng mở mới.');
  if($type==='set_mode'){
   $mode=$action['mode']??'';if(!in_array($mode,array('cross','isolated'),true))throw new RuntimeException('Chế độ ký quỹ không hợp lệ.');
   if($s['positions']||$s['orders'])throw new RuntimeException('Đóng hết vị thế và hủy lệnh chờ trước khi đổi chế độ.');
   $s['margin_mode']=$mode;$events[]=array('kind'=>'margin_mode','mode'=>$mode,'pool_delta'=>0);
  }elseif($type==='cancel_order'){
   $id=(string)($action['order_id']??'');
   if(isset($s['orders'][$id]))self::release_order($s,$id,'order_cancelled',$events);
   else{ $found=false;foreach($events as $e)if(($e['order_id']??'')===$id)$found=true;if(!$found)throw new RuntimeException('Lệnh không còn chờ hoặc không thuộc tài khoản.'); }
  }elseif($type==='place_order'){
   $symbol=$action['symbol']??'';$side=$action['side']??'';$lev=$action['leverage']??0;$q=$quotes[$symbol]??null;$ot=$action['order_type']??'';
   if(!$q||!in_array($side,array('long','short'),true)||!is_int($lev)||$lev<1||$lev>20||!in_array($ot,array('limit','stop_market','stop_limit'),true))throw new RuntimeException('Thông tin lệnh chờ không hợp lệ.');
   $limit=$action['limit']??0;$trigger=$action['trigger']??0;
   foreach(array($limit,$trigger) as $v)if(!is_string($v)||!preg_match('/^\d{1,8}(\.\d{1,8})?$/D',$v)||(float)$v>1e7)throw new RuntimeException('Giá tối đa 8 số lẻ.');
   $limit=(float)$limit;$trigger=(float)$trigger;
   if(($ot!=='stop_market'&&$limit<=0)||($ot!=='limit'&&$trigger<=0))throw new RuntimeException('Thiếu giá giới hạn/kích hoạt.');
   if($ot!=='limit'&&($side==='long'?$trigger<=$q['mark']:$trigger>=$q['mark']))throw new RuntimeException('Stop Long cần trên Mark hiện tại; Stop Short cần dưới Mark hiện tại.');
   $expiry=$action['expires_at']??0;if(!is_int($expiry)||($expiry!==0&&($expiry<$at+15000||$expiry>$at+30*86400000)))throw new RuntimeException('Thời hạn phải từ 15 giây đến 30 ngày.');
   $units=self::units($action['quantity']??'');$reference=$ot==='stop_market'?$trigger:$limit;$notional=self::amount($units/1e8*$reference*self::U);
   $total=$notional;foreach($s['positions'] as $p)$total+=self::amount($p['units']/1e8*$quotes[$p['symbol']]['mark']*self::U);foreach($s['orders'] as $o)$total+=$o['notional'];
   if(count($s['positions'])+count($s['orders'])>=20||$notional<5*self::U||$total>100000*self::U)throw new RuntimeException('Tối đa 20 vị thế/lệnh; mỗi lệnh từ 5 AIM, tổng quy mô 100.000 AIM.');
   $reserved=(int)ceil($notional/$lev)+self::fee($units,$reference);if(self::available($s,$quotes)<$reserved)throw new RuntimeException('Không đủ AIM khả dụng để giữ cho lệnh.');
   $bracket=self::bracket($action,$side,$ot==='stop_market'?$trigger:$limit);$id=(string)++$s['order_sequence'];$s['cash']-=$reserved;
   $s['orders'][$id]=array('id'=>$id,'symbol'=>$symbol,'side'=>$side,'quantity'=>$action['quantity'],'units'=>$units,'leverage'=>$lev,'order_type'=>$ot,'limit'=>$limit,'trigger'=>$trigger,'activated'=>false,'expires_at'=>$expiry,'reserved'=>$reserved,'notional'=>$notional)+$bracket;
   $events[]=array('kind'=>'order_placed','order_id'=>$id,'symbol'=>$symbol,'side'=>$side,'order_type'=>$ot,'price'=>$reference,'units'=>$units,'amount'=>$reserved,'expires_at'=>$expiry,'pool_delta'=>0);
  }elseif($type==='open'){
   $symbol=$action['symbol']??'';$side=$action['side']??'';$lev=$action['leverage']??0;$q=$quotes[$symbol]??null;
   if(!$q||!in_array($side,array('long','short'),true)||!is_int($lev)||$lev<1||$lev>20)throw new RuntimeException('Mã, chiều hoặc đòn bẩy không hợp lệ.');
   if(count($s['positions'])+count($s['orders'])>=20)throw new RuntimeException('Tối đa 20 vị thế và lệnh chờ.');
   $units=self::units($action['quantity']??'');$fill=self::execution($quotes[$symbol],$side==='long',$units,(float)($action['execution_limit']??0));$price=$fill['price'];$notional=self::amount($units/1e8*$price*self::U);
   $total=$notional;foreach($s['positions'] as $p)$total+=self::amount($p['units']/1e8*$quotes[$p['symbol']]['mark']*self::U);foreach($s['orders'] as $o)$total+=$o['notional'];
   if($notional<5*self::U||$total>100000*self::U)throw new RuntimeException('Từ 5 AIM/hợp đồng, tổng quy mô tối đa 100.000 AIM.');
   $margin=(int)ceil($notional/$lev);$fee=self::fee($units,$price);
   if(self::available($s,$quotes)<$margin+$fee)throw new RuntimeException('Không đủ AIM phái sinh cho ký quỹ và phí.');
   $bracket=self::bracket($action,$side,$q['mark']);$id=(string)++$s['sequence'];$p=array('id'=>$id,'symbol'=>$symbol,'side'=>$side,'units'=>$units,'entry'=>$price,'margin'=>self::cross($s)?0:$margin,'margin_mode'=>$s['margin_mode']??'isolated','leverage'=>$lev,'tp'=>0,'sl'=>0,'cursor'=>max($q['time'],(int)($action['opened_at']??0)),'next_funding'=>$q['next_funding'],'policy'=>self::cross($s)?'cross-linear-v1':self::POLICY);
   if(!self::cross($s)&&self::risk($p,$q['mark'])['liquidatable'])throw new RuntimeException('Chênh lệch giá khiến vị thế không đủ ký quỹ.');
   $p['tp']=$bracket['tp'];$p['sl']=$bracket['sl'];$s['positions'][$id]=$p;$s['cash']-=(self::cross($s)?0:$margin)+$fee;if(self::cross($s)&&self::cross_risk($s,$quotes)['equity']<self::cross_risk($s,$quotes)['initial']+self::cross_risk($s,$quotes)['reserved'])throw new RuntimeException('Không đủ vốn Cross sau chênh lệch giá.');$s['fees']+=$fee;$s['realized']-=$fee;
   $events[]=array('kind'=>'open','margin_mode'=>$s['margin_mode']??'isolated','position_id'=>$id,'symbol'=>$symbol,'side'=>$side,'units'=>$units,'price'=>$price,'margin'=>$margin,'fee'=>$fee,'pool_delta'=>$fee)+$fill+$bracket;
  }elseif(in_array($type,array('close','margin','protect','trailing'),true)){
   $id=(string)($action['position_id']??'');
   // Automatic risk processing wins over a simultaneous manual action; return its persisted result.
   if(!isset($s['positions'][$id])){$closed=false;foreach($events as $e)if((string)($e['position_id']??'')===$id&&in_array($e['kind'],array('liquidation','cross_liquidation','stop_loss','take_profit','trailing_stop'),true))$closed=true;if(!$closed)throw new RuntimeException('Vị thế đã đóng hoặc không thuộc tài khoản.');return array('state'=>$s,'events'=>$events,'remaining_quotes'=>$quotes);}
   $p=$s['positions'][$id];$q=$quotes[$p['symbol']];
   if($type==='close'){self::close_book($s,$id,self::units($action['quantity']??''),$quotes,$p['exit_pending']??'close',$events);}
   elseif(!empty($p['exit_pending'])){throw new RuntimeException('Vị thế đã kích hoạt đóng; chờ thanh khoản hoặc đóng từng phần.');}
   elseif($type==='margin'){
    if(self::cross($s))throw new RuntimeException('Cross dùng vốn chung. Chuyển AIM vào/ra tài khoản thay vì sửa ký quỹ từng vị thế.');
    $delta=$action['amount']??null;if(!is_int($delta)||!$delta||abs($delta)>100000*self::U)throw new RuntimeException('Số ký quỹ không hợp lệ.');
    if($delta>$s['cash'])throw new RuntimeException('Không đủ AIM khả dụng.');
    $p['margin']+=$delta;$risk=self::risk($p,$q['mark']);
    if($p['margin']<=0||$risk['equity']<ceil($p['units']/1e8*$q['mark']*self::U/$p['leverage'])||$risk['liquidatable'])throw new RuntimeException('Phần ký quỹ còn lại không đủ.');
    $s['cash']-=$delta;$s['positions'][$id]=$p;$events[]=array('kind'=>'margin','position_id'=>$id,'amount'=>$delta,'pool_delta'=>0);
   }elseif($type==='trailing'){
    $callback=$action['callback']??null;$activation=$action['activation']??null;
    foreach(array($callback,$activation) as $v)if(!is_string($v)||!preg_match('/^\d{1,8}(\.\d{1,8})?$/D',$v))throw new RuntimeException('Thông số trailing không hợp lệ.');
    $callback=(float)$callback;$activation=(float)$activation;
    if(($callback!==0.0&&($callback<0.1||$callback>10))||$activation>1e7)throw new RuntimeException('Khoảng lùi từ 0,1% đến 10%; nhập 0 để bỏ trailing.');
    if($callback===0.0){unset($s['positions'][$id]['trailing']);$events[]=array('kind'=>'trailing_cancelled','position_id'=>$id,'pool_delta'=>0);}
    else{
     if($activation>0&&($p['side']==='long'?$activation<=$q['mark']:$activation>=$q['mark']))throw new RuntimeException('Kích hoạt Long cần trên Mark, Short cần dưới Mark; nhập 0 để bắt đầu ngay.');
     $active=$activation===0.0;$extreme=$active?$q['mark']:0;
     $s['positions'][$id]['trailing']=array('callback'=>$callback,'activation'=>$activation,'active'=>$active,'extreme'=>$extreme,'stop'=>$active?$extreme*(1+($p['side']==='long'?-1:1)*$callback/100):0,'time'=>$q['time']);
     $events[]=array('kind'=>'trailing_set','position_id'=>$id,'symbol'=>$p['symbol'],'callback'=>$callback,'activation'=>$activation,'price'=>$q['mark'],'pool_delta'=>0);
    }
   }else{
    $tp=$action['tp']??null;$sl=$action['sl']??null;
    foreach(array($tp,$sl) as $v)if(!is_numeric($v)||!is_finite((float)$v)||$v<0||$v>1e7)throw new RuntimeException('TP/SL không hợp lệ.');
    if(($tp>0&&($p['side']==='long'?$tp<=$q['mark']:$tp>=$q['mark']))||($sl>0&&($p['side']==='long'?$sl>=$q['mark']:$sl<=$q['mark'])))throw new RuntimeException('TP và SL cần nằm đúng phía Mark Price hiện tại.');
    $s['positions'][$id]['tp']=(float)$tp;$s['positions'][$id]['sl']=(float)$sl;$events[]=array('kind'=>'protect','position_id'=>$id,'tp'=>(float)$tp,'sl'=>(float)$sl,'pool_delta'=>0);
   }
  }elseif(!in_array($type,array('tick','sweep'),true))throw new RuntimeException('Thao tác không hợp lệ.');
  if(!$offline)self::cross_guard($s,$quotes,$events);
  return array('state'=>$s,'events'=>$events,'remaining_quotes'=>$quotes);
 }
}
