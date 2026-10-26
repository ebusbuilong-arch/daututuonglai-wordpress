(function(root){'use strict';
function valid(values,period){return Array.isArray(values)&&Number.isInteger(period)&&period>0&&values.every(Number.isFinite);}
function sma(values,period=20){if(!valid(values,period))return [];let sum=0;return values.map((v,i)=>{sum+=v;if(i>=period)sum-=values[i-period];return i>=period-1?sum/period:null;});}
// Seed with the first complete SMA window, then exponential smoothing.
function ema(values,period=20){if(!valid(values,period))return [];let sum=0,previous=null;const weight=2/(period+1);return values.map((v,i)=>{if(i<period){sum+=v;if(i<period-1)return null;previous=sum/period;}else previous=v*weight+previous*(1-weight);return previous;});}
if(typeof module==='object'&&module.exports)module.exports={sma,ema};else root.AIMIndicators={sma,ema};
})(typeof window==='object'?window:this);
