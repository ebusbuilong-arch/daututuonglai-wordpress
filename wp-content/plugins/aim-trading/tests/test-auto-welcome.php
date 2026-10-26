<?php
define('ABSPATH',__DIR__);function add_action(...$x){}function aim_auto_enabled(){return true;}
$meta=[];$options=['aimtr_wallet_automation_v1'=>['existing_user_max'=>4,'enabled'=>true,'vault'=>'vault','token'=>'token'],'aimtr_vault_v1'=>['address'=>'vault','token'=>'token']];
function get_option($k,$d=false){global $options;return $options[$k]??$d;}function add_option($k,$v,...$x){global $options;if(isset($options[$k]))return false;$options[$k]=$v;return true;}function update_option($k,$v,...$x){global $options;$options[$k]=$v;}
function get_user_meta($u,$k,$s=true){global $meta;return $meta[$u][$k]??null;}function update_user_meta($u,$k,$v){global $meta;$meta[$u][$k]=$v;}function get_current_user_id(){return 8;}
class AIM_Trading{static function table($x){return $x;}}
class DB{public $last_error='';public $done=null;function prepare($q,...$x){return $q;}function get_var($q){return $this->done;}}
$wpdb=new DB;
require dirname(__DIR__).'/wallet-automation.php';
function check($ok,$name){if(!$ok)throw new Exception($name);echo "PASS $name\n";}
AIM_Wallet_Automation::registered(8);check($meta[8]['_aimtr_auto_deposit100']===1,'new signup enrolled');
check(AIM_Wallet_Automation::auto_deposit_state(8)==='off','unverified account blocked');
$meta[8]['_dttl_aim_email_verified']=1;$meta[8]['_aim_privy_wallet']=['address'=>'member'];
check(AIM_Wallet_Automation::auto_deposit_state(8)==='waiting','wait for confirmed welcome');$meta[8]['_aimtr_welcome100_confirmed']='hash';
check(AIM_Wallet_Automation::auto_deposit_state(8)==='ready','confirmed welcome ready');
$a=['owner'=>str_repeat('a',32)];$b=['owner'=>str_repeat('b',32)];
check(AIM_Wallet_Automation::auto_deposit_claim($a)['owned'],'first device owns claim');
check(!AIM_Wallet_Automation::auto_deposit_claim($b)['owned'],'second device denied ownership');
$a['tx']='0x'.str_repeat('1',64);AIM_Wallet_Automation::auto_deposit_claim($a);
check(AIM_Wallet_Automation::auto_deposit_claim($b)['hash']===$a['tx'],'other device can recover existing hash');
$wpdb->done=1;check(AIM_Wallet_Automation::auto_deposit_state(8)==='done','any prior deposit ends automatic funding');
$meta[9]=$meta[8];unset($meta[9]['_aimtr_auto_deposit100']);check(AIM_Wallet_Automation::auto_deposit_state(9)==='off','existing account not enrolled retroactively');
