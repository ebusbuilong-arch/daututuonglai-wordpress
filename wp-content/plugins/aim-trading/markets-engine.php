<?php
/** Multi-market teaching terminal. Deterministic scenarios, never live quotes. */
defined('ABSPATH') || exit;
final class AIM_Markets_Engine {
 const VERSION='markets-lesson-v1';
 const UNIT=1000000;
 static function catalog(){return array(
  'stock'=>array('title'=>'Cổ phiếu Việt Nam','symbol'=>'VN-A','name'=>'Doanh nghiệp giả định A','unit'=>'cổ phiếu','quote'=>'VND / cổ phiếu','multiplier'=>1,'conversion'=>1000,'step'=>100,'tick'=>100,'spread'=>200,'max_leverage'=>1,'prices'=>array(50000,51000,49000,48000,49500,52000,50500,53000,51500),'days'=>array(0,0,1,1,2,3,4,5,6),'times'=>array('09:30','13:30','09:30','13:30','13:00','13:30','13:30','13:30','13:30'),'rule'=>'Lô 100 cổ phiếu; bước giá bài học 100 VND; không bán khống. Bài học chỉ mô phỏng khớp liên tục và chờ chứng khoán/tiền T+2 từ 13:00. Phí giả định 0,15% mỗi chiều, khoản thuế bán minh họa 0,1%. 1.000 VND trong bài = 1 AIM luyện tập. Chưa mô phỏng đấu giá, lô lẻ, quyền và ngày lễ.','source'=>'https://www.ssi.com.vn/khach-hang-ca-nhan/quy-dinh-giao-dich/quy-dinh-giao-dich-co-phieu'),
  'forex'=>array('title'=>'Forex','symbol'=>'EUR/USD','name'=>'Euro / Đô la Mỹ','unit'=>'lot','quote'=>'USD / EUR','multiplier'=>100000,'conversion'=>1,'step'=>0.01,'tick'=>0.00001,'spread'=>0.00010,'max_leverage'=>20,'prices'=>array(1.1000,1.1020,1.0970,1.1050,1.0900,1.0800,1.0950,1.1100,1.1030),'days'=>array(0,0,1,1,2,3,4,5,6),'times'=>array('10:00','15:00','10:00','15:00','10:00','10:00','10:00','10:00','10:00'),'rule'=>'1 lot = 100.000 EUR; tối thiểu 0,01 lot. 1 pip = 0,0001 USD; 1 lot thay đổi 1 pip tương ứng 10 AIM. Spread bài học 1 pip; phí giả định 0,002% mỗi chiều. Ký quỹ riêng 1–20x; chưa tính swap qua đêm. 1 USD trong bài = 1 AIM luyện tập.','source'=>'https://help.oanda.com/us/en/faqs/micro-lots.htm'),
  'commodity'=>array('title'=>'Hàng hóa tương lai','symbol'=>'WTI-BÀI-HỌC','name'=>'Dầu WTI · Hợp đồng giả định','unit'=>'hợp đồng','quote'=>'USD / thùng','multiplier'=>1000,'conversion'=>1,'step'=>1,'tick'=>0.01,'spread'=>0.02,'max_leverage'=>10,'prices'=>array(80,81.20,78.90,82.10,76.30,72.00,77.20,83.10,79.40),'days'=>array(0,0,1,1,2,3,4,5,6),'times'=>array('10:00','15:00','10:00','15:00','10:00','10:00','10:00','10:00','10:00'),'rule'=>'1 hợp đồng = 1.000 thùng; bước giá 0,01 USD = 10 AIM/hợp đồng. Phí giả định 2,5 AIM mỗi chiều; ký quỹ bài học 10% giá trị. Bài học tự tất toán bằng AIM ở kỳ cuối, không nhận hàng. Hợp đồng WTI CL thực tế có cơ chế giao nhận; đây không phải hợp đồng CL đang niêm yết. Chưa tính điều chỉnh ký quỹ hằng ngày.','source'=>'https://www.cmegroup.com/markets/energy/crude-oil/light-sweet-crude.contractSpecs.html')
 );}
 static function need($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
 static function number($v,$name,$zero=false){self::need(is_string($v)&&preg_match('/^\d{1,9}(\.\d{1,5})?$/D',$v),'Nhập '.$name.' hợp lệ.');$n=(float)$v;self::need(is_finite($n)&&($zero?$n>=0:$n>0),'Giá trị '.$name.' không hợp lệ.');return $n;}
 static function aligned($n,$step){return abs($n/$step-round($n/$step))<0.00001;}
 static function money($v){self::need(is_finite($v)&&abs($v)<9e9,'Giá trị vượt giới hạn bài học.');return (int)round($v*self::UNIT);}
 static function quote($r){$c=$r['definition'];$mid=$c['prices'][$r['step']];return array('mid'=>$mid,'bid'=>round($mid-$c['spread']/2,5),'ask'=>round($mid+$c['spread']/2,5));}
 static function day($r){return $r['definition']['days'][$r['step']];}
 static function date($r){$d=new DateTimeImmutable('2026-10-19',new DateTimeZone('Asia/Ho_Chi_Minh'));$n=self::day($r);while($n>0){$d=$d->modify('+1 day');if((int)$d->format('N')<6)$n--;}return $d->format('d/m/Y').' '.$r['definition']['times'][$r['step']].' · giờ bài học';}
 static function gross($r,$qty,$price){$c=$r['definition'];return self::money($qty*$c['multiplier']*$price/$c['conversion']);}
 static function fee($r,$qty,$price,$side){$gross=self::gross($r,$qty,$price);return $r['market']==='stock'?(int)ceil($gross*($side==='sell'?0.0025:0.0015)):($r['market']==='forex'?(int)ceil($gross*0.00002):self::money($qty*2.5));}
 static function leverage($r,$v){if($r['market']==='stock')return 1;if($r['market']==='commodity')return 10;self::need(is_int($v)&&in_array($v,array(1,5,10,20),true),'Đòn bẩy không hợp lệ.');return $v;}
 static function hold($r,$o,$price){if($r['market']==='stock'&&$o['side']==='sell')return 0;return (int)ceil(self::gross($r,$o['quantity'],$price)/$o['leverage'])+self::fee($r,$o['quantity'],$price,$o['side']);}
 static function available_shares($r){$n=0;foreach($r['lots'] as $lot)if($lot['due']<=self::day($r))$n+=$lot['quantity'];foreach($r['orders'] as $o)if($o['side']==='sell')$n-=$o['quantity'];return $n;}
 static function event(&$r,$kind,$data=array()){$r['history'][]=array_merge(array('kind'=>$kind,'step'=>$r['step'],'clock'=>self::date($r)),$data);$r['history']=array_slice($r['history'],-150);}
 static function pnl($r,$p,$price){return self::money(($price-$p['entry'])*$p['quantity']*$r['definition']['multiplier']*($p['side']==='buy'?1:-1)/$r['definition']['conversion']);}
 static function close(&$r,$id,$price,$reason){self::need(isset($r['positions'][$id]),'Vị thế không còn mở.');$p=$r['positions'][$id];$pnl=self::pnl($r,$p,$price);$fee=self::fee($r,$p['quantity'],$price,$p['side']);$raw=$p['margin']+$pnl-$fee;$paid=max(0,$raw);$r['cash']+=$paid;$r['fees']+=$fee;$r['realized']+=$paid-$p['margin'];$r['subsidy']+=max(0,-$raw);unset($r['positions'][$id]);self::event($r,$reason,array('id'=>$id,'side'=>$p['side'],'quantity'=>$p['quantity'],'price'=>$price,'fee'=>$fee,'pnl'=>$paid-$p['margin'],'subsidy'=>max(0,-$raw)));}
 static function fill(&$r,$o,$price){
  $qty=$o['quantity'];$side=$o['side'];
  self::need(!$o['tp']||($side==='buy'?$o['tp']>$price:$o['tp']<$price),'Giá khớp đã vượt chốt lời; lệnh mở bị từ chối.');
  self::need(!$o['sl']||($side==='buy'?$o['sl']<$price:$o['sl']>$price),'Giá khớp đã vượt cắt lỗ; lệnh mở bị từ chối.');
  $fee=self::fee($r,$qty,$price,$side);$gross=self::gross($r,$qty,$price);$hold=self::hold($r,$o,$price);
  self::need($r['cash']>=$hold,'AIM luyện tập khả dụng không đủ.');
  if($r['market']==='stock'){
   if($side==='buy'){$r['cash']-=$hold;$r['lots'][]=array('quantity'=>$qty,'cost'=>$gross+$fee,'due'=>self::day($r)+2);}
   else{self::need(self::available_shares($r)>=$qty,'Cổ phiếu chưa về hoặc đang giữ cho lệnh bán.');$left=$qty;$basis=0;foreach($r['lots'] as &$lot){if($lot['due']>self::day($r)||$left<=0)continue;$sold=min($left,$lot['quantity']);$cost=$sold===$lot['quantity']?$lot['cost']:(int)round($lot['cost']*$sold/$lot['quantity']);$lot['quantity']-=$sold;$lot['cost']-=$cost;$basis+=$cost;$left-=$sold;}unset($lot);$r['lots']=array_values(array_filter($r['lots'],function($x){return $x['quantity']>0;}));$r['receivables'][]=array('amount'=>$gross-$fee,'due'=>self::day($r)+2);$r['realized']+=$gross-$fee-$basis;}
  }else{
   self::need(count($r['positions'])<20,'Tối đa 20 vị thế trong bài.');$r['cash']-=$hold;$r['realized']-=$fee;$id=(string)++$r['sequence'];$r['positions'][$id]=array('id'=>$id,'side'=>$side,'quantity'=>$qty,'entry'=>$price,'margin'=>$hold-$fee,'leverage'=>$o['leverage'],'tp'=>$o['tp'],'sl'=>$o['sl']);
  }
  $r['fees']+=$fee;self::event($r,'fill',array('side'=>$side,'quantity'=>$qty,'price'=>$price,'fee'=>$fee,'order_id'=>$o['id']??null));
 }
 static function mark(&$r){$q=self::quote($r);foreach($r['positions'] as $id=>$p){$exit=$p['side']==='buy'?$q['bid']:$q['ask'];$maintenance=(int)ceil(self::gross($r,$p['quantity'],$q['mid'])*0.005);$net=$p['margin']+self::pnl($r,$p,$exit)-self::fee($r,$p['quantity'],$exit,$p['side']);if($net<=$maintenance){self::close($r,$id,$exit,'liquidation');continue;}if($p['sl']>0&&($p['side']==='buy'?$q['mid']<=$p['sl']:$q['mid']>=$p['sl']))self::close($r,$id,$exit,'sl');elseif($p['tp']>0&&($p['side']==='buy'?$q['mid']>=$p['tp']:$q['mid']<=$p['tp']))self::close($r,$id,$exit,'tp');}}
 static function apply($state,$a,$at){
  $market=$a['market']??null;self::need(is_string($market)&&isset(self::catalog()[$market]),'Thị trường không hợp lệ.');$op=$a['operation']??'';self::need(is_string($op),'Thao tác không hợp lệ.');$r=$state['market_runs'][$market]??null;
  if($op==='start'){
   self::need(!$r||$r['finished'],'Đang có bài chưa kết thúc.');$r=array('market'=>$market,'version'=>self::VERSION,'definition'=>self::catalog()[$market],'step'=>0,'cash'=>100000*self::UNIT,'initial'=>100000*self::UNIT,'lots'=>array(),'receivables'=>array(),'positions'=>array(),'orders'=>array(),'history'=>array(),'sequence'=>0,'fees'=>0,'realized'=>0,'subsidy'=>0,'finished'=>false,'started'=>$at,'actions'=>0);self::event($r,'start');
  }else{
   self::need($r&&!$r['finished'],'Bắt đầu bài mới hoặc xem báo cáo đã kết thúc.');self::need($r['version']===self::VERSION,'Phiên bản bài cần được giữ nguyên.');self::need(($a['step']??null)===$r['step'],'Kỳ đã thay đổi. Tải lại trước khi thao tác.');self::need($r['actions']<100||in_array($op,array('next','close','cancel'),true),'Đã đủ 100 thao tác; tiếp tục kỳ hoặc đóng/hủy lệnh.');$q=self::quote($r);
   if($op==='order'){
    $side=$a['side']??'';$type=$a['order_type']??'';self::need(in_array($side,array('buy','sell'),true)&&in_array($type,array('market','limit'),true),'Chiều hoặc loại lệnh không hợp lệ.');$qty=self::number($a['quantity']??null,'khối lượng');$c=$r['definition'];self::need(self::aligned($qty,$c['step'])&&$qty<=($market==='stock'?100000:100),'Khối lượng phải theo bước '.$c['step'].' '.$c['unit'].'.');$lev=self::leverage($r,$a['leverage']??1);$price=$side==='buy'?$q['ask']:$q['bid'];$limit=$type==='limit'?self::number($a['limit']??null,'giá giới hạn'):0;self::need(!$limit||self::aligned($limit,$c['tick']),'Giá giới hạn không đúng bước giá.');$tp=self::number($a['tp']??'0','chốt lời',true);$sl=self::number($a['sl']??'0','cắt lỗ',true);$entry=$limit?:$price;
    if($market==='stock')self::need($tp==0&&$sl==0,'Cổ phiếu bài học chưa có TP/SL tự động.');
    foreach(array($tp,$sl) as $v)self::need(!$v||self::aligned($v,$c['tick']),'TP/SL không đúng bước giá.');self::need(!$tp||($side==='buy'?$tp>$entry:$tp<$entry),'Chốt lời phải đúng phía giá vào.');self::need(!$sl||($side==='buy'?$sl<$entry:$sl>$entry),'Cắt lỗ phải đúng phía giá vào.');
    $o=array('side'=>$side,'quantity'=>$qty,'leverage'=>$lev,'tp'=>$tp,'sl'=>$sl,'limit'=>$limit);$immediate=$type==='market'||($side==='buy'?$price<=$limit:$price>=$limit);
    if($immediate)self::fill($r,$o,$price);else{self::need(count($r['orders'])<20,'Tối đa 20 lệnh chờ.');if($market==='stock'&&$side==='sell')self::need(self::available_shares($r)>=$qty,'Cổ phiếu khả dụng không đủ.');$hold=self::hold($r,$o,$limit);self::need($r['cash']>=$hold,'Không đủ AIM giữ chỗ.');$o['hold']=$hold;$o['id']=(string)++$r['sequence'];$r['cash']-=$hold;$r['orders'][$o['id']]=$o;self::event($r,'pending',array('id'=>$o['id'],'side'=>$side,'quantity'=>$qty,'price'=>$limit));}
   }elseif($op==='cancel'){$id=(string)($a['id']??'');self::need(isset($r['orders'][$id]),'Lệnh không còn chờ.');$r['cash']+=$r['orders'][$id]['hold'];unset($r['orders'][$id]);self::event($r,'cancel',array('id'=>$id));}
   elseif($op==='close'){$id=(string)($a['id']??'');self::need(isset($r['positions'][$id]),'Vị thế không còn mở.');self::close($r,$id,$r['positions'][$id]['side']==='buy'?$q['bid']:$q['ask'],'close');}
   elseif($op==='next'){
    $r['step']++;$q=self::quote($r);$end=$r['step']===count($r['definition']['prices'])-1;
    foreach($r['receivables'] as $i=>$v)if($v['due']<=self::day($r)){$r['cash']+=$v['amount'];unset($r['receivables'][$i]);self::event($r,'settlement',array('amount'=>$v['amount']));}$r['receivables']=array_values($r['receivables']);
    self::mark($r);
    // Final observation closes the lesson; do not open new exposure at expiry.
    foreach($r['orders'] as $id=>$o){$price=$o['side']==='buy'?$q['ask']:$q['bid'];if(!$end&&!($o['side']==='buy'?$price<=$o['limit']:$price>=$o['limit']))continue;$r['cash']+=$o['hold'];unset($r['orders'][$id]);if($end){self::event($r,'expire',array('id'=>$id));continue;}$candidate=$r;try{self::fill($candidate,$o,$price);$r=$candidate;}catch(RuntimeException $e){self::event($r,'reject',array('id'=>$id,'reason'=>$e->getMessage()));}}
    self::mark($r);self::event($r,'next');
    if($end){foreach(array_keys($r['positions']) as $id)self::close($r,$id,$r['positions'][$id]['side']==='buy'?$q['bid']:$q['ask'],$market==='commodity'?'expiry':'finish_close');$r['finished']=true;self::event($r,'finish');}
   }else throw new RuntimeException('Thao tác không hợp lệ.');$r['actions']++;
  }
  $state['market_runs'][$market]=$r;return array('state'=>$state,'delta'=>0,'events'=>array(array('kind'=>'market_action','market'=>$market,'operation'=>$op,'step'=>$r['step'],'finished'=>$r['finished'],'at'=>$at,'cash_delta'=>0)));
 }
 static function view($r){$q=self::quote($r);$hold=0;$margin=0;$upnl=0;$shares=0;$pending_cash=0;foreach($r['orders'] as $o)$hold+=$o['hold'];foreach($r['positions'] as &$p){$p['pnl']=self::pnl($r,$p,$p['side']==='buy'?$q['bid']:$q['ask']);$upnl+=$p['pnl'];$margin+=$p['margin'];}unset($p);foreach($r['lots'] as $p)$shares+=$p['quantity'];foreach($r['receivables'] as $v)$pending_cash+=$v['amount'];$stock=self::gross($r,$shares,$q['bid']);$equity=$r['cash']+$hold+$margin+$upnl+$stock+$pending_cash;$r['metrics']=array('available'=>$r['cash'],'reserved'=>$hold,'margin'=>$margin,'unrealized'=>$upnl,'equity'=>$equity,'return_pct'=>($equity/$r['initial']-1)*100,'shares'=>$shares,'available_shares'=>self::available_shares($r),'receivable'=>$pending_cash);$r['quote']=$q;$r['clock']=self::date($r);$r['definition_day']=self::day($r);$r['observed']=array_slice($r['definition']['prices'],0,$r['step']+1);$r['total_steps']=count($r['definition']['prices'])-1;unset($r['definition']);return $r;}
 static function public_catalog(){ $out=self::catalog();foreach($out as &$c)unset($c['prices'],$c['days'],$c['times']);unset($c);return $out; }
}
