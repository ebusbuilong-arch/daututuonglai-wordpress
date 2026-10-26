<?php
/** Pure, isolated Forex simulator. No database, wallet or broker writes. */
defined('ABSPATH') || exit;
require_once __DIR__.'/forex-feed.php';
final class AIM_Forex_Engine {
 const U=1000000;
 const POLICY='forex-isolated-usd1000-v2';
 const USD_PER_AIM=1000;
 // Explicit AIM simulation rules, not a claim about a broker's trading conditions.
 const COMMISSION_PER_LOT_SIDE=3.5;
 const STOP_OUT=0.5;
 static function need($ok,$message){if(!$ok)throw new RuntimeException($message);}
 static function money($value){self::need(is_finite((float)$value)&&abs($value)<9e14,'Giá trị hạch toán vượt giới hạn.');return (int)round($value);}
 static function blank($cash=0){self::need(is_int($cash)&&$cash>=0&&$cash<=1000000*self::U,'Vốn kiểm thử không hợp lệ.');return array('policy'=>self::POLICY,'cash'=>$cash,'positions'=>array(),'sequence'=>0,'realized'=>0,'fees'=>0,'subsidy'=>0,'pool_delta'=>0);}
 static function fee($units){return (int)ceil($units/100000*self::COMMISSION_PER_LOT_SIDE*self::U/self::USD_PER_AIM);}
 static function pnl($p,$price,$units=null){return self::money(($price-$p['entry'])*($units??$p['units'])*self::U/self::USD_PER_AIM*($p['side']==='long'?1:-1));}
 static function quote($q,$symbol,$now){
  self::need(is_int($now)&&is_array($q)&&($q['symbol']??null)===$symbol&&!empty($q['tradeable']),'Thiếu báo giá giao dịch đúng mã.');
  foreach(array('time','received') as $key)self::need(isset($q[$key])&&is_int($q[$key]),'Thiếu mốc thời gian báo giá.');
  self::need(AIM_Reference_Feed::fresh($q,$now),'Báo giá cũ; tạm dừng xử lý vị thế.');
  foreach(array('bids','asks') as $side){
   self::need(isset($q[$side])&&is_array($q[$side])&&count($q[$side])>0&&count($q[$side])<=100,'Thiếu độ sâu báo giá.');$last=null;
   foreach($q[$side] as $row){$price=AIM_Forex_Feed::number($row['price']??null);$units=AIM_Forex_Feed::number($row['units']??null);self::need($price<100&&($last===null||($side==='bids'?$price<$last:$price>$last)),'Độ sâu sai thứ tự.');$last=$price;}
  }
  self::need(isset($q['bid'],$q['ask'])&&$q['bid']===$q['bids'][0]['price']&&$q['ask']===$q['asks'][0]['price']&&$q['ask']>=$q['bid'],'Báo giá đầu bảng không khớp.');
 }
 static function bracket($side,$reference,$spec,$tp=null,$sl=null){
  $out=array('tp'=>null,'sl'=>null);
  foreach(array('tp'=>$tp,'sl'=>$sl) as $key=>$raw){if($raw===null||$raw==='')continue;
   self::need(is_string($raw)&&preg_match('/^\d{1,2}(?:\.\d{1,8})?$/D',$raw),'TP/SL phải là giá hợp lệ.');$v=(float)$raw;
   self::need($v>0&&$v<100&&abs($v/$spec['tick']-round($v/$spec['tick']))<0.00001,'TP/SL sai bước giá.');
   $above=($side==='long'&&$key==='tp')||($side==='short'&&$key==='sl');
   self::need($above?$v>$reference:$v<$reference,'TP/SL nằm sai phía hoặc đã bị vượt.');$out[$key]=$v;
  }return $out;
 }
 static function open($state,$q,$spec,$side,$lots,$leverage,$now,$tp=null,$sl=null){
  self::need(($state['policy']??'')===self::POLICY&&count($state['positions'])<20,'Không đúng chính sách hoặc đã đủ 20 vị thế.');
  foreach($state['positions'] as $p)self::need(empty($p['exit_pending']),'Còn vị thế chờ đóng; chưa thể mở thêm.');
  self::quote($q,$spec['symbol'],$now);$fill=AIM_Forex_Feed::execution($q,$spec,$side,$lots,$leverage,$now);
  $bracket=self::bracket($side,$q[$side==='long'?'bid':'ask'],$spec,$tp,$sl);$fee=self::fee($fill['units']);
  self::need($state['cash']>=$fill['margin']+$fee,'Không đủ AIM khả dụng cho ký quỹ và phí.');
  $id=++$state['sequence'];$p=array('id'=>$id,'source'=>$q['source']??'OANDA Practice','symbol'=>$q['symbol'],'side'=>$side,'units'=>$fill['units'],'entry'=>$fill['price'],'margin'=>$fill['margin'],'opened'=>$now,'last_quote'=>$q['time'],'effective_leverage'=>$fill['effective_leverage'],'spec'=>$spec,'tp'=>$bracket['tp'],'sl'=>$bracket['sl'],'exit_pending'=>null);
  self::need(!self::risk($p,$q,$now)['liquidatable'],'Spread quá lớn so với ký quỹ ban đầu.');
  $state['cash']-=$p['margin']+$fee;$state['fees']+=$fee;$state['pool_delta']+=$fee;$state['positions'][$id]=$p;
  return array('state'=>$state,'events'=>array(array('kind'=>'open','id'=>$id,'symbol'=>$p['symbol'],'side'=>$side,'price'=>$p['entry'],'units'=>$p['units'],'margin'=>$p['margin'],'fee'=>$fee,'pool_delta'=>$fee,'fills'=>$fill['fills'],'quote_time'=>$q['time'])));
 }
 static function risk($p,$q,$now){
  self::quote($q,$p['symbol'],$now);self::need(($p['source']??'OANDA Practice')===($q['source']??'OANDA Practice'),'Nguồn giá vị thế khác cấu hình; cần đối soát trước khi đổi nguồn.');self::need($q['time']>=$p['last_quote'],'Báo giá đến sai thứ tự.');$price=$q[$p['side']==='long'?'bid':'ask'];$pnl=self::pnl($p,$price);$fee=self::fee($p['units']);
  $equity=$p['margin']+$pnl-$fee;$maintenance=(int)ceil($p['margin']*self::STOP_OUT);
  return array('reference'=>$price,'pnl'=>$pnl,'estimated_close_fee'=>$fee,'equity_after_close_fee'=>$equity,'maintenance'=>$maintenance,'margin_level_percent'=>100*$equity/$p['margin'],'liquidatable'=>$equity<=$maintenance);
 }
 static function triggers($p,$q,$now){
  $r=self::risk($p,$q,$now);if($r['liquidatable'])return 'liquidation';
  if(!empty($p['exit_pending']))return $p['exit_pending'];$price=$r['reference'];
  if($p['sl']!==null&&($p['side']==='long'?$price<=$p['sl']:$price>=$p['sl']))return 'stop_loss';
  if($p['tp']!==null&&($p['side']==='long'?$price>=$p['tp']:$price<=$p['tp']))return 'take_profit';return null;
 }
 // A shared book is consumed once per evaluation pass, including multiple positions.
 static function fill(&$q,$buy,$units){
  $side=$buy?'asks':'bids';$rows=$q['_remaining'][$side]??$q[$side];$left=$units;$sum=0;$fills=array();
  foreach($rows as $i=>$row){$take=min($left,$row['units']);if($take<=0)continue;$sum+=$take*$row['price'];$left-=$take;$rows[$i]['units']-=$take;$fills[]=array('price'=>$row['price'],'units'=>$take);if($left<1e-7)break;}
  if($left>=1e-7)return null;$q['_remaining'][$side]=$rows;return array('price'=>$sum/$units,'fills'=>$fills);
 }
 static function close_into(&$state,$id,$units,&$q,$now,$reason,&$events,$automatic=false){
  $p=$state['positions'][$id]??null;self::need(is_array($p),'Vị thế không tồn tại hoặc đã đóng.');self::quote($q,$p['symbol'],$now);
  self::need((is_int($units)||is_float($units))&&is_finite((float)$units)&&$units>0&&$units<=$p['units'],'Khối lượng đóng không hợp lệ.');
  $step=pow(10,$p['spec']['unit_precision']);$remaining=$p['units']-$units;
  self::need(abs($units*$step-round($units*$step))<1e-7&&($remaining<1e-7||$remaining>=$p['spec']['min_units']),'Khối lượng đóng hoặc còn lại sai bước.');
  self::need($units>=$p['spec']['min_units'],'Khối lượng đóng nhỏ hơn tối thiểu.');
  $fill=self::fill($q,$p['side']==='short',$units);
  if($fill===null){if(!$automatic)throw new RuntimeException('Thiếu thanh khoản để đóng; vị thế giữ nguyên.');$state['positions'][$id]['exit_pending']=$reason;$events[]=array('kind'=>'exit_waiting','id'=>$id,'reason'=>$reason,'pool_delta'=>0);return;}
  $margin=$remaining<1e-7?$p['margin']:(int)floor($p['margin']*$units/$p['units']);$pnl=self::pnl($p,$fill['price'],$units);$fee=self::fee($units);$raw=$margin+$pnl-$fee;
  $subsidy=max(0,-$raw);$returned=max(0,$raw);$pool=$margin-$returned;
  $state['cash']+=$returned;$state['realized']+=$pnl;$state['fees']+=$fee;$state['subsidy']+=$subsidy;$state['pool_delta']+=$pool;
  if($remaining<1e-7)unset($state['positions'][$id]);else{$state['positions'][$id]['units']=$remaining;$state['positions'][$id]['margin']-=$margin;$state['positions'][$id]['last_quote']=$q['time'];}
  $events[]=array('kind'=>'close','id'=>$id,'symbol'=>$p['symbol'],'reason'=>$reason,'price'=>$fill['price'],'units'=>$units,'released_margin'=>$margin,'pnl'=>$pnl,'fee'=>$fee,'returned'=>$returned,'subsidy'=>$subsidy,'pool_delta'=>$pool,'fills'=>$fill['fills'],'quote_time'=>$q['time']);
 }
 static function close($state,$id,$units,$q,$now){
  $p=$state['positions'][$id]??null;self::need(is_array($p),'Vị thế không tồn tại.');$reason=self::triggers($p,$q,$now);
  self::need($reason===null||abs($units-$p['units'])<1e-7,'Vị thế đã chạm ngưỡng phải đóng toàn bộ.');$events=array();
  self::close_into($state,$id,$units,$q,$now,$reason??'manual',$events);return array('state'=>$state,'events'=>$events);
 }
 static function amend($state,$id,$q,$now,$tp=null,$sl=null){
  $p=$state['positions'][$id]??null;self::need(is_array($p),'Vị thế không tồn tại.');self::need(self::triggers($p,$q,$now)===null,'Vị thế đã chạm ngưỡng đóng; không thể sửa TP/SL.');
  $b=self::bracket($p['side'],$q[$p['side']==='long'?'bid':'ask'],$p['spec'],$tp,$sl);$state['positions'][$id]['tp']=$b['tp'];$state['positions'][$id]['sl']=$b['sl'];$state['positions'][$id]['last_quote']=$q['time'];return $state;
 }
 static function evaluate($state,$quotes,$now){
  // Preflight all positions; missing/stale quotes cannot commit a partial evaluation.
  foreach($state['positions'] as $p)self::quote($quotes[$p['symbol']]??null,$p['symbol'],$now);
  $events=array();foreach($state['positions'] as $id=>$p){$reason=self::triggers($p,$quotes[$p['symbol']],$now);if($reason!==null)self::close_into($state,$id,$p['units'],$quotes[$p['symbol']],$now,$reason,$events,true);if(isset($state['positions'][$id]))$state['positions'][$id]['last_quote']=$quotes[$p['symbol']]['time'];}
  return array('state'=>$state,'events'=>$events);
 }
}
