<?php
defined('ABSPATH')||exit;
final class AIM_Article_Rewards {
 static function claim_key($id){return 'aimtr_article100_'.(int)$id;}
 static function approved($new,$old,$post){
  if($new!=='publish'||$old==='publish'||$post->post_type!=='post'||!get_post_meta($post->ID,'_dttl_member_submission',true))return;
  if(!array_key_exists('existing_user_max',AIM_Wallet_Automation::config()))return;
  $key=self::claim_key($post->ID);$claim=get_option($key);
  if(!$claim){if(get_post_meta($post->ID,'_dttl_aim_reward_recorded',true))return;
   add_option($key,array('uid'=>(int)$post->post_author,'post'=>(int)$post->ID,'amount'=>100000000,'at'=>time()),'',false);$claim=get_option($key);
  }
  if(!$claim)return;
  // Claim is immutable and independent of the old one-AIM ledger cap.
  update_post_meta($post->ID,'_dttl_aim_reward_recorded',$claim['uid']);
  if(empty($claim['hash']))update_post_meta($post->ID,'_aimtr_article100_pending',$claim['uid']);
  if(function_exists('aim_auto_queue_user'))aim_auto_queue_user($claim['uid']);
 }
 static function process($uid){
  if(!AIM_Wallet_Automation::enabled()||!get_user_meta($uid,'_dttl_aim_email_verified',true)||!get_user_meta($uid,'_aim_privy_wallet',true))return null;
  $ids=get_posts(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>5,'fields'=>'ids','orderby'=>'ID','order'=>'ASC','meta_key'=>'_aimtr_article100_pending','meta_value'=>$uid));
  foreach($ids as $id){
   try{
    $key=self::claim_key($id);$claim=get_option($key);AIM_Wallet_Automation::need($claim&&(int)$claim['uid']===$uid&&(int)$claim['post']===(int)$id&&(int)$claim['amount']===100000000,'Bản ghi thưởng bài chưa khớp.');
    $v=AIM_Wallet_Bridge::config();$r=AIM_Wallet_Automation::payout(array('id'=>'0x'.hash('sha256','aim-article100-v1:'.$v['address'].':'.aim_chain_article_key($id)),'address'=>AIM_Wallet_Bridge::wallet($uid),'amount'=>100000000));
    if($r['confirmed']){$claim['hash']=$r['hash'];update_option($key,$claim,false);AIM_Wallet_Automation::need(get_option($key)===$claim,'Chưa lưu xác nhận thưởng bài.');delete_post_meta($id,'_aimtr_article100_pending');update_post_meta($id,'_aimtr_article100_paid',$r['hash']);}
    return array('state'=>'pending','hash'=>$r['hash'],'message'=>$r['confirmed']?'Đã nhận 100 AIM cho bài được duyệt. Đang kiểm tra các khoản còn lại.':'Đã gửi 100 AIM thưởng bài viết; đang chờ xác nhận mạng.');
   }catch(Throwable $e){return array('state'=>'pending','message'=>$e->getMessage());}
  }return null;
 }
 static function tick(){
  if(!AIM_Wallet_Automation::enabled())return;
  $ids=get_posts(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>20,'fields'=>'ids','orderby'=>'ID','order'=>'ASC','meta_key'=>'_aimtr_article100_pending'));
  foreach($ids as $id){if(get_transient('aimtr_article_skip_'.$id))continue;set_transient('aimtr_article_skip_'.$id,1,120);self::process((int)get_post_meta($id,'_aimtr_article100_pending',true));break;}
 }
}
add_action('transition_post_status',array('AIM_Article_Rewards','approved'),5,3);
