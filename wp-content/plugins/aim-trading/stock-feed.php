<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/stock-engine.php';
require_once __DIR__.'/reference-feed.php';
/** Only GET market data / market status. Never sends orders to Alpaca. */
final class AIM_Stock_Feed {
 static function symbols(){return array_merge(array('NVDA','AAPL','GOOG','MSFT','AMZN','TSM','SPCX','META','AVGO','TSLA'),array_keys(AIM_VN_Stock_Feed::map()));}
 static function configured(){return AIM_Reference_Feed::selected()?(bool)AIM_Reference_Feed::key():(bool)self::credentials();}
 static function credentials(){
  $key=defined('AIM_ALPACA_DATA_KEY')?AIM_ALPACA_DATA_KEY:'';$secret=defined('AIM_ALPACA_DATA_SECRET')?AIM_ALPACA_DATA_SECRET:'';
  foreach(array($key,$secret) as $s)if(!is_string($s)||!preg_match('/^[a-zA-Z0-9_-]{16,256}$/D',$s))return null;
  return array('APCA-API-KEY-ID'=>$key,'APCA-API-SECRET-KEY'=>$secret);
 }
 static function feed(){return defined('AIM_ALPACA_DATA_FEED')&&AIM_ALPACA_DATA_FEED==='sip'?'sip':'iex';}
 static function request($kind,$symbol=''){
  $headers=self::credentials();if(!$headers)return AIM_Trading::error('Chưa cấu hình nguồn giá cổ phiếu trên VPS.',503);
  if(!in_array($symbol,self::symbols(),true))return AIM_Trading::error('Mã cổ phiếu chưa hỗ trợ.');
  $paths=array('quote'=>'https://data.alpaca.markets/v2/stocks/'.$symbol.'/quotes/latest?feed='.self::feed(),'clock'=>'https://paper-api.alpaca.markets/v2/clock','asset'=>'https://paper-api.alpaca.markets/v2/assets/'.$symbol);
  if(!isset($paths[$kind]))return AIM_Trading::error('Tuyến nguồn không hợp lệ.');
  $key='aimtr_stock_'.substr(hash('sha256',implode('|',$headers).'|'.$paths[$kind]),0,32);
  $cached=get_transient($key);if(is_array($cached))return $cached;
  if(get_transient('aimtr_stock_backoff'))return AIM_Trading::error('Nguồn đang giới hạn truy cập. Thử lại sau một phút.',503);
  $r=wp_remote_get($paths[$kind],array('headers'=>$headers,'timeout'=>5,'redirection'=>0,'limit_response_size'=>65536));
  if(is_wp_error($r))return AIM_Trading::error('Không kết nối được nguồn cổ phiếu. Không dùng giá thay thế.',503);
  $status=wp_remote_retrieve_response_code($r);if($status===429)set_transient('aimtr_stock_backoff',1,60);
  if($status!==200)return AIM_Trading::error('Nguồn cổ phiếu chưa sẵn sàng (HTTP '.(int)$status.').',503);
  $data=json_decode(wp_remote_retrieve_body($r),true);if(!is_array($data))return AIM_Trading::error('Nguồn cổ phiếu trả dữ liệu không hợp lệ.',503);
  $out=array('data'=>$data,'received'=>(int)round(microtime(true)*1000));set_transient($key,$out,$kind==='asset'?60:1);return $out;
 }
 static function quote($symbol){
  if(isset(AIM_VN_Stock_Feed::map()[$symbol]))return AIM_VN_Stock_Feed::quote($symbol);
  if(AIM_Reference_Feed::selected()){if(!in_array($symbol,self::symbols(),true))return AIM_Trading::error('Mã chưa hỗ trợ.');return AIM_Reference_Feed::quote($symbol);}
  if(!in_array($symbol,self::symbols(),true))return AIM_Trading::error('Chưa có nguồn khớp lệnh cho mã này.',503);
  $clock=self::request('clock',$symbol);if(is_wp_error($clock))return $clock;
  $asset=self::request('asset',$symbol);if(is_wp_error($asset))return $asset;
  $price=self::request('quote',$symbol);if(is_wp_error($price))return $price;
  try{
   $now=(int)round(microtime(true)*1000);$d=$price['data'];$a=$asset['data'];$c=$clock['data'];
   AIM_Stock_Engine::need(($d['symbol']??'')===$symbol&&is_array($d['quote']??null),'Nguồn trả sai mã cổ phiếu.');
   AIM_Stock_Engine::need(($a['symbol']??'')===$symbol&&($a['class']??'')==='us_equity'&&($a['status']??'')==='active'&&($a['tradable']??false)===true,'Mã chưa được nguồn cho giao dịch.');
   AIM_Stock_Engine::need(isset($c['timestamp'])&&is_string($c['timestamp'])&&abs(strtotime($c['timestamp'])*1000-$now)<=15000&&$now-$clock['received']<=5000,'Trạng thái phiên đã cũ.');
   $row=$d['quote'];$q=array('symbol'=>$symbol,'currency'=>'USD','bid'=>$row['bp']??null,'ask'=>$row['ap']??null,'time'=>AIM_Forex_Feed::timestamp($row['t']??null),'received'=>$price['received'],'open'=>($c['is_open']??false)===true,'source'=>'Alpaca '.strtoupper(self::feed()),'usd_per_aim'=>1000);
   AIM_Stock_Engine::quote($q,$symbol,$now);return $q;
  }catch(Throwable $e){return AIM_Trading::error($e->getMessage(),503);}
 }
}

/** SSI data only: never sends an order to SSI. */
final class AIM_VN_Stock_Feed {
 static function map(){return array('SHS'=>'HNX','PVS'=>'HNX','FPT'=>'HOSE','HPG'=>'HOSE','VCB'=>'HOSE','MBB'=>'HOSE','TCB'=>'HOSE','SSI'=>'HOSE','VNM'=>'HOSE','VIC'=>'HOSE','VHM'=>'HOSE','MWG'=>'HOSE','ACV'=>'UPCOM');}
 static function configured(){return class_exists('DTTL_SSI_Charts')&&method_exists('DTTL_SSI_Charts','market_data')&&(bool)get_option('dttl_ssi_credentials','');}
 static function session($now){$d=(new DateTimeImmutable('@'.(int)($now/1000)))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));$hm=(int)$d->format('Hi');return (int)$d->format('N')<=5&&(($hm>=900&&$hm<1130)||($hm>=1300&&$hm<1500));}
 static function quote($symbol){
  if(!self::configured()||!isset(self::map()[$symbol]))return AIM_Trading::error('Chưa kết nối nguồn SSI cho mã này.',503);
  $now=(int)round(microtime(true)*1000);
  if(!self::session($now))return AIM_Trading::error('Cổ phiếu VN: giờ giao dịch mô phỏng 09:00–11:30 và 13:00–15:00 (giờ Việt Nam, thứ Hai–thứ Sáu).',503);
  $key='aimtr_ssi_quote_'.$symbol;$cached=get_transient($key);if(is_array($cached)){try{AIM_Stock_Engine::quote($cached,$symbol,$now);return $cached;}catch(Throwable $e){delete_transient($key);}}
  $date=(new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y/m/d');
  $d=DTTL_SSI_Charts::market_data('ohlc',array('symbol'=>$symbol,'timeFrame'=>'1m','from'=>$date.' 09:00:00','to'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y/m/d H:i:s'),'pageIndex'=>1,'pageSize'=>5));if(is_wp_error($d))return $d;
  $rows=$d['data']??array();$bars=DTTL_SSI_Charts::normalize($rows,'1m');$bar=$bars?end($bars):null;
  if(!$bar||$bar['volume']<=0||$bar['close']<=0)return AIM_Trading::error('Chưa có nến giao dịch SSI đủ mới để khớp mô phỏng.',503);
  $now=(int)round(microtime(true)*1000);$p=$bar['close'];
  $q=array('symbol'=>$symbol,'currency'=>'VND','reference'=>$p,'bid'=>round($p*0.9995,2),'ask'=>round($p*1.0005,2),'time'=>(int)$bar['time']*1000,'received'=>$now,'open'=>self::session($now),'source'=>'SSI 1m · mô phỏng','liquidity_model'=>'admin_synthetic','spread_rate'=>0.001,'usd_per_aim'=>1000,'vnd_per_aim'=>AIM_Stock_Engine::VND_PER_AIM);
  try{AIM_Stock_Engine::quote($q,$symbol,$now);}catch(Throwable $e){return AIM_Trading::error($e->getMessage(),503);}
  set_transient($key,$q,15);return $q;
 }
}
