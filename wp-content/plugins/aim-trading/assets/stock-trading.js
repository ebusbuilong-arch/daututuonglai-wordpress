(()=>{'use strict';const c=window.AIMTradingConfig,$=id=>document.getElementById(id),money=v=>new Intl.NumberFormat((window.AIMTradingConfig?.lang==='en'?'en-US':'vi-VN'),{maximumFractionDigits:6}).format(v/1e6)+' AIM',num=v=>new Intl.NumberFormat('en-US',{maximumFractionDigits:4}).format(v);let symbol=$('ex-symbol').textContent,side='buy',account=null,quote=null,busy=false,pending=null,generation=0;const valuations=new Map(),valuationPending=new Set(),valuationErrors=new Map();const en=c.lang==='en';const key='aim-stock-pending-'+c.user_id;
try{if(c.logged)pending=JSON.parse(localStorage.getItem(key)||'null');}catch{}
function pendingSave(value){try{if(value)localStorage.setItem(key,JSON.stringify(value));else localStorage.removeItem(key);}catch{}pending=value;$('st-retry').hidden=!pending;}
async function api(path,body){if(!c.logged)throw Error('Đăng nhập để giao dịch.');const ctrl=new AbortController(),timeout=setTimeout(()=>ctrl.abort(),20000);try{const r=await fetch(c.api+'stocks/'+path,{method:body?'POST':'GET',headers:{'X-WP-Nonce':c.nonce,'Content-Type':'application/json'},credentials:'same-origin',signal:ctrl.signal,...(body?{body:JSON.stringify(body)}:{})});let d;try{d=await r.json();}catch{throw Error('Chưa rõ phản hồi máy chủ.');}if(!r.ok){const e=Error(d.message||'Yêu cầu chưa hoàn tất.');e.status=r.status;throw e;}return d;}finally{clearTimeout(timeout);}}
function node(tag,value){const n=document.createElement(tag);n.textContent=value;return n;}
function table(host,head,rows){const t=document.createElement('table'),thead=document.createElement('thead'),tr=document.createElement('tr');head.forEach(x=>tr.append(node('th',x)));thead.append(tr);t.append(thead);const b=document.createElement('tbody');rows.forEach(r=>{const tr=document.createElement('tr');r.forEach(x=>{const td=document.createElement('td');if(x instanceof Node)td.append(x);else td.textContent=x;tr.append(td);});b.append(tr);});t.append(b);host.replaceChildren(t);host.classList.add('table-scroll');}
const vnBoards=['HOSE','HNX','UPCOM'];
const instruments=c.exchange.instruments;
const vnStocks=Array.isArray(c.vnStocks)&&c.vnStocks.length?c.vnStocks:instruments.filter(x=>vnBoards.includes(x[3]));
const vnSymbols=new Set(vnStocks.map(x=>x[1]));
const supported=new Set([...instruments.map(x=>x[1]),...vnSymbols]);
const isVN=s=>vnSymbols.has(s),currency=s=>isVN(s)?'VND':'USD',scale=s=>isVN(s)?25000000:1000;
const vndFormatter=new Intl.NumberFormat(en?'en-US':'vi-VN',{maximumFractionDigits:0});
const vnd=value=>vndFormatter.format(value)+(en?' VND':' VNĐ');
const priceText=(price,cur)=>cur==='VND'?vnd(price):num(price)+' '+cur;
const amountText=(amount,cur)=>cur==='VND'?vnd(amount*25000000/1e6):money(amount);

function valuationCell(sym,p){
 const box=node('div',''),q=valuations.get(sym);
 if(q){
  const value=Math.round(p.quantity*q.bid*1e6/scale(sym)),pnl=value-Math.max(1,Math.ceil(value*.001))-p.cost;
  const percentage=Number.isFinite(p.cost)&&p.cost>0?pnl/p.cost*100:null;
  const percentText=percentage===null?'—':new Intl.NumberFormat(en?'en-US':'vi-VN',{minimumFractionDigits:2,maximumFractionDigits:2,signDisplay:'exceptZero'}).format(Math.abs(percentage)<0.005?0:percentage)+'%';
  const result=node('strong',amountText(pnl,currency(sym))+' ('+percentText+')');
  result.title=en?'Return on cost paid, after estimated selling fee':'Tỷ suất trên vốn đã trả, sau phí bán dự kiến';result.style.color=pnl<0?'#ef9a9a':pnl>0?'#66d4b5':'inherit';
  const at=node('small',(en?'Reference price · ':'Giá tham khảo · ')+new Date(q.time).toLocaleString(en?'en-GB':'vi-VN',{timeZone:'Asia/Ho_Chi_Minh'})+' (VN)');
  at.style.display='block';box.append(result,at);
 }else box.append(node('span',en?'Price unavailable':'Chưa có giá'));
 if(valuationErrors.has(sym)){const error=node('small',valuationErrors.get(sym));error.style.display='block';box.append(error);}
 const button=node('button',valuationPending.has(sym)?(en?'Updating…':'Đang cập nhật…'):(en?'Update':'Cập nhật'));
 button.type='button';button.disabled=valuationPending.has(sym);button.setAttribute('aria-label',(en?'Update ':'Cập nhật ')+sym);
 button.style.cssText='display:inline-block;width:auto;min-height:0;padding:4px 8px;margin-top:4px;font-size:12px';
 button.onclick=()=>refreshValuation(sym);box.append(button);return box;
}
async function refreshValuation(sym){
 if(valuationPending.has(sym))return;
 valuationPending.add(sym);valuationErrors.delete(sym);if(account)renderAccount();
 try{
  const q=await api('valuation?symbol='+encodeURIComponent(sym));
  if(q.symbol!==sym||!Number.isFinite(q.bid)||q.bid<=0||!Number.isFinite(q.time)||q.time<=0)throw Error(en?'Invalid price':'Giá không hợp lệ');
  valuations.set(sym,q);
 }catch(e){valuationErrors.set(sym,en?'Could not update price; retry.':('Chưa cập nhật được giá: '+e.message));}
 finally{valuationPending.delete(sym);if(account)renderAccount();}
}
async function refreshPortfolio(){
 for(const sym of Object.keys(account?.state?.positions||{}))await refreshValuation(sym);
}

function renderAccount(){
 const s=account.state,positions=Object.entries(s.positions);
 table($('ex-panel-positions'),['Mã','Cổ phiếu','Giá vốn / CP','Vốn đã trả','Giá trị tài sản','Lãi/lỗ tạm tính sau phí bán'],positions.map(([sym,p])=>{
  const cur=currency(sym),quote=valuations.get(sym);
  const marketValue=quote?p.quantity*quote.bid:null;
  const valueText=marketValue===null?'—':cur==='VND'?vnd(marketValue):money(Math.round(marketValue*1e6/scale(sym)));
  return [sym,num(p.quantity),priceText(p.cost*scale(sym)/1e6/p.quantity,cur),amountText(p.cost,cur),valueText,valuationCell(sym,p)];
 }));
 if(!positions.length)$('ex-panel-positions').append(node('p','Chưa có cổ phiếu trong danh mục.'));
 table($('ex-panel-history'),['Thời gian UTC','Lệnh','Mã','Số CP','Giá','Phí','Lãi/lỗ đã chốt'],account.history.map(({created,event})=>{
  const cur=event.currency||currency(event.symbol);
  return [created,event.side==='buy'?'Mua':'Bán',event.symbol,num(event.quantity),priceText(event.price,cur),amountText(event.fee,cur),amountText(event.realized,cur)];
 }));
 $('ex-panel-orders').replaceChildren(node('p','Market và Limit IOC được xử lý ngay, không có lệnh chờ qua phiên.'));
 $('ex-account-status').textContent=account.enabled&&account.configured?'Đã bật · chỉ khớp khi có giá hợp lệ':'Chờ kết nối / kích hoạt nguồn';
 const specs=$('ex-panel-specs');if(!document.getElementById('st-policy')){const p=node('p','Quy ước mô phỏng: 1 AIM = 1.000 USD hoặc 25.000.000 VND (cố định, không phải tỷ giá thị trường); phí mỗi chiều 0,1%; mua nguyên cổ phiếu, không đòn bẩy; mô phỏng thanh toán ngay. Giá vốn bao gồm phí mua.');p.id='st-policy';specs.prepend(p);}}
const marginSection=node('section','');
marginSection.hidden=true;marginSection.className='ex-card';marginSection.style.marginTop='12px';
$('st-form').insertAdjacentElement('afterend',marginSection);
const marginHeading=node('h3',en?'Stock margin · 2×':'Ký quỹ cổ phiếu · 2×');
const marginInfo=node('p',''),marginStatus=node('p',''),marginResult=node('p','');
marginResult.setAttribute('role','status');
const marginLabel=node('label',en?'Shares':'Số cổ phiếu');
const marginQuantity=node('input','');marginQuantity.type='number';marginQuantity.min='100';marginQuantity.max='1000000';marginQuantity.step='100';marginQuantity.value='100';
marginLabel.append(marginQuantity);
const marginOpen=node('button',en?'Open margin position':'Mở vị thế margin');
const marginClose=node('button',en?'Close margin position':'Chốt vị thế margin');
marginOpen.type=marginClose.type='button';
marginSection.append(marginHeading,marginInfo,marginStatus,marginLabel,marginOpen,marginClose,marginResult);
function renderMargin(){
 const position=account?.state?.margin_positions?.[symbol];
 marginSection.hidden=!c.logged||!isVN(symbol)||(!account?.margin_enabled&&!position);
 if(marginSection.hidden)return;
 const valid=quote?.symbol===symbol&&quote.open&&Date.now()-quote.time<=180000&&Date.now()-quote.received<=30000;
 marginLabel.hidden=marginOpen.hidden=!!position;
 marginClose.hidden=!position;
 marginOpen.disabled=busy||!!pending||!valid||!account?.margin_enabled;
 marginClose.disabled=busy||!!pending||!valid||!position;
 const rate=(position?.annual_bps??account.margin_annual_bps)/100;
 marginInfo.textContent=en?'50% initial collateral · Annual interest '+rate+'%':'Ký quỹ ban đầu 50% · Lãi vay năm '+rate+'%';
 if(position){
  const days=Math.max(0,(Date.now()/1000-position.opened_at)/86400);
  const interest=Math.ceil(position.loan*position.annual_bps*days/(10000*365));
  const value=valid?Math.round(position.quantity*quote.bid*1e6/scale(symbol)):null;
  const net=value===null?null:Math.max(0,value-position.loan-interest-Math.max(1,Math.ceil(value/1000)));
  marginStatus.textContent=position.quantity+' CP · Ký quỹ '+money(position.collateral)+' · Dư nợ '+money(position.loan)+(net===null?'':' · Ước tính nhận lại '+money(net));
 }else marginStatus.textContent=valid?'Báo giá SSI đang sẵn sàng.':'Cần báo giá SSI trong phiên.';
}
async function submitMargin(close){
 if(busy||pending||!isVN(symbol)||!c.logged)return;
 const sym=symbol,position=account?.state?.margin_positions?.[sym];
 const qty=close?position?.quantity:Number(marginQuantity.value);
 if(!Number.isSafeInteger(qty)||qty<100||qty>1e6||qty%100!==0)return;
 busy=true;render();marginResult.textContent='';
 try{
  const q=await api('quote?symbol='+encodeURIComponent(sym));
  const value=Math.round(q[close?'bid':'ask']*qty*1e6/scale(sym));
  const fee=Math.max(1,Math.ceil(value/1000));
  const bound=q[close?'bid':'ask']*(close ? 0.995 : 1.005);
  const p={request_id:crypto.randomUUID(),symbol:sym,side:close?'sell':'buy',quantity:qty,bound,order_type:'market',funding:'margin'};
  if(close)p.open_request_id=position.open_request_id;
  const detail=close
   ? 'Giá bán tối thiểu: '+num(bound)+' VND · Dư nợ: '+money(position.loan)
   : 'Ký quỹ dự kiến: '+money(Math.ceil(value/2))+' · Phí mở dự kiến: '+money(fee);
  if(!await confirmStock(p,detail))return;
  pendingSave(p);await send(p);
 }catch(e){marginResult.textContent=e.message;}finally{busy=false;render();}
}
marginOpen.onclick=()=>submitMargin(false);marginClose.onclick=()=>submitMargin(true);
function render(){
 renderMargin();
 const vn=isVN(symbol),cur=currency(symbol);$('st-conversion').textContent=vn?'1 AIM = 25.000.000 VND sức mua mô phỏng':'1 AIM = 1.000 USD sức mua';$('st-settlement').textContent=vn?'Quy ước cố định · Lô 100 CP · Thanh toán ngay trong mô phỏng':'Cổ phiếu quốc tế · Thanh toán ngay trong mô phỏng';$('st-quote-label').textContent=(vn?'Giá bán / mua mô phỏng · ':'Bid / Ask · ')+cur;$('st-limit-title').textContent='Giá giới hạn · '+cur;$('st-quantity').min=vn?'100':'1';$('st-quantity').step=vn?'100':'1';$('st-execution-note').textContent=vn?'Khớp theo giá đóng nến SSI 1 phút, cộng/trừ 0,05% để tạo spread mô phỏng 0,1%; phí mỗi chiều 0,1%. Giờ mô phỏng 09:00–11:30 và 13:00–15:00 (giờ Việt Nam, thứ Hai–thứ Sáu); giá quá 180 giây sẽ khóa lệnh. Không phải Bid/Ask sổ lệnh SSI; chưa mô phỏng T+2, cổ tức và chia tách.':'Admin đối ứng theo Bid/Ask nguồn; không mô phỏng sổ lệnh thật. Lệnh IOC không đạt giá sẽ hủy ngay. Chưa có lệnh chờ GTC, bán khống hoặc xử lý cổ tức/chia tách.';
 const valid=c.logged&&account?.ready&&account.enabled&&account.configured&&supported.has(symbol)&&quote&&quote.symbol===symbol&&quote.open&&Date.now()-quote.time<=180000&&Date.now()-quote.received<=(isVN(symbol)?30000:90000);$('st-mode').textContent=!c.logged?'CHẾ ĐỘ XEM':valid?'SẴN SÀNG':'CHỜ NGUỒN';$('ex-submit').disabled=!valid||busy||!!pending;$('ex-submit').textContent=!c.logged?'Đăng nhập để giao dịch':busy?'Đang xử lý…':valid?(side==='buy'?'Mua':'Bán')+' '+symbol:'Chưa thể đặt lệnh';$('ex-submit').className='action '+side;$('st-refresh').disabled=!account?.configured||!supported.has(symbol)||busy;$('st-type').disabled=busy;$('st-quantity').disabled=busy;$('st-limit').disabled=busy;$('st-limit-label').hidden=$('st-type').value!=='limit_ioc';$('st-retry').hidden=!pending;$('st-retry').disabled=!c.logged||busy;
 if(quote){$('st-quote').textContent=num(quote.bid)+' / '+num(quote.ask);const qty=Number($('st-quantity').value),value=Math.round(qty*quote[side==='buy'?'ask':'bid']*1e6/scale(symbol)),fee=Math.max(1,Math.ceil(value*.001));const ok=Number.isSafeInteger(qty)&&qty>=1&&qty<=1e6&&(!isVN(symbol)||qty%100===0); $('st-value').textContent=ok?money(value):'— AIM';$('st-fee').textContent=ok?money(fee):'— AIM';$('st-total').textContent=ok?money(side==='buy'?value+fee:value-fee):'— AIM';}else{['st-value','st-fee','st-total'].forEach(id=>$(id).textContent='— AIM');$('st-quote').textContent='— / —';}}
async function refreshQuote(){const g=++generation,s=symbol;quote=null;render();if(!c.logged){$('st-status').textContent='Đăng nhập để giao dịch; bạn vẫn có thể xem biểu đồ và lựa chọn mã.';return;}if(!supported.has(s)){$('st-status').textContent='Mã này chưa hỗ trợ giao dịch mô phỏng.';return;}if(!account?.configured){$('st-status').textContent='Chưa cấu hình nguồn Bid/Ask cổ phiếu trên máy chủ.';return;}$('st-status').textContent='Đang lấy báo giá…';try{const q=await api('quote?symbol='+encodeURIComponent(s));if(g!==generation)return;quote=q;$('st-status').textContent='Báo giá lúc '+new Date(q.time).toLocaleTimeString((window.AIMTradingConfig?.lang==='en'?'en-US':'vi-VN'))+' · '+q.source;$('st-source').textContent=isVN(s)?'SSI · Giá nến 1 phút + spread mô phỏng 0,1% · VND':'Phiên chính · USD · Nguồn đặt lệnh có thể khác biểu đồ TradingView.';renderAccount();}catch(e){if(g===generation)$('st-status').textContent=e.message;}finally{if(g===generation)render();}}
async function refreshAccount(){if(!c.logged){$('st-status').textContent='Đăng nhập để giao dịch; anh/chị vẫn có thể xem biểu đồ và lựa chọn mã.';render();$('ex-submit').textContent='Đăng nhập để giao dịch';return;}try{account=await api('account');renderAccount();render();void refreshPortfolio();}catch(e){$('st-status').textContent=e.message;}}
async function send(p){busy=true;render();const status=p.funding==='margin'?marginResult:$('st-result');try{const d=await api('order',p);pendingSave(null);$('st-quantity').value='';$('st-limit').value='';$('st-result').textContent=(d.replayed?'Đã đối chiếu: ':'Đã khớp: ')+(d.event.side==='buy'?'mua ':'bán ')+d.event.quantity+' '+d.event.symbol+' tại '+num(d.event.price)+' '+(d.event.currency||'USD')+' · phí '+money(d.event.fee)+'.';$('ex-cash').textContent='Đang cập nhật…';await fetch(c.api+'account',{cache:'no-store',headers:{'X-WP-Nonce':c.nonce},credentials:'same-origin'}).then(r=>{if(!r.ok)throw Error();return r.json();}).then(a=>{$('ex-cash').textContent=money(a.available_cash??a.cash);}).catch(()=>{$('ex-cash').textContent='Tải lại số dư';});await refreshAccount();}catch(e){if(e.status&&e.status<500)pendingSave(null);status.textContent=e.name==='AbortError'?'Chưa rõ kết quả. Bấm Đối chiếu lệnh vừa gửi; không đặt lệnh mới.':e.message;}finally{busy=false;render();}}
function confirmStock(p,detail){return new Promise(resolve=>{
 const box=node('div','');box.className='mt-confirm';box.setAttribute('role','group');box.setAttribute('aria-label','Xác nhận lệnh cổ phiếu');
 box.append(node('strong','Xác nhận / Confirm'),node('p',(p.side==='buy'?'MUA / BUY ':'BÁN / SELL ')+p.symbol+' · '+p.quantity+' CP'),node('p',p.order_type==='limit_ioc'?'Limit IOC: '+num(p.bound)+' '+currency(p.symbol):'Market · Giá khớp do máy chủ xác định'),node('p',detail||('Dự kiến: '+$('st-total').textContent)));
 const actions=node('div',''),cancel=node('button','Hủy / Cancel'),ok=node('button','Xác nhận / Confirm');actions.className='mt-confirm-actions';cancel.type=ok.type='button';ok.className='mt-confirm-accept';actions.append(cancel,ok);box.append(actions);const finish=v=>{box.remove();resolve(v);};cancel.onclick=()=>finish(false);ok.onclick=()=>finish(true);box.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();finish(false);}});$('ex-submit').insertAdjacentElement('afterend',box);cancel.focus();
 });}
$('st-form').onsubmit=async e=>{e.preventDefault();if(!c.logged||busy||pending||!account?.enabled)return;busy=true;render();const sym=symbol,orderSide=side,qty=Number($('st-quantity').value),type=$('st-type').value,limit=Number($('st-limit').value);try{if(!Number.isSafeInteger(qty)||qty<1||qty>1e6||(isVN(sym)&&qty%100!==0))throw Error('Nhập số lượng hợp lệ; mã Việt Nam dùng bội số 100 cổ phiếu.');const q=await api('quote?symbol='+encodeURIComponent(sym));const bound=type==='limit_ioc'?limit:q[orderSide==='buy'?'ask':'bid']*(orderSide==='buy'?1.005:.995);if(!Number.isFinite(bound)||bound<=0)throw Error('Nhập giá giới hạn hợp lệ.');const p={request_id:crypto.randomUUID(),symbol:sym,side:orderSide,quantity:qty,bound,order_type:type};if(!await confirmStock(p))return;pendingSave(p);await send(p);}catch(e){$('st-result').textContent=e.message;}finally{busy=false;render();}};
$('st-retry').onclick=()=>{if(pending&&!busy)send(pending);};$('st-refresh').onclick=refreshQuote;['st-type','st-quantity','st-limit'].forEach(id=>$(id).addEventListener('input',render));['buy','sell'].forEach(s=>$('ex-'+s).addEventListener('click',()=>{side=s;render();}));
const portfolioRefresh=setInterval(()=>{if(!document.hidden&&!busy&&!pending)void refreshPortfolio();},60000);
const quoteGuard=setInterval(render,1000),vnRefresh=setInterval(()=>{if(isVN(symbol)&&!document.hidden&&!busy&&!pending)refreshQuote();},20000);window.addEventListener('pagehide',()=>{clearInterval(portfolioRefresh);clearInterval(quoteGuard);clearInterval(vnRefresh);});
document.addEventListener('aim-symbol-change',e=>{symbol=e.detail.symbol;marginResult.textContent='';$('st-quantity').value=isVN(symbol)?'100':'1';refreshQuote();});pendingSave(pending);if(isVN(symbol))$('st-quantity').value='100';refreshAccount().then(refreshQuote);
})();
