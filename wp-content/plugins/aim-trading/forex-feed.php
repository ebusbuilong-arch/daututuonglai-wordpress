<?php
/** OANDA practice data adapter. GET-only, admin-only diagnostics; never sends broker orders. */
defined('ABSPATH') || exit;
require_once __DIR__.'/reference-feed.php';
final class AIM_Forex_Feed {
 const MAX_AGE=15000;
 static function symbols(){return array('EUR_USD','GBP_USD','AUD_USD','NZD_USD');}
 static function configured(){return AIM_Reference_Feed::selected()?(bool)AIM_Reference_Feed::key():(bool)self::credentials();}
 static function credentials(){
  $token=defined('AIM_OANDA_PRACTICE_TOKEN')?AIM_OANDA_PRACTICE_TOKEN:'';
  $account=defined('AIM_OANDA_PRACTICE_ACCOUNT')?AIM_OANDA_PRACTICE_ACCOUNT:'';
  if(!is_string($token)||!preg_match('/^[A-Za-z0-9_-]{20,256}$/D',$token)||!is_string($account)||!preg_match('/^[0-9]{3}-[0-9]{3}-[0-9]{1,20}-[0-9]{3}$/D',$account))return null;
  return array('token'=>$token,'account'=>$account);
 }
 static function need($ok,$message){if(!$ok)throw new RuntimeException($message);}
 static function number($raw,$positive=true){
  self::need((is_string($raw)||is_int($raw)||is_float($raw))&&is_numeric($raw),'Dữ liệu số không hợp lệ.');$x=(float)$raw;
  self::need(is_finite($x)&&abs($x)<=1e12&&(!$positive||$x>0),'Dữ liệu số ngoài giới hạn.');return $x;
 }
 static function timestamp($raw){
  self::need(is_string($raw)&&preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?Z$/D',$raw),'Thiếu thời gian nguồn UTC.');
  $time=strtotime(substr($raw,0,19).'Z');self::need($time!==false&&gmdate('Y-m-d\TH:i:s',$time)===substr($raw,0,19),'Thời gian nguồn không hợp lệ.');
  $fraction=strpos($raw,'.')!==false?substr($raw,strpos($raw,'.')+1,-1):'';
  return $time*1000+(int)substr(str_pad($fraction,3,'0'),0,3);
 }
 static function spec($row,$symbol){
  self::need(in_array($symbol,self::symbols(),true)&&is_array($row)&&($row['name']??'')===$symbol&&($row['type']??'')==='CURRENCY','Sai mã hoặc loại sản phẩm Forex.');
  $precision=$row['tradeUnitsPrecision']??null;$display=$row['displayPrecision']??null;$pip=$row['pipLocation']??null;
  self::need(is_int($precision)&&$precision>=0&&$precision<=8&&is_int($display)&&$display>=1&&$display<=8&&is_int($pip)&&$pip>=-8&&$pip<=0,'Thông số bước giá không hợp lệ.');
  $min=self::number($row['minimumTradeSize']??null);$max=self::number($row['maximumOrderUnits']??null);$margin=self::number($row['marginRate']??null);
  self::need($max>=$min&&$margin<=1,'Thông số ký quỹ/khối lượng không hợp lệ.');
  return array('symbol'=>$symbol,'unit_precision'=>$precision,'display_precision'=>$display,'pip'=>pow(10,$pip),'tick'=>pow(10,-$display),'min_units'=>$min,'max_units'=>$max,'margin_rate'=>$margin,'lot_units'=>100000,'quote_currency'=>'USD');
 }
 static function price($row,$symbol,$received,$now){
  self::need(in_array($symbol,self::symbols(),true)&&is_array($row)&&($row['instrument']??'')===$symbol,'Báo giá sai mã.');
  $time=self::timestamp($row['time']??null);self::need(is_int($received)&&is_int($now)&&$received<=$now+1000&&$now-$received<=5000,'Báo giá máy chủ đã cũ.');
  self::need($time<=$now+2000&&$now-$time<=self::MAX_AGE,'Báo giá nguồn đã cũ hoặc sai đồng hồ.');
  self::need(in_array($row['status']??'',array('tradeable','non-tradeable','invalid'),true),'Thiếu trạng thái thị trường.');
  $out=array('symbol'=>$symbol,'source'=>'OANDA Practice','time'=>$time,'received'=>$received,'tradeable'=>$row['status']==='tradeable');
  foreach(array('bids','asks') as $side){
   $rows=$row[$side]??null;self::need(is_array($rows)&&count($rows)>0&&count($rows)<=100,'Thiếu độ sâu Bid/Ask.');$last=null;$out[$side]=array();
   foreach($rows as $level){self::need(is_array($level),'Mức thanh khoản không hợp lệ.');$price=self::number($level['price']??null);$units=self::number($level['liquidity']??null);
    self::need($price<100&&$units<=1e12&&($last===null||($side==='bids'?$price<$last:$price>$last)),'Thứ tự giá hoặc quy mô thanh khoản không hợp lệ.');
    $out[$side][]=array('price'=>$price,'units'=>$units);$last=$price;
   }
  }
  $out['bid']=$out['bids'][0]['price'];$out['ask']=$out['asks'][0]['price'];self::need($out['ask']>=$out['bid'],'Bid/Ask bị đảo.');
  return $out;
 }
 static function execution($q,$spec,$side,$lots,$leverage,$now,$limit=null){
  self::need(in_array($side,array('long','short'),true)&&is_int($leverage)&&$leverage>=1&&$leverage<=20,'Chiều/đòn bẩy không hợp lệ.');
  self::need(($q['symbol']??'')===($spec['symbol']??null)&&!empty($q['tradeable']),'Thị trường chưa cho giao dịch.');
  self::need(AIM_Reference_Feed::fresh($q,$now),'Báo giá hết hiệu lực.');
  self::need(is_string($lots)&&preg_match('/^\d{1,3}(?:\.\d{1,5})?$/D',$lots),'Khối lượng lot không hợp lệ.');$units=(float)$lots*100000;
  self::need($units>=$spec['min_units']&&$units<=$spec['max_units']&&abs($units*pow(10,$spec['unit_precision'])-round($units*pow(10,$spec['unit_precision'])))<0.00001,'Khối lượng không đúng bước hoặc vượt giới hạn.');
  if($limit!==null)self::need(is_string($limit)&&preg_match('/^\d{1,2}(?:\.\d{1,8})?$/D',$limit)&&(float)$limit>0,'Giá giới hạn không hợp lệ.');
  if($limit!==null)self::need(abs((float)$limit/$spec['tick']-round((float)$limit/$spec['tick']))<0.00001,'Giá giới hạn sai bước giá.');
  $rows=$q[$side==='long'?'asks':'bids'];$left=$units;$total=0;$fills=array();
  foreach($rows as $r){if($limit!==null&&($side==='long'?$r['price']>(float)$limit:$r['price']<(float)$limit))break;$take=min($left,$r['units']);$total+=$take*$r['price'];$left-=$take;$fills[]=array('price'=>$r['price'],'units'=>$take);if($left<0.000001)break;}
  self::need($left<0.000001,'Không đủ thanh khoản tại mức giá cho phép.');
  $price=$total/$units;$margin_rate=max(1/$leverage,$spec['margin_rate']);$notional=(int)round($total*1000);
  self::need($notional<=100000*1000000,'Giới hạn thử nghiệm: giá trị mỗi lệnh tối đa 100.000 AIM.');
  return array('symbol'=>$q['symbol'],'side'=>$side,'lots'=>$lots,'units'=>$units,'price'=>$price,'fills'=>$fills,'notional'=>$notional,'margin'=>(int)ceil($total*$margin_rate*1000),'effective_leverage'=>1/$margin_rate,'pip_value'=>(int)round($units*$spec['pip']*1000),'spread_cost'=>(int)round($units*($q['ask']-$q['bid'])*1000),'quote_time'=>$q['time'],'simulation_only'=>true);
 }
 static function request($kind){
  $credentials=self::credentials();if(!$credentials)return AIM_Trading::error('Chưa cấu hình tài khoản và token OANDA Practice trên máy chủ.',503);
  if(!in_array($kind,array('pricing','instruments'),true))return AIM_Trading::error('Tuyến dữ liệu không hợp lệ.');
  $cache_key='aimtr_fx_'.substr(hash('sha256',$credentials['account'].'|'.$credentials['token'].'|'.$kind),0,32);$cached=get_transient($cache_key);if(is_array($cached))return $cached;
  $backoff_key='aimtr_fx_backoff_'.substr(hash('sha256',$credentials['account']),0,16);if(get_transient($backoff_key))return AIM_Trading::error('Nguồn Forex đang giới hạn truy cập; chờ một phút rồi kiểm tra lại.',503);
  $url='https://api-fxpractice.oanda.com/v3/accounts/'.$credentials['account'].'/'.$kind.'?'.http_build_query(array('instruments'=>implode(',',self::symbols())),'','&',PHP_QUERY_RFC3986);
  $r=wp_remote_get($url,array('headers'=>array('Authorization'=>'Bearer '.$credentials['token'],'Accept-Datetime-Format'=>'RFC3339'),'timeout'=>5,'redirection'=>0,'limit_response_size'=>262144));
  if(is_wp_error($r))return AIM_Trading::error('Chưa kết nối được nguồn Forex. Không dùng giá lưu cũ.',503);
  $code=wp_remote_retrieve_response_code($r);if($code===429)set_transient($backoff_key,1,60);
  if($code!==200)return AIM_Trading::error(in_array($code,array(401,403),true)?'Nguồn Forex từ chối quyền truy cập. Kiểm tra token và tài khoản Practice.':'Nguồn Forex chưa sẵn sàng (HTTP '.(int)$code.').',503);
  $data=json_decode(wp_remote_retrieve_body($r),true);if(!is_array($data)||isset($data['errorMessage'])||isset($data['errorCode']))return AIM_Trading::error('Nguồn Forex trả dữ liệu không hợp lệ.',503);
  $out=array('data'=>$data,'received'=>(int)round(microtime(true)*1000));set_transient($cache_key,$out,$kind==='instruments'?300:2);return $out;
 }
 static function snapshot($symbols=null){
  if(AIM_Reference_Feed::selected())return AIM_Reference_Feed::snapshot($symbols??self::symbols());
  $meta=self::request('instruments');if(is_wp_error($meta))return $meta;$prices=self::request('pricing');if(is_wp_error($prices))return $prices;
  try{$now=(int)round(microtime(true)*1000);$specs=array();$quotes=array();$missing=array();
   self::need(is_array($meta['data']['instruments']??null)&&is_array($prices['data']['prices']??null),'Nguồn Forex thiếu danh sách sản phẩm hoặc báo giá.');
   foreach($meta['data']['instruments'] as $row){$symbol=$row['name']??'';if(in_array($symbol,self::symbols(),true)){self::need(!isset($specs[$symbol]),'Sản phẩm trùng trong nguồn.');$specs[$symbol]=self::spec($row,$symbol);}}
   foreach($prices['data']['prices'] as $row){$symbol=$row['instrument']??'';if(in_array($symbol,self::symbols(),true)){self::need(!isset($quotes[$symbol]),'Báo giá trùng trong nguồn.');$quotes[$symbol]=self::price($row,$symbol,$prices['received'],$now);}}
   foreach(self::symbols() as $symbol)if(!isset($specs[$symbol],$quotes[$symbol]))$missing[]=$symbol;
   return array('source'=>'OANDA Practice','mode'=>'admin_diagnostics_only','execution_enabled'=>false,'specs'=>$specs,'quotes'=>$quotes,'missing'=>$missing,'received'=>$prices['received'],'notice'=>'Chỉ kiểm tra nguồn; chưa gửi lệnh AIM. Cần kiểm tra quyền sử dụng dữ liệu công khai và hoàn tất nối bộ khớp lệnh.');
  }catch(Throwable $e){return AIM_Trading::error($e->getMessage(),503);}
 }
 static function status(){if(AIM_Reference_Feed::selected())return array('configured'=>(bool)AIM_Reference_Feed::key(),'source'=>AIM_Reference_Feed::SOURCE,'symbols'=>self::symbols(),'execution_enabled'=>false,'needed'=>array('AIM_TWELVE_DATA_KEY'),'notice'=>'Giá tham chiếu + spread mô phỏng; admin đối ứng.');return array('configured'=>(bool)self::credentials(),'source'=>'OANDA Practice','symbols'=>self::symbols(),'execution_enabled'=>false,'needed'=>array('AIM_OANDA_PRACTICE_ACCOUNT','AIM_OANDA_PRACTICE_TOKEN'),'notice'=>'Khóa chỉ đặt trên máy chủ; không gửi khóa vào trò chuyện. Không dùng tài khoản tiền thật.');}
 static function preview($request){
  $p=$request->get_json_params();if(!is_array($p)||!in_array($p['symbol']??null,self::symbols(),true))return AIM_Trading::error('Mã Forex không hỗ trợ.');
  $snapshot=self::snapshot(array($p['symbol']));if(is_wp_error($snapshot))return $snapshot;$symbol=$p['symbol'];if(!isset($snapshot['specs'][$symbol],$snapshot['quotes'][$symbol]))return AIM_Trading::error('Tài khoản nguồn không cung cấp mã này.',503);
  try{return self::execution($snapshot['quotes'][$symbol],$snapshot['specs'][$symbol],$p['side']??null,$p['lots']??null,$p['leverage']??null,(int)round(microtime(true)*1000),$p['limit']??null);}catch(Throwable $e){return AIM_Trading::error($e->getMessage());}
 }
 static function routes(){
  foreach(array('status','snapshot') as $method)register_rest_route('aim-trading/v1','/forex-feed/'.$method,array('methods'=>'GET','callback'=>array(__CLASS__,$method),'permission_callback'=>array('AIM_Trading','admin_permission')));
  register_rest_route('aim-trading/v1','/forex-feed/preview',array('methods'=>'POST','callback'=>array(__CLASS__,'preview'),'permission_callback'=>array('AIM_Trading','admin_permission')));
 }
}
add_action('rest_api_init',array('AIM_Forex_Feed','routes'));
