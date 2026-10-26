<?php
/** Cash equity simulator. Prices are USD; cash is integer micro-AIM. */
defined('ABSPATH') || exit;
require_once __DIR__.'/reference-feed.php';
final class AIM_Stock_Engine {
 const USD_PER_AIM=1000;
 const U=1000000;
 const VND_PER_AIM=25000000;
 const POLICY='stock-cash-usd1000-v1';
 static function need($ok,$message){if(!$ok)throw new RuntimeException($message);}
 static function money($amount,$currency='USD'){self::need(in_array($currency,array('USD','VND'),true),'Tiền tệ không hợp lệ.');$scale=$currency==='VND'?self::VND_PER_AIM:self::USD_PER_AIM;self::need(is_numeric($amount)&&is_finite((float)$amount)&&abs($amount)/$scale<=1000000,'Giá trị vượt giới hạn.');return (int)round($amount*self::U/$scale);}
 static function blank(){return array('policy'=>self::POLICY,'positions'=>array(),'realized'=>0,'fees'=>0);}
 static function quote($q,$symbol,$now){
  self::need(is_array($q)&&($q['symbol']??null)===$symbol&&in_array(($q['currency']??null),array('USD','VND'),true),'Báo giá không đúng mã hoặc tiền tệ.');
  self::need(($q['open']??false)===true,'Thị trường đang đóng cửa hoặc mã đang tạm ngừng giao dịch. / Market closed or trading halted.');
  $vn=$q['currency']==='VND';
  if($vn){
   self::need(($q['source']??'')==='SSI 1m · mô phỏng'&&($q['vnd_per_aim']??0)===self::VND_PER_AIM,'Nguồn / quy đổi VND không hợp lệ.');
   foreach(array('time'=>180000,'received'=>30000) as $field=>$age)self::need(isset($q[$field])&&is_int($q[$field])&&$q[$field]<=$now+2000&&$now-$q[$field]<=$age,'Giá SSI đã cũ; vui lòng lấy giá mới.');
  }else self::need(AIM_Reference_Feed::fresh($q,$now),'Báo giá đã cũ; vui lòng lấy giá mới.');
  foreach(array('bid','ask') as $k)self::need(isset($q[$k])&&is_numeric($q[$k])&&is_finite((float)$q[$k])&&$q[$k]>0&&$q[$k]<=($vn?1000000000:1000000),'Giá nguồn không hợp lệ.');
  self::need($q['ask']>=$q['bid'],'Bid/Ask bị đảo.');
 }
 static function fill($state,$cash,$symbol,$side,$quantity,$q,$now,$bound){
  self::need(($state['policy']??'')===self::POLICY&&is_int($cash)&&$cash>=0,'Tài khoản cổ phiếu cần đối soát.');
  self::need(is_string($symbol)&&preg_match('/^[A-Z]{1,8}$/D',$symbol)&&in_array($side,array('buy','sell'),true),'Mã hoặc chiều lệnh không hợp lệ.');
  self::need(is_int($quantity)&&$quantity>=1&&$quantity<=1000000,'Khối lượng phải là số nguyên từ 1 đến 1.000.000 cổ phiếu.');
  self::quote($q,$symbol,$now);$currency=$q['currency'];if($currency==='VND')self::need($quantity%100===0,'Mã Việt Nam: khối lượng là bội số của 100 cổ phiếu.');$price=(float)$q[$side==='buy'?'ask':'bid'];
  self::need((is_float($bound)||is_int($bound))&&is_finite((float)$bound)&&$bound>0&&$bound<=($currency==='VND'?1000000000:1000000),'Thiếu giá bảo vệ hợp lệ.');
  self::need($side==='buy'?$price<=$bound:$price>=$bound,'Giá đã vượt mức cho phép; lệnh không khớp và không giữ AIM.');
  $value=self::money($quantity*$price,$currency);self::need($value>0,'Giá trị quá nhỏ.');$fee=max(1,(int)ceil($value*0.001));$realized=0;$next=$state;
  $position=$state['positions'][$symbol]??array('quantity'=>0,'cost'=>0,'currency'=>$currency);
  self::need(($position['currency']??'USD')===$currency,'Tiền tệ danh mục không khớp.');
  if($side==='buy'){
   self::need($cash>=$value+$fee,'Không đủ AIM khả dụng.');self::need($position['quantity']+$quantity<=1000000,'Danh mục vượt giới hạn số cổ phiếu.');
   $position['quantity']+=$quantity;$position['cost']+=$value+$fee;$delta=-$value-$fee;
  }else{
   self::need($position['quantity']>=$quantity,'Không đủ cổ phiếu để bán; không bán khống.');
   $basis=$quantity===$position['quantity']?$position['cost']:(int)round($position['cost']*$quantity/$position['quantity']);
   $position['quantity']-=$quantity;$position['cost']-=$basis;$delta=$value-$fee;$realized=$delta-$basis;
  }
  if($position['quantity'])$next['positions'][$symbol]=$position;else unset($next['positions'][$symbol]);
  $next['realized']+=$realized;$next['fees']+=$fee;
  return array('state'=>$next,'cash_delta'=>$delta,'pool_delta'=>-$delta,'event'=>array('symbol'=>$symbol,'side'=>$side,'quantity'=>$quantity,'price'=>$price,'value'=>$value,'fee'=>$fee,'realized'=>$realized,'quote_time'=>$q['time'],'source'=>$q['source']??'','usd_per_aim'=>self::USD_PER_AIM,'currency'=>$currency,'vnd_per_aim'=>$currency==='VND'?self::VND_PER_AIM:null));
 }
}
