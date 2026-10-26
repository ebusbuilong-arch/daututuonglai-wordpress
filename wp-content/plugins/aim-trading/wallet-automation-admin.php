<?php
defined('ABSPATH') || exit;
// Configuration is deliberately owner-operated; installing this file never changes signer access.
function aimtr_flow_normalize($rules){
 if(!is_array($rules))return null;$out=array();
 foreach($rules as $r){if(!is_array($r)||!is_array($r['conditions']??null))return null;unset($r['id'],$r['name']);$cs=array();foreach($r['conditions'] as $c){
  if(($c['field_source']??'')==='ethereum_transaction'){
   if(($c['field']??'')==='to')$c['value']=strtolower($c['value']);
   if(($c['field']??'')==='chain_id')$c['value']=(string)$c['value'];
   if(($c['field']??'')==='value'&&in_array($c['value']??null,array(0,'0','0x0','0x00'),true))$c['value']='0x0';
  }$cs[]=wp_json_encode(aim_auto_canonical($c));
 }sort($cs,SORT_STRING);$r['conditions']=$cs;$out[]=wp_json_encode(aim_auto_canonical($r));}sort($out,SORT_STRING);return $out;
}
function aimtr_flow_matches($policy,$rules){return is_array($policy)&&($policy['chain_type']??'')==='ethereum'&&($policy['version']??'')==='1.0'&&aimtr_flow_normalize($policy['rules']??null)===aimtr_flow_normalize($rules);}
function aimtr_flow_enable(){
 AIM_Wallet_Automation::need(current_user_can('manage_options'),'Chỉ quản trị viên được bật.');
 AIM_Wallet_Automation::need(function_exists('aim_auto_process_legacy')&&function_exists('aim_v2_policy_headers')&&aim_auto_enabled(),'Cần ví 0.5.4 và hệ thống thưởng đang hoạt động.');
 $v=AIM_Wallet_Automation::verified_vault();AIM_Wallet_Automation::need(strtolower(AIM_Wallet_Bridge::wallet(get_current_user_id()))===strtolower($v['owner']),'Cần tài khoản chủ kho AIM.');
 $reserve=AIM_Wallet_Bridge::rpc('eth_call',array(array('to'=>$v['token'],'data'=>'0x70a08231'.str_pad(substr($v['address'],2),64,'0',STR_PAD_LEFT)),'latest'));
 $minimum='0x'.AIM_Wallet_Automation::words(10000000000);
 AIM_Wallet_Automation::need(preg_match('/^0x[0-9a-f]{64}$/iD',$reserve)&&strcmp(strtolower($reserve),$minimum)>=0,'Nạp ít nhất 10.000 AIM vào dự trữ kho trước khi bật.');
 $rules=AIM_Wallet_Automation::rules();$id=(string)aim_auto_setting('AIM_PRIVY_REWARD_POLICY_ID');AIM_Wallet_Automation::need(preg_match('/^[a-zA-Z0-9_-]+$/D',$id),'Thiếu policy ID hợp lệ.');
 $url='https://api.privy.io/v1/policies/'.$id;$body=array('name'=>'AIM rewards and bounded vault payout','rules'=>$rules);$headers=aim_v2_policy_headers($url,$body);if(is_wp_error($headers))throw new RuntimeException($headers->get_error_message());
 $get=function()use($url,$headers){$r=wp_remote_get($url,array('timeout'=>15,'redirection'=>0,'headers'=>array('privy-app-id'=>AIM_PRIVY_APP,'Authorization'=>$headers['Authorization']),'limit_response_size'=>65536));AIM_Wallet_Automation::need(!is_wp_error($r)&&wp_remote_retrieve_response_code($r)===200,'Không đọc được chính sách ký.');return json_decode(wp_remote_retrieve_body($r),true);};
 $old=$get();AIM_Wallet_Automation::need(aimtr_flow_matches($old,$rules)||aim_v2_policy_matches($old,array_slice($rules,0,2)),'Chính sách hiện tại khác dự kiến; cần kiểm tra, chưa thay đổi.');
 if(!aimtr_flow_matches($old,$rules)){$r=wp_remote_request($url,array('method'=>'PATCH','timeout'=>20,'redirection'=>0,'headers'=>$headers,'body'=>wp_json_encode($body),'limit_response_size'=>65536));AIM_Wallet_Automation::need(!is_wp_error($r)&&wp_remote_retrieve_response_code($r)===200,'Chưa cập nhật được quyền ký.');}
 AIM_Wallet_Automation::need(aimtr_flow_matches($get(),$rules),'Chưa xác minh được quyền ký; luồng chưa bật.');
 global $wpdb;$s=AIM_Wallet_Automation::config();$s=array('enabled'=>true,'existing_user_max'=>$s['existing_user_max']??(int)$wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->users}"),'vault'=>$v['address'],'token'=>$v['token'],'activated_at'=>$s['activated_at']??time());update_option(AIM_Wallet_Automation::OPTION,$s,false);AIM_Wallet_Automation::need(get_option(AIM_Wallet_Automation::OPTION)===$s,'Chưa lưu được cấu hình.');
}
function aimtr_flow_admin(){
 if(!current_user_can('manage_options'))return;$message='';
 if($_SERVER['REQUEST_METHOD']==='POST'){check_admin_referer('aimtr_wallet_flow');try{if(($_POST['flow_action']??'')==='enable'){aimtr_flow_enable();$message='Đã bật cho tài khoản đăng ký từ thời điểm này.';}elseif(($_POST['flow_action']??'')==='pause'){$s=AIM_Wallet_Automation::config();$s['enabled']=false;update_option(AIM_Wallet_Automation::OPTION,$s,false);$message='Đã dừng xử lý tự động. Tài khoản mới vẫn dùng AIM nạp từ ví.';}}catch(Throwable $e){$message=$e->getMessage();}}
 echo '<div class="wrap"><h1>100 AIM vào ví · giao dịch · rút về ví</h1>';if($message)echo '<div class="notice notice-info"><p>'.esc_html($message).'</p></div>';
 echo '<p>Trạng thái: <strong>'.(AIM_Wallet_Automation::enabled()?'Đang bật':'Chưa bật / tạm dừng').'</strong></p><p>Dùng đúng AIM hiện tại trên Base Sepolia. Tài khoản mới xác minh email và mở ví nhận một khoản 100 AIM trực tiếp từ kho. Tài khoản đang nhận dở theo luồng cũ chỉ được bổ sung phần còn thiếu. Mỗi bài chia sẻ được duyệt từ bản cập nhật này nhận 100 AIM từ kho, một lần mỗi bài, áp dụng cả tài khoản cũ. Không cấp thêm 100 AIM nội bộ. Số dư tài khoản cũ được giữ.</p><p>Người dùng xác nhận nạp AIM từ ví để giao dịch. Khi yêu cầu rút, hệ thống trả số dư khả dụng về ví, gồm vốn và lãi/lỗ đã chốt. Kho cần đủ AIM; thiếu dự trữ thì yêu cầu chờ, không báo đã trả.</p><p><strong>Quyền ký bổ sung:</strong> máy chủ được gọi pay trên đúng kho AIM, đúng mạng 84532, không gửi ETH; tối đa 10.000 AIM mỗi giao dịch. Đây là giới hạn mỗi lần, không phải hạn mức tổng. Khoản lớn hơn vẫn chờ quản trị xử lý.</p><p><a href="'.esc_url(home_url('/tai-khoan/#dm-wallet')).'">Mở ví admin để bổ sung ít nhất 10.000 AIM vào dự trữ kho</a></p><form method="post">';wp_nonce_field('aimtr_wallet_flow');echo '<button class="button button-primary" name="flow_action" value="enable">Cho phép kho trả AIM tự động và bật luồng 100 AIM</button> <button class="button" name="flow_action" value="pause">Tạm dừng xử lý</button></form></div>';
}
add_action('admin_menu',function(){add_submenu_page('aim-trading','Luồng ví 100 AIM','Luồng ví 100 AIM','manage_options','aim-wallet-flow','aimtr_flow_admin');});
