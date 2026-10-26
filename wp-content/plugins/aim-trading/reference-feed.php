<?php
/** Latest-price simulation: server-only Twelve Data, synthetic spread and admin liquidity. */
defined('ABSPATH') || exit;
final class AIM_Reference_Feed {
 const SOURCE='Twelve Data / AIM reference-v1';
 const MAX_AGE=180000;
 static function selected(){return defined('AIM_REFERENCE_FEED')?AIM_REFERENCE_FEED==='twelvedata':(class_exists('AIM_Market_Launch')&&AIM_Market_Launch::active());}
 static function key(){ $k=defined('AIM_TWELVE_DATA_KEY')?AIM_TWELVE_DATA_KEY:'';return is_string($k)&&preg_match('/^[a-zA-Z0-9_-]{16,128}$/D',$k)?$k:null; }
 static function need($v,$m){if(!$v)throw new RuntimeException($m);}
 static function map(){return array('EUR_USD'=>'EUR/USD','GBP_USD'=>'GBP/USD','AUD_USD'=>'AUD/USD','NZD_USD'=>'NZD/USD','XAU_USD'=>'XAU/USD','XAG_USD'=>'XAG/USD','WTICO_USD'=>'WTI/USD','BCO_USD'=>'XBR/USD','NVDA'=>'NVDA','AAPL'=>'AAPL','GOOG'=>'GOOG','MSFT'=>'MSFT','AMZN'=>'AMZN','TSM'=>'TSM','SPCX'=>'SPCX','META'=>'META','AVGO'=>'AVGO','TSLA'=>'TSLA');}
 static function fresh($q,$now){
  if(!is_array($q))return false;
  if(($q['source']??'')==='CommodityPriceAPI'){
   if(($q['time_basis']??'')!=='provider_response'||empty($q['execution_enabled'])||!in_array($q['symbol']??'',array('WTICO_USD','BCO_USD'),true))return false;
   foreach(array('response_at'=>120000,'received'=>90000) as $k=>$age)if(!isset($q[$k])||!is_int($q[$k])||$q[$k]>$now+2000||$now-$q[$k]>$age)return false;
   return ($q['time']??null)===$q['response_at'];
  }
  $ref=($q['source']??'')===self::SOURCE;
  foreach(array('time','received') as $k)if(!isset($q[$k])||!is_int($q[$k])||$q[$k]>$now+2000||$now-$q[$k]>($ref?($k==='time'?self::MAX_AGE:90000):($k==='time'?15000:5000)))return false;
  return true;
 }
 static function request($kind,$symbol){
  if(!isset(self::map()[$symbol])||!in_array($kind,array('quote','time_series'),true))return AIM_Trading::error('Mã hoặc tuyến dữ liệu chưa hỗ trợ.');
  $key=self::key();if(!$key)return AIM_Trading::error('Chưa cấu hình AIM_TWELVE_DATA_KEY trên máy chủ.',503);
  $cache='aimtr_ref_'.hash('sha256',$key.'|'.$kind.'|'.$symbol);$cached=get_transient($cache);if(is_array($cached))return $cached;
  $backoff='aimtr_ref_wait_'.substr(hash('sha256',$key),0,16);if(get_transient($backoff))return AIM_Trading::error('Nguồn đang giới hạn lượt gọi; tạm dừng khớp.',503);
  $params=array('symbol'=>self::map()[$symbol],'apikey'=>$key,'interval'=>'1min','timezone'=>'UTC');if($kind==='time_series')$params['outputsize']=120;
  $r=wp_remote_get('https://api.twelvedata.com/'.$kind.'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986),array('timeout'=>5,'redirection'=>0,'limit_response_size'=>131072));
  if(is_wp_error($r))return AIM_Trading::error('Không lấy được giá tham chiếu mới.',503);
  $code=wp_remote_retrieve_response_code($r);$d=json_decode(wp_remote_retrieve_body($r),true);
  if($code===429||($d['code']??null)===429)set_transient($backoff,1,60);
  if($code!==200||!is_array($d)||($d['status']??'')==='error')return AIM_Trading::error('Nguồn chưa cung cấp dữ liệu; kiểm tra khóa, mã và quyền gói dữ liệu.',503);
  $out=array('data'=>$d,'received'=>(int)round(microtime(true)*1000));set_transient($cache,$out,60);return $out;
 }
 static function normalize($d,$symbol,$received,$now){
  self::need(isset(self::map()[$symbol])&&is_array($d)&&($d['symbol']??'')===self::map()[$symbol],'Nguồn trả sai mã.');
  self::need(is_int($d['last_quote_at']??null)&&is_bool($d['is_market_open']??null),'Thiếu thời gian giá gần nhất hoặc trạng thái phiên.');
  $price=$d['close']??null;self::need(is_numeric($price)&&is_finite((float)$price)&&(float)$price>0&&(float)$price<1000000,'Giá không hợp lệ.');$price=(float)$price;
  $stock=strpos($symbol,'_')===false;$fx=in_array($symbol,array('EUR_USD','GBP_USD','AUD_USD','NZD_USD'),true);
  if($stock)self::need(($d['currency']??'')==='USD'&&in_array($d['exchange']??'',array('NASDAQ','NYSE'),true),'Sai thị trường hoặc tiền tệ cổ phiếu.');
  if($stock)self::need($d['is_market_open'],'Thị trường đang đóng cửa, chưa thể giao dịch. Vui lòng quay lại khi phiên giao dịch mở cửa. / Market closed. Trading resumes when the market reopens.');
  $spread=$fx?0.0002:0.001;$bid=$price*(1-$spread/2);$ask=$price*(1+$spread/2);
  $q=array('symbol'=>$symbol,'source'=>self::SOURCE,'currency'=>'USD','reference'=>$price,'spread_rate'=>$spread,'bid'=>$bid,'ask'=>$ask,'time'=>$d['last_quote_at']*1000,'received'=>$received,'open'=>$d['is_market_open'],'tradeable'=>$d['is_market_open'],'liquidity_model'=>'admin_synthetic','usd_per_aim'=>1000,'bids'=>array(array('price'=>$bid,'units'=>1e12)),'asks'=>array(array('price'=>$ask,'units'=>1e12)));
  self::need(self::fresh($q,$now),'Giá tham chiếu đã cũ quá 180 giây; tạm dừng khớp.');return $q;
 }
 static function quote($symbol){if(class_exists('AIM_Oil_Reference')&&isset(AIM_Oil_Reference::map()[$symbol]))return AIM_Oil_Reference::quote($symbol);$r=self::request('quote',$symbol);if(is_wp_error($r))return $r;try{return self::normalize($r['data'],$symbol,$r['received'],(int)round(microtime(true)*1000));}catch(Throwable $e){return AIM_Trading::error($e->getMessage(),503);}}
 static function spec($symbol){
  $fx=in_array($symbol,array('EUR_USD','GBP_USD','AUD_USD','NZD_USD'),true);$precision=$fx?5:2;
  return array('symbol'=>$symbol,'unit_precision'=>0,'display_precision'=>$precision,'pip'=>$fx?0.0001:0.01,'tick'=>pow(10,-$precision),'min_units'=>1,'max_units'=>10000000,'margin_rate'=>0.05,'lot_units'=>$fx?100000:($symbol==='XAU_USD'?10:100),'quote_currency'=>'USD','rules'=>'AIM simulation');
 }
 static function snapshot($symbols){$out=array('source'=>self::SOURCE,'quotes'=>array(),'specs'=>array(),'missing'=>array(),'execution_enabled'=>false);foreach(array_unique($symbols) as $s){$q=self::quote($s);if(is_wp_error($q)){$out['missing'][]=$s;continue;}$out['quotes'][$s]=$q;$out['specs'][$s]=self::spec($s);}return $out;}
 static function history($symbol){
  if(class_exists('AIM_Oil_Reference')&&isset(AIM_Oil_Reference::map()[$symbol]))return array();
  $r=self::request('time_series',$symbol);if(is_wp_error($r))return $r;
  try{$d=$r['data'];self::need(($d['meta']['symbol']??'')===(self::map()[$symbol]??null)&&($d['meta']['interval']??'')==='1min'&&is_array($d['values']??null),'Sai dữ liệu biểu đồ.');$out=array();
   foreach($d['values'] as $row){$date=$row['datetime']??'';self::need(is_string($date)&&preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$date),'Sai thời gian nến.');$time=strtotime($date.' UTC');self::need($time!==false&&gmdate('Y-m-d H:i:s',$time)===$date,'Sai ngày nến.');$v=$row['close']??null;self::need(is_numeric($v)&&is_finite((float)$v)&&$v>0&&$v<1000000,'Sai giá nến.');self::need(!isset($out[$time]),'Nến trùng thời gian.');$out[$time]=array('time'=>$time*1000,'close'=>(float)$v);}
   ksort($out);return array_values($out);
  }catch(Throwable $e){return AIM_Trading::error($e->getMessage(),503);}
 }
 static function chart($req){$symbol=$req->get_param('symbol');if(!is_string($symbol)||!isset(self::map()[$symbol]))return AIM_Trading::error('Mã chưa hỗ trợ.');$q=self::quote($symbol);if(is_wp_error($q))return $q;$h=self::history($symbol);if(is_wp_error($h))return $h;return array('quote'=>$q,'points'=>$h,'poll_seconds'=>60,'max_age_seconds'=>180);}
 static function routes(){register_rest_route('aim-trading/v1','/reference/chart',array('methods'=>'GET','callback'=>array(__CLASS__,'chart'),'permission_callback'=>array('AIM_Trading','admin_permission')));}
}
if(function_exists('add_action'))add_action('rest_api_init',array('AIM_Reference_Feed','routes'));
