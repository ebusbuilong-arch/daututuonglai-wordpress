<?php
// Never expose a web-triggered executor. No secrets or HTTP cron endpoints.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
if(PHP_INT_SIZE<8){fwrite(STDERR,"Requires 64-bit PHP.\n");exit(1);}
$options=getopt('',array('check'));
// wp-config.php owns DISABLE_WP_CRON; do not redefine it here.
$aim_bootstrapped=false;
register_shutdown_function(function()use(&$aim_bootstrapped){if(!$aim_bootstrapped){fwrite(STDERR,"AIM worker: WordPress bootstrap failed.\n");exit(1);}});
define('WP_USE_THEMES',false);
require dirname(__DIR__,3).'/wp-load.php';
$aim_bootstrapped=true;
if(!class_exists('AIM_Trading')||version_compare(AIM_Trading::VERSION,'0.3.4','<')){fwrite(STDERR,"Activate AIM Trading 0.3.4 first.\n");exit(1);}
if(!AIM_Limit_Orders::ready()||get_option('aimtr_spot_execution_schema')!=='1'){fwrite(STDERR,"Open WordPress admin once to migrate orders table.\n");exit(1);}
// Transactions are only safe when all transactional tables use InnoDB.
global $wpdb;
foreach(array('accounts','ledger','orders','futures','futures_events','workshop','workshop_events','bridge') as $suffix){
 $table=AIM_Trading::table($suffix);
 $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table));
 if(strtoupper((string)$engine)!=='INNODB'){fwrite(STDERR,"Table engine check failed: $suffix\n");exit(1);}
}
if(isset($options['check'])){echo "AIM Trading ".AIM_Trading::VERSION.": PHP + WordPress + 8 InnoDB tables OK. No orders executed.\n";exit(0);}
AIM_Futures::tick();
AIM_Workshop::tick();
if(!get_transient('aimtr_competition_checked')){
 $awards=AIM_Competitions::tick();
 if(is_wp_error($awards)){fwrite(STDERR,'AIM awards: '.$awards->get_error_message()."\n");}
 else set_transient('aimtr_competition_checked',1,60);
}
$result=AIM_Limit_Orders::tick();
if(is_wp_error($result)){fwrite(STDERR,$result->get_error_message()."\n");exit(1);}
// Silence healthy idle ticks to avoid filling the journal every five seconds.
if($result['filled']||$result['errors'])echo gmdate('c').' '.$result['message']."\n";
// Forex is isolated from the existing workers; missing configuration never stops Crypto.
if(class_exists('AIM_Forex_Accounts'))AIM_Forex_Accounts::tick();

// Commodity pilot is isolated; no credentials means no simulated fills.
if(class_exists('AIM_Commodity_Accounts'))AIM_Commodity_Accounts::tick();

if(class_exists('AIM_Wallet_Automation'))AIM_Wallet_Automation::tick();
