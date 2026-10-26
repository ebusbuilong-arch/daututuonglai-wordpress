<?php
define('ABSPATH', __DIR__);function add_action(...$a){}function is_wp_error($x){return false;}
require dirname(__DIR__).'/wallet-bridge.php';
function aim_chain_rpc($method,$args){global $responses;return $responses[$method];}
function ok($name,$fn,$reject=false){try{$fn();if($reject)throw new Exception('expected rejection');echo "PASS $name\n";}catch(RuntimeException $e){if(!$reject)throw $e;echo "PASS $name rejects\n";}}
$member='0xc97c2821767ba8dff3c51804357b27afe501c4d2';$c=['address'=>'0x870e5ba1e12c061c1938e9d6c86d6c5c29dfb531'];
$log=['address'=>$c['address'],'topics'=>[AIM_Wallet_Bridge::artifact()['selectors']['deposit'],'0x'.str_repeat('0',24).substr($member,2)],'data'=>'0x'.str_pad('55de6a779bbac0000',64,'0',STR_PAD_LEFT)];
// 99 * 10^18 encoded independently from the contract ABI.
$r=['from'=>'0x701798d910e620ac044d0f331a819af0b1da3aaf','to'=>'0x0000000071727de22e5e9d8baf0edac6f37da032','logs'=>[$log],'status'=>'0x1','blockNumber'=>'0x64','blockHash'=>'0xabc'];
ok('AA event credits exact 99 AIM',function()use($r,$c,$member){if(AIM_Wallet_Bridge::transfer_log($r,$c,'deposit',$member)!==99000000)throw new Exception('wrong amount');});
foreach(['member','emitter','topic','missing','duplicate'] as $case){$bad=$r;if($case==='member')$bad['logs'][0]['topics'][1]='0x'.str_repeat('0',64);if($case==='emitter')$bad['logs'][0]['address']=$member;if($case==='topic')$bad['logs'][0]['topics'][0]='0x'.str_repeat('0',64);if($case==='missing')$bad['logs']=[];if($case==='duplicate')$bad['logs'][]=$log;ok($case,fn()=>AIM_Wallet_Bridge::transfer_log($bad,$c,'deposit',$member),true);}
$responses=['eth_chainId'=>'0x14a34','eth_getTransactionReceipt'=>$r,'eth_blockNumber'=>'0x6f','eth_getBlockByNumber'=>['hash'=>'0xabc']];$hash='0x'.str_repeat('1',64);
ok('12 canonical confirmations',fn()=>AIM_Wallet_Bridge::receipt($hash));
foreach(['reverted','confirmations','chain','reorg'] as $case){$saved=$responses;if($case==='reverted')$responses['eth_getTransactionReceipt']['status']='0x0';if($case==='confirmations')$responses['eth_blockNumber']='0x6e';if($case==='chain')$responses['eth_chainId']='0x1';if($case==='reorg')$responses['eth_getBlockByNumber']['hash']='0xdef';ok($case,fn()=>AIM_Wallet_Bridge::receipt($hash),true);$responses=$saved;}
