(function(root,factory){if(typeof module==='object'&&module.exports)module.exports=factory();else root.DTTLPortfolio=factory();})(typeof globalThis!=='undefined'?globalThis:this,function(){
'use strict';
const MAX=1e12;
function fail(m){throw new Error(m);}
function num(v,min,max,label){if(typeof v!=='number'||!Number.isSafeInteger(v)||v<min||v>max)fail(label+' không hợp lệ.');return v;}
function symbol(v){if(typeof v!=='string'||!/^[A-Z][A-Z0-9]{1,9}$/.test(v))fail('Mã gồm 2–10 chữ/số, bắt đầu bằng chữ.');return v;}
function date(v){if(typeof v!=='string'||v.length>35||!Number.isFinite(Date.parse(v)))fail('Thời điểm không hợp lệ.');return v;}
function clean(input){
 if(!input||input.version!==1||!Array.isArray(input.trades)||input.trades.length>1000)fail('Tệp không đúng định dạng mô phỏng hoặc vượt 1.000 giao dịch.');
 const s={version:1,initial:num(input.initial,1,MAX,'Vốn'),trades:[],quotes:{}};
 input.trades.forEach(t=>{if(!t||!['buy','sell'].includes(t.side))fail('Loại giao dịch không hợp lệ.');if(typeof t.note!=='string'||t.note.length>300)fail('Ghi chú tối đa 300 ký tự.');
 const x={side:t.side,symbol:symbol(t.symbol),qty:num(t.qty,1,1e6,'Số lượng'),price:num(t.price,1,1e9,'Giá'),fee:num(t.fee,0,1e9,'Phí'),note:t.note,at:date(t.at)};
 if(x.qty*x.price>MAX)fail('Giá trị một giao dịch tối đa 1.000 tỷ VNĐ.');s.trades.push(x);});
 if(!input.quotes||typeof input.quotes!=='object'||Array.isArray(input.quotes)||Object.keys(input.quotes).length>100)fail('Dữ liệu giá không hợp lệ.');
 const symbols=new Set(s.trades.map(t=>t.symbol));
 Object.entries(input.quotes).forEach(([k,q])=>{symbol(k);if(!symbols.has(k)||!q)fail('Giá cập nhật có mã chưa từng giao dịch.');s.quotes[k]={price:num(q.price,1,1e9,'Giá cập nhật'),at:date(q.at)};});
 return s;
}
function calculate(input){
 const s=clean(input);let cash=s.initial,realized=0,fees=0;const positions=new Map();const journal=[];
 for(const t of s.trades){let p=positions.get(t.symbol)||{symbol:t.symbol,qty:0,cost:0,lastBuy:0};const gross=t.qty*t.price;let profit=null;
 if(t.side==='buy'){
  if(gross+t.fee>cash+0.001)fail('Không đủ tiền giả lập để mua và trả phí.');if(p.qty+t.qty>1e6)fail('Mỗi mã tối đa 1 triệu cổ phiếu giả lập.');
  cash-=gross+t.fee;p.qty+=t.qty;p.cost+=gross+t.fee;p.lastBuy=t.price;
 }else{
  if(t.qty>p.qty)fail('Không đủ cổ phiếu để bán; mô phỏng không bán khống.');
  const costSold=p.cost*(t.qty/p.qty);profit=gross-t.fee-costSold;
  if(cash+gross-t.fee<0)fail('Không đủ tiền để trả phí bán.');cash+=gross-t.fee;realized+=profit;p.qty-=t.qty;p.cost-=costSold;
  if(p.qty===0)p.cost=0;
 }
 positions.set(t.symbol,p);if(positions.size>100)fail('Tối đa 100 mã trong một danh mục.');fees+=t.fee;journal.push(Object.assign({},t,{profit,cash}));
 }
 let value=0,cost=0;const holdings=[];
 for(const p of positions.values()){if(!p.qty)continue;const q=s.quotes[p.symbol];const price=q?q.price:p.lastBuy;const marketValue=p.qty*price;
 if(marketValue>MAX)fail('Giá trị đánh dấu một mã vượt giới hạn 1.000 tỷ VNĐ.');value+=marketValue;cost+=p.cost;
 holdings.push(Object.assign({},p,{average:p.cost/p.qty,price,marketValue,unrealized:marketValue-p.cost,quoteAt:q?q.at:null}));}
 const total=cash+value;return {state:s,cash,realized,fees,holdings,journal,value,cost,total,unrealized:value-cost,profit:total-s.initial,returnPct:(total/s.initial-1)*100};
}
function fresh(initial=100000000){return {version:1,initial,trades:[],quotes:{}};}
return {calculate,fresh};
});
