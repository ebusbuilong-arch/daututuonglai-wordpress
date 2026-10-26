<?php
/** Existing AIM token and existing vault only. Disabled until owner approves restricted signing. */
defined('ABSPATH') || exit;
final class AIM_Wallet_Automation {
 const OPTION='aimtr_wallet_automation_v1';
 const MAX_PAYOUT=10000000000; // 10,000 AIM, six-decimal accounting units.
 static function config(){return get_option(self::OPTION,array());}
 static function enabled(){ $s=self::config();$v=get_option('aimtr_vault_v1',array());return !empty($s['enabled'])&&($s['vault']??'')===($v['address']??null)&&($s['token']??'')===($v['token']??null)&&function_exists('aim_auto_enabled')&&aim_auto_enabled(); }
 static function wallet_first($uid){$s=self::config();return array_key_exists('existing_user_max',$s)&&$uid>(int)($s['existing_user_max']??PHP_INT_MAX);}
 static function eligible($uid){return self::wallet_first($uid)&&(bool)get_user_meta($uid,'_dttl_aim_email_verified',true)&&!empty(get_user_meta($uid,'_aim_privy_wallet',true));}
 static function need($v,$m){if(!$v)throw new RuntimeException($m);}
 static function words($n){self::need(is_int($n)&&$n>0&&$n<=self::MAX_PAYOUT,'Số AIM vượt giới hạn tự động.');$decimal=bcmul((string)$n,'1000000000000',0);$hex='';while(bccomp($decimal,'0')>0){$hex=dechex((int)bcmod($decimal,'16')).$hex;$decimal=bcdiv($decimal,'16',0);}return str_pad($hex,64,'0',STR_PAD_LEFT);}
 static function validate_job($job){self::need(is_array($job)&&preg_match('/^0x[0-9a-f]{64}$/D',$job['id']??'')&&preg_match('/^0x[0-9a-f]{40}$/D',$job['address']??'')&&$job['address']!=='0x'.str_repeat('0',40),'Yêu cầu trả AIM không hợp lệ.');self::words($job['amount']??null);}
 static function verified_vault(){
  $v=AIM_Wallet_Bridge::config();$a=AIM_Wallet_Bridge::artifact();$t=get_option('aim_chain_contract_v1',array());
  self::need(hexdec(AIM_Wallet_Bridge::rpc('eth_chainId',array()))===84532,'Sai mạng AIM.');
  self::need(strtolower(AIM_Wallet_Bridge::rpc('eth_getCode',array($v['address'],'latest')))===strtolower($a['runtime']),'Mã kho AIM không khớp.');
  foreach(array('owner'=>$t['owner']??'','token'=>$t['address']??'') as $key=>$expected){$r=AIM_Wallet_Bridge::rpc('eth_call',array(array('to'=>$v['address'],'data'=>$a['selectors'][$key]),'latest'));self::need(strtolower('0x'.substr($r,-40))===strtolower($expected),'Kho AIM sai '.$key);}
  return $v;
 }
 static function payout($job){
  self::need(self::enabled(),'Tự động trả AIM chưa được bật.');self::validate_job($job);
  global $wpdb;$lock='aimtr_wallet_auto_'.md5($wpdb->prefix);self::need((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))===1,'Đang xử lý giao dịch AIM khác.');
  try{
   $v=self::verified_vault();$a=AIM_Wallet_Bridge::artifact();$key='aimtr_pay_'.substr(hash('sha256',$v['address'].$job['id']),0,40);$state=get_option($key,array());
   $fingerprint=hash('sha256',wp_json_encode($job));self::need(empty($state['fingerprint'])||hash_equals($state['fingerprint'],$fingerprint),'Yêu cầu trả AIM thay đổi nội dung.');
   if(!empty($state['hash'])){
    $r=AIM_Wallet_Bridge::receipt($state['hash']);$amount=AIM_Wallet_Bridge::transfer_log($r,$v,'paid',$job['address'],$job['id']);
    self::need($amount===$job['amount'],'Số AIM xác nhận không khớp.');return array('confirmed'=>true,'hash'=>$state['hash']);
   }
   $used=AIM_Wallet_Bridge::rpc('eth_call',array(array('to'=>$v['address'],'data'=>$a['selectors']['withdrawals'].substr($job['id'],2)),'latest'));
   self::need(preg_match('/^0x[0-9a-fA-F]{64}$/D',$used),'Chưa đọc được mã thanh toán.');
   self::need(substr($used,-1)==='0','Mã thanh toán đã dùng; cần đối chiếu giao dịch cũ, không gửi lại.');
   self::need(empty($state['sent_at'])||time()-$state['sent_at']<23*3600,'Giao dịch chưa rõ kết quả quá 23 giờ; cần đối soát.');
   self::need(empty($state['attempt_at'])||time()-$state['attempt_at']>=60,'Đang chờ xác nhận, không gửi lặp.');
   $data=$a['selectors']['pay'].substr($job['id'],2).str_pad(substr($job['address'],2),64,'0',STR_PAD_LEFT).self::words($job['amount']);
   AIM_Wallet_Bridge::rpc('eth_call',array(array('from'=>$v['owner'],'to'=>$v['address'],'data'=>$data),'latest'));
   $state=array('fingerprint'=>$fingerprint,'sent_at'=>$state['sent_at']??time(),'attempt_at'=>time(),'idempotency'=>'aimpay-'.substr(hash('sha256',$v['address'].$job['id']),0,50));
   update_option($key,$state,false);self::need(get_option($key)===$state,'Chưa lưu được yêu cầu thanh toán.');
   $hash=aim_auto_privy_send($v['address'],$data,$state['idempotency']);if(is_wp_error($hash))throw new RuntimeException($hash->get_error_message());
   $state['hash']=$hash;update_option($key,$state,false);self::need(get_option($key)===$state,'Chưa lưu mã giao dịch; cần đối soát.');return array('confirmed'=>false,'hash'=>$hash);
  }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
 }
 static function welcome($uid){
  if(!self::eligible($uid)||!self::enabled())return null;
  if(get_user_meta($uid,'_aimtr_welcome100_confirmed',true))return array('state'=>'confirmed','message'=>'Đã cấp tổng 100 AIM đăng ký vào ví. Nạp từ ví để giao dịch.');
  try{
   $v=AIM_Wallet_Bridge::config();$jobs=aim_auto_jobs($uid);self::need(!empty($jobs),'Xác minh email và mở ví để nhận 100 AIM.');
   $base=$jobs[0];self::need($base['id']==='welcome','Thiếu bản ghi đăng ký.');$paid=aim_auto_paid($v['token'],$base);self::need(is_bool($paid),'Chưa xác minh được thưởng đăng ký cũ.');
   // Never race an already submitted legacy reward during the upgrade.
   $legacy=get_user_meta($uid,'_aim_auto_'.substr(hash('sha256',$v['token'].':welcome'),0,32),true);
   self::need($paid||empty($legacy['sent_at'])&&empty($legacy['hash']),'Đang đối soát giao dịch thưởng cũ; chưa gửi khoản mới.');
   $amount=$paid?99500000:100000000;
   $plan=get_user_meta($uid,'_aimtr_welcome_plan',true);
   if(!$plan){$plan=array('amount'=>$amount,'address'=>AIM_Wallet_Bridge::wallet($uid));update_user_meta($uid,'_aimtr_welcome_plan',$plan);self::need(get_user_meta($uid,'_aimtr_welcome_plan',true)===$plan,'Chưa lưu kế hoạch thưởng.');}
   self::need((int)$plan['amount']===$amount&&$plan['address']===AIM_Wallet_Bridge::wallet($uid),'Thưởng đăng ký thay đổi; cần đối soát.');
   $job=array('id'=>'0x'.hash('sha256','aim-welcome-100-v1:'.$v['address'].':'.$base['member']),'address'=>AIM_Wallet_Bridge::wallet($uid),'amount'=>$amount);
   $r=self::payout($job);if($r['confirmed']){update_user_meta($uid,'_aimtr_welcome100_confirmed',$r['hash']);delete_user_meta($uid,'_aimtr_welcome100_pending');}
   return array('state'=>$r['confirmed']?'confirmed':'pending','hash'=>$r['hash'],'message'=>$r['confirmed']?'Đã nhận tổng 100 AIM vào ví.':($amount===100000000?'Đã gửi 100 AIM vào ví; đang đợi xác nhận mạng.':'Đang hoàn tất phần thưởng đăng ký cũ để đủ 100 AIM.'));
  }catch(Throwable $e){return array('state'=>'pending','message'=>$e->getMessage());}
 }
 static function process_wallet_rewards($uid){
  if(!self::enabled())return array('state'=>'setup','message'=>'Phát thưởng từ kho đang tạm dừng.');
  if(!self::eligible($uid))return array('state'=>'waiting','message'=>'Xác minh email và mở ví để nhận 100 AIM.');
  global $wpdb;$lock='aim_auto_'.substr(hash('sha256',home_url()),0,32);
  if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1)return array('state'=>'pending','message'=>'Đang xử lý thưởng.');
  try{
   $welcome=self::welcome($uid);if(($welcome['state']??'')!=='confirmed')return $welcome;
   // The existing contract requires its 0.5 welcome before article rewards.
   // Preserve the same 0.1/article and five-article cap via the vault for this cohort.
   $jobs=aim_auto_jobs($uid);$v=AIM_Wallet_Bridge::config();$count=0;
   foreach($jobs as $article){if($article['id']==='welcome')continue;if(++$count>5)break;
    $paid=aim_auto_paid($v['token'],$article);self::need(is_bool($paid),'Chưa đối soát được thưởng bài viết.');if($paid)continue;
    $legacy=get_user_meta($uid,'_aim_auto_'.substr(hash('sha256',$v['token'].':'.$article['id']),0,32),true);
    self::need(empty($legacy['sent_at'])&&empty($legacy['hash']),'Đang đối soát thưởng bài viết cũ.');
    $r=self::payout(array('id'=>'0x'.hash('sha256','aim-article-vault-v1:'.$v['address'].':'.$article['event']),'address'=>AIM_Wallet_Bridge::wallet($uid),'amount'=>100000));
    if(!$r['confirmed'])return array('state'=>'pending','hash'=>$r['hash'],'message'=>'Đang xác nhận thưởng bài viết vào ví.');
   }
   if(class_exists('AIM_Article_Rewards')){$article=AIM_Article_Rewards::process($uid);if($article)return $article;}
   return $welcome;
  }catch(Throwable $e){return array('state'=>'pending','message'=>$e->getMessage());}
  finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
 }
 static function auto_deposit_state($uid){
  global $wpdb;
  if(!self::eligible($uid)||!get_user_meta($uid,'_aimtr_auto_deposit100',true))return 'off';
  $t=AIM_Trading::table('bridge');
  $done=$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d AND direction='deposit' LIMIT 1",$uid));
  if($wpdb->last_error)return 'waiting';
  if($done)return 'done';
  return self::enabled()&&get_user_meta($uid,'_aimtr_welcome100_confirmed',true)?'ready':'waiting';
 }
 static function auto_deposit_claim($p){
  $uid=get_current_user_id();self::need(self::auto_deposit_state($uid)==='ready','Khoản khởi đầu chưa sẵn sàng hoặc đã được nạp.');
  $owner=$p['owner']??'';self::need(is_string($owner)&&preg_match('/^[a-zA-Z0-9-]{32,64}$/D',$owner),'Mã phiên không hợp lệ.');
  $key='aimtr_auto_deposit_claim_'.$uid;
  // Atomic, permanent claim prevents another device from sending a second welcome deposit.
  add_option($key,array('owner'=>$owner,'hash'=>null),'',false);$claim=get_option($key);
  self::need(is_array($claim),'Chưa lưu được yêu cầu tự nạp.');
  if(!empty($p['tx'])){
   self::need(hash_equals($claim['owner'],$owner),'Giao dịch đang được xử lý ở phiên khác.');
   self::need(is_string($p['tx'])&&preg_match('/^0x[0-9a-fA-F]{64}$/D',$p['tx']),'Mã giao dịch không hợp lệ.');
   self::need(empty($claim['hash'])||strtolower($p['tx'])===$claim['hash'],'Đã có giao dịch cần đối soát.');
   $claim['hash']=strtolower($p['tx']);update_option($key,$claim,false);
   self::need(get_option($key)===$claim,'Chưa lưu được mã giao dịch tự nạp.');
  }
  return array('owned'=>hash_equals($claim['owner'],$owner),'hash'=>$claim['hash']);
 }
 static function registered($uid){if(self::wallet_first((int)$uid)){update_user_meta((int)$uid,'_aimtr_welcome100_pending',1);update_user_meta((int)$uid,'_aimtr_auto_deposit100',1);}}
 static function tick(){
  if(!self::enabled()||get_transient('aimtr_auto_wallet_wait'))return;set_transient('aimtr_auto_wallet_wait',1,60);
  if(class_exists('AIM_Article_Rewards'))AIM_Article_Rewards::tick();
  $users=get_users(array('number'=>20,'fields'=>'ID','orderby'=>'ID','order'=>'ASC','meta_query'=>array(array('key'=>'_aimtr_welcome100_pending','value'=>1),array('key'=>'_dttl_aim_email_verified','value'=>1),array('key'=>'_aim_privy_wallet','compare'=>'EXISTS'))));
  foreach($users as $uid){if(get_transient('aimtr_welcome_skip_'.$uid))continue;set_transient('aimtr_welcome_skip_'.$uid,1,300);try{aim_auto_process((int)$uid);}catch(Throwable $e){update_option('aimtr_auto_wallet_status',array('at'=>time(),'message'=>'Cần kiểm tra phát thưởng ví.'),false);}break;}
  global $wpdb;$t=AIM_Trading::table('bridge');$rows=$wpdb->get_results("SELECT * FROM $t WHERE direction='withdraw' AND status='pending' AND amount<=10000000000 ORDER BY id LIMIT 10",ARRAY_A);
  foreach($rows as $row){if(get_transient('aimtr_auto_skip_'.$row['id']))continue;
   try{$r=self::payout(array('id'=>$row['request_id'],'address'=>strtolower($row['address']),'amount'=>(int)$row['amount']));
    if($r['confirmed']){$ok=$wpdb->update($t,array('status'=>'paid','tx'=>$r['hash']),array('id'=>$row['id'],'status'=>'pending'));self::need($ok!==false,'Chưa lưu xác nhận trả AIM.');}
   }catch(Throwable $e){set_transient('aimtr_auto_skip_'.$row['id'],1,120);update_option('aimtr_auto_wallet_status',array('at'=>time(),'message'=>$e->getMessage()),false);}break;
  }
 }
 static function rules(){
  $v=self::verified_vault();$token=get_option('aim_chain_contract_v1');$file=WP_PLUGIN_DIR.'/aim-wallet-privy/setup/privy-reward-policy.json';$base=json_decode(file_get_contents($file),true);
  foreach($base['rules'] as &$r)foreach($r['conditions'] as &$c)if(($c['field_source']??'')==='ethereum_transaction'&&($c['field']??'')==='to')$c['value']=$token['address'];unset($r,$c);
  $abi=array_values(array_filter(AIM_Wallet_Bridge::artifact()['abi'],function($a){return ($a['name']??'')==='pay'&&($a['type']??'')==='function';}));
  $base['rules'][]=array('name'=>'AIM vault payout up to 10000 AIM','method'=>'eth_sendTransaction','action'=>'ALLOW','conditions'=>array(
   array('field_source'=>'ethereum_transaction','field'=>'chain_id','operator'=>'eq','value'=>'84532'),
   array('field_source'=>'ethereum_transaction','field'=>'to','operator'=>'eq','value'=>$v['address']),
   array('field_source'=>'ethereum_transaction','field'=>'value','operator'=>'eq','value'=>'0x0'),
   array('field_source'=>'ethereum_calldata','field'=>'function_name','operator'=>'eq','value'=>'pay','abi'=>$abi),
   array('field_source'=>'ethereum_calldata','field'=>'pay.amount','operator'=>'lte','value'=>'10000000000000000000000','abi'=>$abi)
  ));return $base['rules'];
 }
}
add_action('user_register',array('AIM_Wallet_Automation','registered'));
