(function(root,factory){const math=factory();if(typeof module==='object'&&module.exports)module.exports=math;else root.AIMOrderMath=math;})(typeof globalThis==='object'?globalThis:this,()=>{
 'use strict';
 const U=1e6,Q=1e8;
 function units(raw){if(typeof raw!=='string'||!/^\d{1,8}(\.\d{1,8})?$/.test(raw))return null;const [whole,fraction='']=raw.split('.');const n=Number(whole)*Q+Number(fraction.padEnd(8,'0'));return Number.isSafeInteger(n)&&n>0&&n<=1e6*Q?n:null;}
 function amounts(n,price,side){const gross=Math.round(n/Q*price*U),fee=Math.ceil(gross/1000);return {gross,fee,total:side==='buy'?gross+fee:gross-fee};}
 function validate(raw,price,side,cash,held){const n=units(raw);if(n===null)return 'Nhập khối lượng hợp lệ, tối đa 8 số lẻ.';if(!Number.isFinite(price)||price<=0)return 'Chờ báo giá mới để đặt lệnh.';const a=amounts(n,price,side);if(!Number.isSafeInteger(a.gross)||a.gross<U||a.gross>100000*U)return 'Giá trị lệnh phải từ 1 đến 100.000 AIM.';if(side==='buy'&&a.total>cash)return 'AIM khả dụng không đủ cho lệnh và phí.';if(side==='sell'&&n>held)return 'Số lượng bán vượt tài sản đang giữ.';return '';}
 function maxBuy(cash,price){if(!Number.isSafeInteger(cash)||cash<=0||!Number.isFinite(price)||price<=0)return 0;let lo=0,hi=1e6*Q;while(lo<hi){const mid=lo+Math.ceil((hi-lo)/2),a=amounts(mid,price,'buy');if(a.total<=cash&&a.gross<=100000*U)lo=mid;else hi=mid-1;}return lo;}
 function depth(quote,n,side){
  if(!Number.isSafeInteger(n)||n<=0)return null;
  const rows=quote?.[side==='buy'?'asks':'bids'];if(!Array.isArray(rows)||!rows.length)return null;
  let left=n,sum=0,levels=0,last=null;
  for(const row of rows){const p=Number(row[0]),size=Math.round(Number(row[1])*Q);if(!Number.isFinite(p)||p<=0||!Number.isSafeInteger(size)||size<=0||(last!==null&&(side==='buy'?p<=last:p>=last)))return null;last=p;const take=Math.min(left,size);sum+=take*p;left-=take;levels++;if(!left)break;}
  if(left)return null;const price=sum/n,best=Number(rows[0][0]);return {price,levels,slippageBps:Math.max(0,(side==='buy'?price-best:best-price)/best*10000),...amounts(n,price,side)};
 }
 function maxBuyDepth(cash,quote){if(!Number.isSafeInteger(cash)||cash<=0)return 0;let lo=0,hi=1e6*Q;while(lo<hi){const mid=lo+Math.ceil((hi-lo)/2),a=depth(quote,mid,'buy');if(a&&a.total<=cash&&a.gross<=100000*U)lo=mid;else hi=mid-1;}return lo;}
 return {units,amounts,validate,maxBuy,depth,maxBuyDepth};
});
