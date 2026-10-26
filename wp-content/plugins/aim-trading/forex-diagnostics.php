<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/forex-engine.php';
/** Deterministic admin verification: fixtures only, no persistent account or wallet. */
final class AIM_Forex_Diagnostics {
 static function book($bid,$ask,$depth,$time){return array('symbol'=>'EUR_USD','source'=>'FIXTURE — NOT LIVE','time'=>$time,'received'=>$time,'tradeable'=>true,'bid'=>$bid,'ask'=>$ask,'bids'=>array(array('price'=>$bid,'units'=>$depth)),'asks'=>array(array('price'=>$ask,'units'=>$depth)));}
 static function run(){
  try{
   $now=1792670400000;$initial=1000*1000000;
   $spec=AIM_Forex_Feed::spec(array('name'=>'EUR_USD','type'=>'CURRENCY','tradeUnitsPrecision'=>0,'displayPrecision'=>5,'pipLocation'=>-4,'minimumTradeSize'=>'1','maximumOrderUnits'=>'10000000','marginRate'=>'0.05'),'EUR_USD');
   $start=self::book(1.1,1.1002,100000,$now);$rows=array();
   $scenarios=array(
    array('Long chốt lời','long',1.112,1.1122,100000,'take_profit',false),
    array('Short chốt lời','short',1.088,1.0882,100000,'take_profit',false),
    array('Giá nhảy qua SL','long',1.085,1.0852,100000,'stop_loss',false),
    array('Thanh lý thiếu ký quỹ','long',1.04,1.0402,100000,'liquidation',false),
    array('Lỗ vượt ký quỹ','long',0.8,0.8002,100000,'liquidation',false),
    array('Thiếu thanh khoản → chờ đóng','long',1.08,1.0802,500,'stop_loss',true)
   );
   foreach($scenarios as $case){list($name,$side,$bid,$ask,$depth,$reason,$waiting)=$case;
    $opened=AIM_Forex_Engine::open(AIM_Forex_Engine::blank($initial),$start,$spec,$side,'0.01',10,$now,$side==='long'?'1.11':'1.09',$side==='long'?'1.09':'1.11');
    $done=AIM_Forex_Engine::evaluate($opened['state'],array('EUR_USD'=>self::book($bid,$ask,$depth,$now)),$now);$s=$done['state'];$event=$done['events'][0]??array();$margin=array_sum(array_column($s['positions'],'margin'));
    $conserved=$s['cash']+$margin+$s['pool_delta']===$initial;
    $passed=$conserved&&$s['cash']>=0&&($event['reason']??'')===$reason&&($event['kind']??'')===($waiting?'exit_waiting':'close');
    $rows[]=array('name'=>$name,'passed'=>$passed,'entry'=>$opened['events'][0]['price'],'exit'=>$event['price']??null,'cash'=>$s['cash'],'reserved'=>$margin,'pool_delta'=>$s['pool_delta'],'subsidy'=>$s['subsidy'],'pnl'=>$s['realized'],'fees'=>$s['fees'],'status'=>$waiting?'Chờ đủ thanh khoản':'Đã đóng','conserved'=>$conserved);
   }
   return array('policy'=>AIM_Forex_Engine::POLICY,'source'=>'DỮ LIỆU KIỂM THỬ CỐ ĐỊNH — KHÔNG PHẢI GIÁ THẬT','initial'=>$initial,'rows'=>$rows,'passed'=>count(array_filter($rows,function($r){return $r['passed'];}))===count($rows),'notice'=>'Mỗi tình huống bắt đầu riêng với 1.000 AIM kiểm thử, 0,01 lot, đòn bẩy 10x. Phí mô phỏng 3,5 USD = 0,0035 AIM/lot/mỗi chiều; stop-out tại 50% ký quỹ ban đầu sau phí đóng ước tính. Không ghi ví, số dư hoặc lệnh người dùng; chưa tính swap.');
  }catch(Throwable $e){return AIM_Trading::error('Kiểm tra bộ xử lý Forex thất bại: '.$e->getMessage(),503);}
 }
 static function routes(){register_rest_route('aim-trading/v1','/forex-feed/engine-check',array('methods'=>'GET','callback'=>array(__CLASS__,'run'),'permission_callback'=>array('AIM_Trading','admin_permission')));}
}
add_action('rest_api_init',array('AIM_Forex_Diagnostics','routes'));
