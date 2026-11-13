/* Admin-only pilot. Server owns prices, fills, permissions and all accounting. */
(()=>{'use strict';const c=window.AIMTradingConfig,$=id=>document.getElementById(id);if(!['forex','commodity'].includes(c.market)||!$('fx-pilot'))return;
const key='aimtr_'+c.market+'_pending_'+c.user_id,money=v=>new Intl.NumberFormat((window.AIMTradingConfig?.lang==='en'?'en-US':'vi-VN'),{maximumFractionDigits:6}).format(v/1e6);let account=null,busy=false,pending=null,storageOK=true,refreshing=false;
try{if(c.logged)pending=JSON.parse(localStorage.getItem(key)||'null');if(pending&&(!pending.request_id||!['open','close','protect','transfer_in','transfer_out'].includes(pending.type)))throw Error();}catch{storageOK=false;$('fx-pilot-message').textContent='Chưa đọc được yêu cầu trước. Tạm khóa thao tác để tránh ghi trùng.';}
const node=(tag,value)=>{const el=document.createElement(tag);el.textContent=value;return el;};
const forex=c.market==='forex',commodity=c.market==='commodity',positionsPanel=$('ex-panel-positions');
const positionStatus=(forex||commodity)?node('p',''):null;
const closeRetry=(forex||commodity)?node('button','Đối chiếu lệnh đóng'):null;
let closeNotice='',closeReceipt=null;
if(positionStatus)positionStatus.setAttribute('role','status');
if(closeRetry){
 closeRetry.type='button';closeRetry.className='start';closeRetry.hidden=true;closeRetry.onclick=perform;
}
function renderPositionStatus(){
 if(!positionStatus)return;
 let status=closeNotice;
 if(closeReceipt){
  const {id,units}=closeReceipt;
  const position=account?.state?.positions?.[id];
  status=!account?.storage_ready?'Đã khớp lệnh đóng #'+id+' ('+units+' đơn vị). Đang cập nhật danh sách vị thế.'
   :position?'Đã đóng '+units+' đơn vị vị thế #'+id+'. Còn '+position.units+' đơn vị.'
   :'Đã đóng toàn bộ vị thế #'+id+'.';
 }
 positionStatus.hidden=!status;
 if(positionStatus.textContent!==status)positionStatus.textContent=status;
 if(positionStatus.parentNode!==positionsPanel)positionsPanel.prepend(positionStatus);
 if(closeRetry.parentNode!==positionsPanel)positionStatus.after(closeRetry);
}
function setCloseNotice(status){closeNotice=status;closeReceipt=null;renderPositionStatus();}
function removeConfirmedFullClose(receipt,replayed){
 const previous=account?.state?.positions?.[receipt.id];
 if(replayed||!previous||receipt.units<previous.units)return;
 const row=[...positionsPanel.querySelectorAll('[data-position-id]')].find(item=>item.dataset.positionId===String(receipt.id));
 if(!row)return;
 row.remove();
 if(!positionsPanel.querySelector('[data-position-id]'))positionsPanel.append(node('p','Chưa có vị thế.'));
}
function controls(){const locked=!c.logged||busy||refreshing||!!pending||!storageOK||!account?.storage_ready;$('fx-pilot-open-submit').disabled=locked||!account?.pilot_enabled||(!savedOrderQuote&&(!account?.feed_configured||!account?.worker?.online))||!quoteUsable();$('fx-pilot-transfer-submit').disabled=locked||($('fx-pilot-direction').value==='transfer_in'&&!account?.pilot_enabled);$('fx-pilot-retry').hidden=!pending;$('fx-pilot-retry').disabled=busy||refreshing;$('fx-pilot-refresh').disabled=busy||refreshing;document.querySelectorAll('[data-fx-mutation]').forEach(b=>b.disabled=locked);if(closeRetry){closeRetry.hidden=pending?.type!=='close';closeRetry.disabled=busy||refreshing;}$('fx-pilot-open-submit').textContent=!c.logged?'Đăng nhập để giao dịch':busy?'Đang xử lý…':$('fx-pilot-side').value==='long'?'Mở Long':'Mở Short';}
async function api(path,body,spot=false){if(!c.logged)throw Error('Đăng nhập để giao dịch.');const ctrl=new AbortController(),timer=setTimeout(()=>ctrl.abort(),15000);try{const r=await fetch(c.api+(spot?'':c.market+'/')+path,{cache:'no-store',method:body?'POST':'GET',headers:{'X-WP-Nonce':c.nonce,'Content-Type':'application/json'},credentials:'same-origin',signal:ctrl.signal,...(body?{body:JSON.stringify(body)}:{})});const d=await r.json();if(!r.ok){const e=Error(d.message||'Chưa xử lý được yêu cầu Forex.');e.status=r.status;throw e;}return d;}finally{clearTimeout(timer);}}
function table(headers){const t=document.createElement('table'),h=document.createElement('tr');headers.forEach(v=>h.append(node('th',v)));t.append(h);return t;}
function draw(){if(dismissConfirmation&&commodity)dismissConfirmation();const s=account.state||{cash:0,positions:{},realized:0,fees:0},positions=Object.values(s.positions);$('fx-pilot-readiness').textContent=(account.storage_ready?'✓ Dữ liệu tài khoản sẵn sàng':'Chưa nâng cấp dữ liệu')+' · '+(account.pilot_enabled?'Đã bật giao dịch AIM':'Đang chờ kích hoạt')+' · '+(account.feed_configured?'Đã cấu hình nguồn':'Chưa cấu hình nguồn giá')+' · '+account.worker.message;$('ex-account-status').textContent=account.pilot_enabled?'Giao dịch AIM · theo nguồn giá':'Chờ kích hoạt';$('ex-panel-orders').textContent='Market xử lý ngay; TP/SL gắn theo từng vị thế. Chưa hỗ trợ lệnh Limit chờ.';
 $('fx-pilot-cash').textContent=money(s.cash);$('fx-pilot-margin').textContent=money(positions.reduce((v,p)=>v+p.margin,0));$('fx-pilot-realized').textContent=money(s.realized);$('fx-pilot-fees').textContent=money(s.fees);positionsPanel.replaceChildren();
 renderPositionStatus();
 if(!positions.length)$('ex-panel-positions').append(node('p','Chưa có vị thế.'));
 positions.forEach(p=>{const wrap=document.createElement('div');wrap.className='fx-position';wrap.dataset.positionId=String(p.id);wrap.append(node('strong','#'+p.id+' · '+p.symbol+' · '+p.side.toUpperCase()),node('p',p.units+' đơn vị · Mở '+p.entry+' · Ký quỹ '+money(p.margin)+' AIM'+(p.exit_pending?' · Đang chờ đóng: '+p.exit_pending:'')));
 const closeForm=document.createElement('form'),label=node('label','Số đơn vị đóng '),input=document.createElement('input'),button=node('button','Đóng vị thế');input.value=String(p.units);input.inputMode='decimal';input.required=true;input.setAttribute('aria-label','Số đơn vị đóng vị thế '+p.id);button.dataset.fxMutation='1';button.className='start';label.append(input);closeForm.append(label,button);closeForm.onsubmit=async e=>{e.preventDefault();if(!/^\d+(?:\.\d{1,8})?$/.test(input.value.trim()))return message('Khối lượng đóng không hợp lệ.');const order={type:'close',id:p.id,units:Number(input.value)};
  if(!commodity)return send(order);
  if(button.disabled||order.units<=0||order.units>p.units)return;
  button.disabled=true;
  try{
   const data=await api('order-quote?symbol='+encodeURIComponent(p.symbol)+'&position='+p.id);
   if(!button.isConnected||busy||refreshing||pending)return;
   setCloseNotice('');
   const price=data.quote[p.side==='long'?'bid':'ask'];
   if(await confirmOrder(order,button,['#'+p.id+' · '+p.symbol.replace('_','/')+' · '+order.units+' đơn vị','Giá '+price+' USD',quoteLabel(data.quote,data.saved)])){
    await send({...order,quote_token:data.token});
   }
  }catch(error){message(error.message);setCloseNotice('Vị thế #'+p.id+' · '+error.message);}
  finally{controls();}};wrap.append(closeForm);
 const protect=document.createElement('form'),tp=document.createElement('input'),sl=document.createElement('input'),save=node('button','Lưu TP/SL');tp.value=p.tp===null?'':String(p.tp);sl.value=p.sl===null?'':String(p.sl);tp.placeholder='TP';sl.placeholder='SL';tp.setAttribute('aria-label','Chốt lời vị thế '+p.id);sl.setAttribute('aria-label','Cắt lỗ vị thế '+p.id);save.dataset.fxMutation='1';save.className='start';protect.append(tp,sl,save);protect.onsubmit=e=>{e.preventDefault();send({type:'protect',id:p.id,tp:tp.value.trim()||null,sl:sl.value.trim()||null});};wrap.append(protect);$('ex-panel-positions').append(wrap);});
 const t=table(['UTC','Nghiệp vụ','Vị thế','Lãi/lỗ trước phí','Phí','Đối ứng quỹ']);for(const h of account.history||[]){for(const ev of h.events){const row=document.createElement('tr');[h.created,ev.kind+(ev.reason?' · '+ev.reason:'')+(ev.saved_quote?' · Báo giá lưu '+quoteDate(ev.quote_time):''),ev.id??'—',money(ev.pnl||0),money(ev.fee||0),money(ev.pool_delta||0)].forEach(v=>row.append(node('td',String(v))));t.append(row);}}$('ex-panel-history').replaceChildren(t);controls();}
function message(v){$('fx-pilot-message').textContent=v;}
async function refresh(silent=false){
 if(refreshing)return false;
 if(!c.logged){$('fx-pilot-readiness').textContent='Đăng nhập để giao dịch.';controls();return false;}
 refreshing=true;controls();
 try{
  const loadPositions=api('account').then(data=>{account=data;draw();}).catch(error=>{
   account=null;
   for(const id of ['fx-pilot-cash','fx-pilot-margin','fx-pilot-realized','fx-pilot-fees'])$(id).textContent='—';
   renderPositionStatus();
   throw error;
  });
  const results=await Promise.allSettled([loadPositions,api('account',null,true)]);
  const spot=results[1].status==='fulfilled'?results[1].value:null,available=spot?(spot.available_cash??spot.cash):null;
  const spotOK=spot&&spot.active&&typeof available==='number'&&Number.isFinite(available);
  $('ex-cash').textContent=spotOK?money(available)+' AIM':'Chưa đọc được số dư';
  const ok=results[0].status==='fulfilled'&&!!spotOK;
  if(!ok&&!busy&&!silent)message('Chưa tải đủ số dư mới. Bấm Làm mới tài khoản. / Could not refresh balances. Please refresh the account.');
  return ok;
 }finally{refreshing=false;controls();}
}
async function perform(){
 if(busy||refreshing||!pending)return;busy=true;controls();message('Đang xử lý; giữ nguyên mã yêu cầu nếu kết nối gián đoạn.');
 const request=pending,closing=(forex||commodity)&&request.type==='close';
 if(closing)setCloseNotice('Đang gửi lệnh đóng vị thế #'+request.id+'…');
 let resultMessage='',succeeded=false;
 try{
  const completedType=pending.type;const r=await api('action',pending);localStorage.removeItem(key);pending=null;succeeded=true;
  if(closing){
   closeReceipt=r.events.find(e=>e.kind==='close'&&e.id===request.id)??null;
   closeNotice=closeReceipt?'':'Chưa nhận được xác nhận khớp cho vị thế #'+request.id+'.';
   if(closeReceipt){
    removeConfirmedFullClose(closeReceipt,r.replayed);
    closeNotice='Đã khớp lệnh đóng #'+request.id+'. Đang cập nhật danh sách vị thế.';
    if(!positionStatus.hidden)positionStatus.textContent=closeNotice;
   }else renderPositionStatus();
  }
  if(completedType==='open'&&!r.events.some(e=>e.kind==='open_skipped'))for(const id of ['fx-pilot-lots','fx-pilot-tp','fx-pilot-sl'])$(id).value='';
  if(['transfer_in','transfer_out'].includes(completedType))$('fx-pilot-amount').value='';estimate();
  resultMessage=r.replayed?'Đã đối chiếu yêu cầu trước, không hạch toán lần hai.':r.events.some(e=>e.kind==='open_skipped')?'Đã xử lý vị thế cũ. Lệnh mở mới chưa được thực hiện.':'Đã thực hiện thành công.';
  message('Đã ghi nhận. Đang cập nhật số dư và lịch sử… / Updating balances and history…');
 }catch(e){
  if([400,403,409].includes(e.status)){localStorage.removeItem(key);pending=null;}
  resultMessage=e.name==='AbortError'?'Chưa rõ kết quả. Bấm kiểm tra lại yêu cầu trước; không đặt lại bằng mã mới.':e.message;
  if(closing)setCloseNotice('Vị thế #'+request.id+' · '+resultMessage+(pending?' Dùng nút Đối chiếu lệnh đóng.':''));
 }finally{
  const updated=await refresh();busy=false;controls();
  if(commodity&&!succeeded&&!pending)void loadQuote();
  message(resultMessage+(succeeded?(updated?' Số dư, vị thế và lịch sử đã cập nhật.':' Giao dịch đã ghi nhận nhưng chưa tải đủ số dư mới. Bấm Làm mới tài khoản; không gửi lại lệnh.'):''));
 }
}
async function send(body){if(!c.logged||busy||refreshing||pending||!storageOK)return message('Cần đối chiếu yêu cầu trước trước khi thao tác tiếp.');try{body.request_id=crypto.randomUUID();const raw=JSON.stringify(body);localStorage.setItem(key,raw);if(localStorage.getItem(key)!==raw)throw Error();pending=body;}catch{storageOK=false;controls();return message('Không lưu được mã yêu cầu trên trình duyệt; chưa gửi lệnh.');}await perform();}
let dismissConfirmation=null;
function confirmOrder(order,anchor=$('fx-pilot-open-submit'),details=null){
 if(dismissConfirmation)dismissConfirmation();
 return new Promise(resolve=>{
  const box=node('div','');box.className='mt-confirm';box.setAttribute('role','group');box.setAttribute('aria-label',order.type==='close'?'Xác nhận đóng vị thế':'Xác nhận mở lệnh / Confirm order');
  const title=order.type==='close'?'Xác nhận đóng vị thế':'Xác nhận mở lệnh / Confirm order';
  box.append(node('strong',title));
  (details??[order.symbol.replace('_','/')+' · '+(order.side==='long'?'LONG':'SHORT')+' · '+order.lots+' lot · '+order.leverage+'x',$('mt-estimate').textContent]).forEach(line=>box.append(node('p',line)));
  if(order.tp||order.sl)box.append(node('p','TP: '+(order.tp||'—')+' · SL: '+(order.sl||'—')));
  box.append(node('small','Giá khớp có thể thay đổi. / Execution price may change.'));
  const actions=node('div',''),cancel=node('button','Hủy / Cancel'),accept=node('button','Xác nhận / Confirm');actions.className='mt-confirm-actions';cancel.type=accept.type='button';accept.className='mt-confirm-accept';actions.append(cancel,accept);box.append(actions);
  const finish=ok=>{box.remove();dismissConfirmation=null;resolve(ok);};dismissConfirmation=()=>finish(false);cancel.onclick=()=>finish(false);accept.onclick=()=>finish(true);
  anchor.insertAdjacentElement('afterend',box);cancel.focus();box.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();finish(false);anchor.focus();}});
 });
}
$('fx-pilot-open').onsubmit=async e=>{
 e.preventDefault();if($('fx-pilot-open-submit').disabled)return;
 const order={type:'open',symbol:$('fx-pilot-symbol').value,side:$('fx-pilot-side').value,lots:$('fx-pilot-lots').value.trim(),leverage:Number($('fx-pilot-leverage').value),tp:$('fx-pilot-tp').value.trim()||null,sl:$('fx-pilot-sl').value.trim()||null};
 if(!/^\d+(?:\.\d+)?$/.test(order.lots)||Number(order.lots)<=0)return message('Nhập khối lượng hợp lệ. / Enter a valid volume.');
 if(!quoteUsable())return message('Chưa có báo giá dùng được. Vui lòng thử lại.');
 const token=orderQuoteToken;
 const details=commodity?[order.symbol.replace('_','/')+' · '+order.side.toUpperCase()+' · '+order.lots+' lot · '+order.leverage+'x',$('mt-estimate').textContent,quoteLabel(liveQuote,savedOrderQuote)]:null;
 if(!await confirmOrder(order,$('fx-pilot-open-submit'),details))return;
 if(busy||refreshing||pending)return message('Đang xử lý yêu cầu trước. / A previous request is being processed.');
 if(!quoteUsable()||(commodity&&token!==orderQuoteToken))return message('Báo giá đã thay đổi. Vui lòng xem và xác nhận lại.');
 send(commodity?{...order,quote_token:token}:order);
};
$('fx-pilot-transfer').onsubmit=e=>{e.preventDefault();const raw=$('fx-pilot-amount').value.trim();if(!/^\d{1,6}(?:\.\d{1,6})?$/.test(raw))return message('Nhập số AIM với tối đa 6 số lẻ.');const amount=Math.round(Number(raw)*1e6);if(!Number.isSafeInteger(amount)||amount<=0)return message('Số AIM phải lớn hơn 0.');send({type:$('fx-pilot-direction').value,amount});};

let liveQuote=null,liveSpec=null,savedOrderQuote=false,orderQuoteToken=null,quoteSerial=0,quoteTimer=null;
function quoteDate(time){return new Date(time).toLocaleString('vi-VN',{timeZone:'Asia/Ho_Chi_Minh'});}
function quoteLabel(quote,saved){return (saved?'Báo giá đã lưu':'Báo giá hiện tại')+' · '+(quote.time_basis==='provider_response'?'Nguồn phản hồi lúc ':'Mốc giá ')+quoteDate(quote.time)+' (giờ Việt Nam) · '+quote.source;}
function quoteUsable(){return commodity&&savedOrderQuote?liveQuote?.symbol===$('fx-pilot-symbol').value:quoteFresh();}
function mapped(v){return ({'WTI':'WTICO_USD','BRENT':'BCO_USD'})[v]||v.replace('/','_');}
function quoteFresh(){return !!liveQuote&&liveQuote.open&&liveQuote.symbol===$('fx-pilot-symbol').value&&Date.now()-liveQuote.time<=(liveQuote.time_basis==='provider_response'?120000:180000)&&Date.now()-liveQuote.received<=90000;}
function estimate(){
 if(!quoteUsable()){$('mt-estimate').textContent=commodity?'Chưa có báo giá giao dịch cho mã này.':'Chưa có giá mới trong phiên; tạm khóa mở lệnh.';return;}
 const lots=Number($('fx-pilot-lots').value),lev=Number($('fx-pilot-leverage').value),price=liveQuote[$('fx-pilot-side').value==='long'?'ask':'bid'],value=lots*liveSpec.lot_units*price/1000;
 $('mt-estimate').textContent=lots>0&&Number.isFinite(value)?'Giá trị '+money(Math.round(value*1e6))+' AIM · Ký quỹ ước tính '+money(Math.ceil(value/lev*1e6))+' AIM, chưa gồm phí.':'Nhập khối lượng hợp lệ.';
}
async function loadQuote(preview=null){
 clearTimeout(quoteTimer);const serial=++quoteSerial;liveQuote=null;liveSpec=null;savedOrderQuote=false;orderQuoteToken=null;controls();estimate();
 if(!c.logged){$('mt-price').textContent='Đăng nhập để xem báo giá đặt lệnh.';return;}
 if(forex&&(document.hidden||pending?.type==='close')){
  if(!document.hidden)$('mt-price').textContent='Đang chờ đối chiếu lệnh đóng vị thế.';
  quoteTimer=setTimeout(loadQuote,60000);return;
 }
 const symbol=$('fx-pilot-symbol').value;
 if(commodity&&!(c.commoditySymbols??[]).includes(symbol)){
  $('mt-price').textContent='Chưa có mã hàng hóa có báo giá dùng được.';
  return;
 }
 $('mt-price').textContent='Đang lấy báo giá '+symbol+'…';
 try{
 if(commodity){
  const data=preview?.quote?.symbol===symbol?preview:await api('order-quote?symbol='+encodeURIComponent(symbol));
  if(serial!==quoteSerial)return;
  liveQuote=data.quote;liveSpec=data.spec;savedOrderQuote=data.saved;orderQuoteToken=data.token;
  $('mt-price').textContent='Mua '+Number(liveQuote.ask.toFixed(5))+' / Bán '+Number(liveQuote.bid.toFixed(5))+' USD · '+quoteLabel(liveQuote,savedOrderQuote);
 }else{
const r=await fetch(c.api+'market-launch/quote?symbol='+encodeURIComponent(symbol),{headers:{'X-WP-Nonce':c.nonce},credentials:'same-origin'});const d=await r.json();if(serial!==quoteSerial)return;if(!r.ok)throw Error(d.message||'Chưa có nguồn giá.');liveQuote=d.quote;liveSpec=d.spec;
 $('mt-price').textContent='Mua '+Number(liveQuote.ask.toFixed(5))+' / Bán '+Number(liveQuote.bid.toFixed(5))+' USD · '+new Date(liveQuote.time).toLocaleTimeString((window.AIMTradingConfig?.lang==='en'?'en-US':'vi-VN'))+' · '+(liveQuote.time_basis==='provider_response'?'Thời điểm phản hồi nguồn · phiên mô phỏng':(liveQuote.open?'Trong phiên':'Đóng phiên'))+' · '+liveQuote.source;

 }
 }catch(e){if(serial===quoteSerial)$('mt-price').textContent=e.message;}finally{if(serial===quoteSerial){controls();estimate();quoteTimer=setTimeout(loadQuote,60000);}}
}
document.addEventListener('aim-symbol-change',e=>{if(dismissConfirmation)dismissConfirmation();$('fx-pilot-symbol').value=mapped(e.detail.symbol);loadQuote(e.detail.commodityQuote);});
for(const side of ['buy','sell'])$('ex-'+side).addEventListener('click',()=>{if(dismissConfirmation)dismissConfirmation();$('fx-pilot-side').value=side==='buy'?'long':'short';$('fx-pilot-open-submit').className='action '+side;controls();estimate();});
for(const id of ['fx-pilot-lots','fx-pilot-leverage'])$(id).addEventListener('input',()=>{if(dismissConfirmation)dismissConfirmation();estimate();});
for(const id of ['fx-pilot-tp','fx-pilot-sl'])$(id).addEventListener('input',()=>{if(dismissConfirmation)dismissConfirmation();});
$('fx-pilot-symbol').value=commodity&&!(c.commoditySymbols??[]).includes(mapped($('ex-symbol').textContent))?'':mapped($('ex-symbol').textContent);
const freshnessTimer=setInterval(()=>{controls();estimate();},1000);
if(forex)document.addEventListener('visibilitychange',()=>{if(!document.hidden&&!quoteFresh())void loadQuote();});
const positionTimer=forex&&c.logged?setInterval(()=>{
 if(document.hidden||busy||refreshing||pending||positionsPanel.closest('[hidden]')||positionsPanel.contains(document.activeElement))return;
 if(!account||Object.keys(account.state?.positions??{}).length)void refresh(true);
},20000):null;
window.addEventListener('pagehide',()=>{clearTimeout(quoteTimer);clearInterval(freshnessTimer);clearInterval(positionTimer);quoteSerial++;});
loadQuote();

$('fx-pilot-direction').onchange=controls;$('fx-pilot-refresh').onclick=()=>refresh();$('fx-pilot-retry').onclick=perform;
if(pending){
 message('Có yêu cầu chưa đối chiếu từ lần trước. Bấm kiểm tra lại để tránh ghi trùng.');
 if((forex||commodity)&&pending.type==='close')setCloseNotice('Vị thế #'+pending.id+' · Đang chờ đối chiếu lệnh đóng trước.');
}
refresh();
})();
