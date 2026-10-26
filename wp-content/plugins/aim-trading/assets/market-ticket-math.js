(function(root){'use strict';
function fee(market,notional,quantity,side){return market==='stock'?notional*(side==='sell'?.0025:.0015):market==='forex'?notional*.00002:quantity*2.5;}
function quantity(spec,market,side,price,leverage,available,shares,percent){if(!(price>0)||!(leverage>0)||!(spec.step>0)||!Number.isFinite(available)||![25,50,75,100].includes(percent))return 0;let raw;if(market==='stock'&&side==='sell')raw=shares*percent/100;else{const unit=spec.multiplier*price/spec.conversion,cost=unit/leverage+fee(market,unit,1,side);raw=available*percent/100/cost;}const count=Math.floor((raw+1e-10)/spec.step);return Number((Math.max(0,count)*spec.step).toFixed(8));}
function result(spec,market,side,entry,exit,qty){if(!(entry>0&&exit>0&&qty>0))return null;const sign=side==='buy'?1:-1,gross=(exit-entry)*qty*spec.multiplier/spec.conversion;return sign*gross-fee(market,entry*qty*spec.multiplier/spec.conversion,qty,side)-fee(market,exit*qty*spec.multiplier/spec.conversion,qty,side==='buy'?'sell':'buy');}
const api={fee,quantity,result};if(typeof module==='object'&&module.exports)module.exports=api;else root.AIMTicketMath=api;
})(typeof window==='object'?window:this);
