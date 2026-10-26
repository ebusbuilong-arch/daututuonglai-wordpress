<?php
/** CommodityPriceAPI snapshots and explicitly labelled AIM simulation quotes. */
defined('ABSPATH') || exit;
final class AIM_Oil_Reference {
 const SOURCE='CommodityPriceAPI';
 static function map(){return array('WTICO_USD'=>'WTIOIL-SPOT','BCO_USD'=>'BRENTOIL-SPOT');}
 static function key(){
  $k=defined('AIM_COMMODITY_PRICE_API_KEY')?AIM_COMMODITY_PRICE_API_KEY:'';
  return is_string($k)&&strlen($k)>=16&&strlen($k)<=512&&preg_match('/^[\x21-\x7e]+$/D',$k)?$k:null;
 }
 static function normalize($d,$symbol,$received,$now){
  $s=self::map()[$symbol]??null;
  AIM_Reference_Feed::need($s&&is_array($d)&&($d['success']??false)===true&&empty($d['unresolved'])&&empty($d['warning']),'Dữ liệu dầu không đúng mã yêu cầu.');
  $t=$d['timestamp']??null;
  AIM_Reference_Feed::need(is_int($t)&&$t>0&&$t*1000<=$now+5000&&$now-$t*1000<=120000&&$received<=$now+5000&&$now-$received<=90000,'Phản hồi nguồn dầu đã cũ; cần tải lại.');
  $meta=$d['metadata'][$s]??array();
  AIM_Reference_Feed::need(($meta['unit']??'')==='Bbl'&&($meta['quote']??'')==='USD','Đơn vị dầu phải là USD/thùng.');
  $r=$d['rates'][$s]??array();
  foreach(array('rate','bid','ask') as $field)AIM_Reference_Feed::need(is_array($r)&&isset($r[$field])&&is_numeric($r[$field])&&is_finite((float)$r[$field])&&(float)$r[$field]>0&&(float)$r[$field]<1000000,'Giá dầu không hợp lệ.');
  AIM_Reference_Feed::need((float)$r['bid']<=(float)$r['ask'],'Bid/Ask dầu bị đảo.');
  // timestamp is response-generation time, NOT last trade / quote time.
  // No `time`, `open`, `tradeable`, depth, or execution price contract is exposed.
  return array('symbol'=>$symbol,'provider_symbol'=>$s,'source'=>self::SOURCE,'reference'=>(float)$r['rate'],'bid'=>(float)$r['bid'],'ask'=>(float)$r['ask'],'currency'=>'USD','unit'=>'Bbl','response_at'=>$t*1000,'received'=>$received,'quote_at'=>null,'market_open'=>null,'execution_enabled'=>false,'notice'=>'Chỉ tham khảo: nguồn chưa xác nhận thời điểm giá và trạng thái phiên. Chưa dùng để khớp lệnh AIM.');
 }
 static function latest($symbol){
  if(!is_string($symbol)||!isset(self::map()[$symbol]))return AIM_Trading::error('Mã dầu chưa hỗ trợ.');
  $key=self::key();if(!$key)return AIM_Trading::error('Chưa cấu hình AIM_COMMODITY_PRICE_API_KEY hợp lệ trên VPS.',503);
  $id=substr(hash('sha256',$key),0,20);$cache='aimtr_oil_v1_'.$id.'_'.$symbol;$wait='aimtr_oil_wait_'.$id;
  $stored=get_transient($cache);$now=(int)round(microtime(true)*1000);
  if(is_array($stored)){try{return self::normalize($stored['data'],$symbol,$stored['received'],$now);}catch(Throwable $e){return AIM_Trading::error($e->getMessage(),503);}}
  if(get_transient($wait))return AIM_Trading::error('Nguồn dầu đang tạm nghỉ sau lỗi kết nối hoặc giới hạn lượt gọi. Thử lại sau 60 giây.',503);
  $r=wp_remote_get('https://api.commoditypriceapi.com/v3/rates/latest/'.self::map()[$symbol].'?quote=USD&metadata=true',array('headers'=>array('x-api-key'=>$key,'Accept'=>'application/json'),'timeout'=>8,'redirection'=>0,'limit_response_size'=>65536));
  if(is_wp_error($r)){set_transient($wait,1,60);return AIM_Trading::error('VPS chưa kết nối được nguồn dầu. Thử lại sau 60 giây.',503);}
  $code=wp_remote_retrieve_response_code($r);$d=json_decode(wp_remote_retrieve_body($r),true);
  if($code!==200){set_transient($wait,1,60);$messages=array(401=>'Nguồn dầu không chấp nhận khóa API.',402=>'Nguồn dầu yêu cầu gia hạn dùng thử hoặc gói dữ liệu.',403=>'Nguồn dầu từ chối truy cập hoặc đã hết hạn mức.',404=>'Nguồn dầu chưa trả được mã hoặc khóa chưa được nhận diện.',429=>'Nguồn dầu giới hạn lượt gọi; chờ 60 giây.');return AIM_Trading::error($messages[$code]??'Nguồn dầu đang lỗi; chưa có giá hợp lệ.',503);}
  $now=(int)round(microtime(true)*1000);
  try{$out=self::normalize($d,$symbol,$now,$now);set_transient($cache,array('data'=>$d,'received'=>$now),60);return $out;}catch(Throwable $e){set_transient($wait,1,60);return AIM_Trading::error($e->getMessage(),503);}
 }
 static function simulation($r,$now){
  AIM_Reference_Feed::need(is_array($r)&&isset(self::map()[$r['symbol']??''])&&($r['source']??'')===self::SOURCE,'Sai nguồn giá dầu.');
  $q=$r;
  // Simulation clock is the API response time, never represented as market quote time.
  $q['time']=$r['response_at'];$q['time_basis']='provider_response';
  $q['open']=true;$q['tradeable']=true;$q['execution_enabled']=true;
  $q['session_model']='AIM simulation, available while API response is fresh';
  $q['liquidity_model']='admin_synthetic';$q['usd_per_aim']=1000;
  $q['spread_rate']=($r['ask']-$r['bid'])/$r['reference'];
  $q['bids']=array(array('price'=>$r['bid'],'units'=>1e12));
  $q['asks']=array(array('price'=>$r['ask'],'units'=>1e12));
  $q['notice']='Khớp mô phỏng theo Bid/Ask CommodityPriceAPI. Thời gian là phản hồi nguồn, không phải thời điểm phát sinh giá; chưa xác định độ trễ và phiên thị trường.';
  AIM_Reference_Feed::need(AIM_Reference_Feed::fresh($q,$now),'Phản hồi dầu hết hiệu lực; tạm dừng khớp.');
  return $q;
 }
 static function quote($symbol){
  $r=self::latest($symbol);if(is_wp_error($r))return $r;
  try{return self::simulation($r,(int)round(microtime(true)*1000));}catch(Throwable $e){return AIM_Trading::error($e->getMessage(),503);}
 }
 static function read($r){return self::latest($r->get_param('symbol'));}
 static function routes(){register_rest_route('aim-trading/v1','/oil-reference',array('methods'=>'GET','callback'=>array(__CLASS__,'read'),'permission_callback'=>array('AIM_Trading','admin_permission')));}
}
add_action('rest_api_init',array('AIM_Oil_Reference','routes'));
