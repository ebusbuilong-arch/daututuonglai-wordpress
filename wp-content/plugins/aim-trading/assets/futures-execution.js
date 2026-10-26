/* Display estimate only. Authoritative prices and balances are calculated by PHP. */
(function(root){'use strict';
function estimate(q,buy,quantity){
 const units=Math.round(Number(quantity)*1e8),rows=q?.[buy?'asks':'bids'];
 if(!Number.isSafeInteger(units)||units<=0||!Array.isArray(rows)||!rows.length)return null;
 let left=units,total=0,levels=0;
 for(const [p,size] of rows){const price=Number(p),depth=Math.round(Number(size)*1e8);if(!(price>0)||!Number.isFinite(price)||!Number.isSafeInteger(depth)||depth<=0)return null;const take=Math.min(left,depth);total+=take*price;left-=take;levels++;if(!left)break;}
 if(left)return null;const price=total/units,best=Number(q[buy?'ask':'bid']);return{price,levels,slippageBps:Math.max(0,(buy?price-best:best-price)/best*10000)};
}
if(typeof module==='object'&&module.exports)module.exports={estimate};else root.AIMFuturesDepth={estimate};
})(typeof window==='object'?window:this);
