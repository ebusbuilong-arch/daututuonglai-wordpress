<?php
defined('ABSPATH') || exit;
/** Calendar periods and rankings. Trading wallet cash never enters this score. */
final class AIM_Competition_Engine {
 static function periods(){return array('week'=>'Tuần','month'=>'Tháng','quarter'=>'Quý','year'=>'Năm');}
 static function period($kind,$at){
  if(!isset(self::periods()[$kind]))throw new RuntimeException('Kỳ xếp hạng không hợp lệ.');
  $d=(new DateTimeImmutable('@'.(int)floor($at/1000)))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->setTime(0,0);
  if($kind==='week'){$start=$d->modify('monday this week');$end=$start->modify('+7 days');$label='Tuần '.$start->format('d/m/Y');}
  elseif($kind==='month'){$start=$d->modify('first day of this month');$end=$start->modify('+1 month');$label='Tháng '.$start->format('m/Y');}
  elseif($kind==='quarter'){$q=(int)floor(((int)$d->format('n')-1)/3);$start=$d->setDate((int)$d->format('Y'),$q*3+1,1);$end=$start->modify('+3 months');$label='Quý '.($q+1).'/'.$start->format('Y');}
  else{$start=$d->setDate((int)$d->format('Y'),1,1);$end=$start->modify('+1 year');$label='Năm '.$start->format('Y');}
  return array('id'=>$kind==='week'?$start->format('Y-m-d'):$kind.'-'.$start->format('Y-m-d'),'period'=>$kind,'label'=>$label,'starts_at'=>$start->getTimestamp()*1000,'ends_at'=>$end->getTimestamp()*1000,'scenario'=>$kind==='week'?'season':'season_'.$kind);
 }
 static function definition($kind,$at){
  $p=self::period($kind,$at);$n=array('week'=>8,'month'=>12,'quarter'=>16,'year'=>24)[$kind];$prices=array(100);
  for($i=0;$i<$n;$i++){$change=(hexdec(substr(hash('sha256','aim-season-v1-'.$p['id'].'-'.$i),0,4))%2401-1200)/10000;$prices[]=round(end($prices)*(1+$change),4);}
  return array('title'=>'Thi đấu · '.$p['label'],'intro'=>'Cùng 10.000 đơn vị vốn, một lượt mỗi kỳ. Xếp hạng theo % lợi nhuận sau phí; mức giảm vốn chỉ tham khảo. Giá giả định, không phải giá thị trường.','price'=>0,'reward'=>0,'max_leverage'=>3,'prices'=>$prices,'income'=>array_fill(0,$n,0),'expense'=>array_fill(0,$n,0),'events'=>array_fill(0,$n,'Chọn mức rủi ro trước khi biết giá kỳ sau. Kết quả được xếp theo % lợi nhuận sau phí.'),'season'=>$p['id'],'period'=>$kind,'starts_at'=>$p['starts_at'],'ends_at'=>$p['ends_at']);
 }
 static function rank($accounts,$period,$pilot=false){
  $rows=array();$started=0;$unfinished=0;$excluded=0;
  foreach($accounts as $account){$s=$account['state'];$r=$s['seasons'][$period['id']]??null;if(!$r)continue;
   if((!empty($s['test_account']))!==$pilot){$excluded++;continue;}$started++;
   if(empty($r['finished'])){$unfinished++;continue;}
   if(($r['started']??0)<$period['starts_at']||($r['completed_at']??0)>=$period['ends_at']||($r['completed_at']??0)<($r['started']??0))continue;
   $profit=$r['return_pct']??null;if(!is_numeric($profit)||!is_finite((float)$profit)||$profit< -100)continue;
   $rows[]=array('user_id'=>(int)$account['user_id'],'return_pct'=>round((float)$profit,8),'drawdown_pct'=>(float)($r['drawdown_pct']??0),'fees'=>(int)($r['fees']??0),'completed_at'=>(int)$r['completed_at']);
  }
  usort($rows,function($a,$b){return ($b['return_pct']<=>$a['return_pct'])?:($a['completed_at']<=>$b['completed_at'])?:($a['user_id']<=>$b['user_id']);});
  foreach($rows as $i=>&$r)$r['rank']=$i+1;unset($r);
  return array('rows'=>$rows,'participants'=>$started,'completed'=>count($rows),'unfinished'=>$unfinished,'excluded'=>$excluded);
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
