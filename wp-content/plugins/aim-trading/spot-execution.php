<?php
/** Observed Spot depth. Full-size fills; no fabricated liquidity or exchange queue. */
defined('ABSPATH') || exit;
final class AIM_Spot_Liquidity extends RuntimeException {}
final class AIM_Spot_Execution {
 static function book($q){
  $out=array();
  foreach(array('bids','asks') as $side){
   $rows=$q[$side]??null;if(!is_array($rows)||!count($rows)||count($rows)>100)throw new RuntimeException('Thiếu hoặc sai độ sâu Spot.');
   $last=null;$out[$side]=array();
   foreach($rows as $r){
    if(!is_array($r)||count($r)!==2||!is_string($r[0])||!is_string($r[1])||!preg_match('/^\d{1,8}(\.\d{1,8})?$/D',$r[0])||!preg_match('/^\d{1,8}(\.\d{1,8})?$/D',$r[1]))throw new RuntimeException('Mức giá hoặc lượng Spot không hợp lệ.');
    $price=(float)$r[0];$units=(int)round((float)$r[1]*1e8);
    if($price<=0||$price>1e7||$units<=0||($last!==null&&($side==='bids'?$price>=$last:$price<=$last)))throw new RuntimeException('Sổ Spot sai thứ tự hoặc có lượng bằng không.');
    $out[$side][]=array('price'=>$price,'units'=>$units);$last=$price;
   }
  }
  if($out['asks'][0]['price']<$out['bids'][0]['price'])throw new RuntimeException('Sổ Spot bị chéo giá.');
  return $out;
 }
 static function fill(&$book,$buy,$units,$limit=0){
  if($units<=0||$units>1e14)throw new RuntimeException('Khối lượng Spot không hợp lệ.');
  $side=$buy?'asks':'bids';$rows=$book[$side];$left=$units;$sum=0.0;$fills=array();$best=$rows[0]['price'];
  foreach($rows as $i=>$r){
   if($limit>0&&($buy?$r['price']>$limit:$r['price']<$limit))break;
   $take=min($left,$r['units']);if(!$take)continue;
   $sum+=$take*$r['price'];$left-=$take;$rows[$i]['units']-=$take;$fills[]=array('price'=>$r['price'],'units'=>$take);if(!$left)break;
  }
  if($left)throw new AIM_Spot_Liquidity('Chưa đủ thanh khoản Spot cho toàn bộ lượng ở giá cho phép. Giảm lượng hoặc chờ sổ giá mới.');
  $price=count($fills)===1?$fills[0]['price']:$sum/$units;$book[$side]=$rows;
  return array('price'=>$price,'best_price'=>$best,'slippage_bps'=>max(0,($buy?$price-$best:$best-$price)/$best*10000),'fills'=>$fills,'policy'=>'spot-depth-v1');
 }
 static function migrate(){
  if(get_option('aimtr_spot_execution_schema')==='1')return true;
  global $wpdb;$t=AIM_Trading::table('ledger');
  $column=$wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM $t LIKE %s",'execution'));
  if(!$column&&$wpdb->query("ALTER TABLE $t ADD COLUMN execution longtext NULL")===false)return AIM_Trading::error('Chưa nâng cấp nhật ký độ sâu Spot.',503);
  if(!$wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM $t LIKE %s",'execution')))return AIM_Trading::error('Chưa xác minh cột nhật ký Spot.',503);
  update_option('aimtr_spot_execution_schema','1',false);return true;
 }
}
add_action('admin_init',array('AIM_Spot_Execution','migrate'));
