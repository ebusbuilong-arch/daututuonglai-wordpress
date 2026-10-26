(() => {
'use strict';
let uid = 0;
document.querySelectorAll('.dttl-stock').forEach(root => {
 const en = root.dataset.lang === 'en';
 const t = (vi, english) => en ? english : vi;
 const form = root.querySelector('form');
 const board = form.elements.board, symbol = form.elements.symbol, frame = form.elements.frame;
 const status = root.querySelector('.dttl-stock-status'), name = root.querySelector('.dttl-stock-name'), price = root.querySelector('.dttl-stock-price');
 const plot = root.querySelector('.dttl-stock-plot'), canvas = plot.querySelector('canvas'), ctx = canvas.getContext('2d'), tip = plot.querySelector('.dttl-stock-tooltip');
 const count = root.querySelector('[name=count]'), pan = root.querySelector('[name=pan]'), list = root.querySelector('datalist');
 list.id = 'dttl-ssi-symbols-' + (++uid); symbol.setAttribute('list', list.id);
 const nf = new Intl.NumberFormat(en ? 'en-US' : 'vi-VN', {maximumFractionDigits: 2});
 let bars = [], visible = [], selected = null, hover = -1, geometry = {}, serial = 0, controller, timer, busy = false, activeQuery = null;
 const text = (el, value) => { el.textContent = value; };
 async function get(path, params, signal) {
  const url = new URL(root.dataset.endpoint + path); Object.entries(params).forEach(([k,v]) => url.searchParams.set(k,v));
  const response = await fetch(url, {signal, credentials:'same-origin'}); const data = await response.json();
  if (!response.ok) throw new Error(data.message || t('Chưa tải được dữ liệu.', 'Data unavailable.'));
  return data;
 }
 async function loadSymbols() {
  const current = board.value;
  try {
   const data = await get('symbols', {board:current}); if (current !== board.value) return;
   list.replaceChildren();
   for (const item of data.symbols) { const option = document.createElement('option'); option.value = item.symbol; option.textContent = en ? item.nameEn : item.name; list.append(option); }
  } catch (_) { /* The chart request reports actionable errors. */ }
 }
 function resetView() { const size = Math.min(Number(count.value), bars.length); pan.max = String(Math.max(0, bars.length - size)); pan.value = pan.max; hover = -1; tip.hidden = true; draw(); }
 async function load(automatic = false) {
  if (automatic && (document.hidden || busy)) return;
  const query = {symbol:symbol.value.trim().toUpperCase(),board:board.value,frame:frame.value};
  if (!/^[A-Z0-9]{2,15}$/.test(query.symbol)) { text(status,t('Nhập mã chứng khoán hợp lệ.', 'Enter a valid stock symbol.')); return; }
  symbol.value = query.symbol; clearTimeout(timer); if(controller) controller.abort(); controller = new AbortController(); const id=++serial;
  const same = activeQuery === JSON.stringify(query); busy=true;
  if (!same) { bars=[]; selected=null; name.textContent=query.symbol; price.textContent='—'; tip.hidden=true; draw(); }
  text(status,t('Đang tải dữ liệu SSI…','Loading SSI data…')); root.setAttribute('aria-busy','true');
  try {
   const data = await get('history', query, controller.signal); if(id!==serial)return;
   if (!Array.isArray(data.bars)) throw new Error(t('Dữ liệu chưa hợp lệ.','Invalid data.'));
   const wasLatest = Number(pan.value)===Number(pan.max); const oldPan=Number(pan.value);
   bars=data.bars; selected=data.security; activeQuery=JSON.stringify(query);
   text(name,`${selected.symbol} · ${en ? selected.nameEn : selected.name} · ${selected.board}`);
   const last=bars.at(-1), previous=bars.at(-2);
   if(last) { const change=previous ? (last.close-previous.close) : 0; const pct=previous && previous.close ? change/previous.close*100:0; text(price,`${nf.format(last.close)}${previous ? `  ${change>=0?'+':''}${nf.format(change)} (${pct>=0?'+':''}${nf.format(pct)}%)`:''}`); price.style.color=change>=0?'#087e63':'#d54454'; }
   text(status,bars.length ? `${t('Nến cuối','Last candle')}: ${last.date} · ${bars.length} ${t('nến','candles')} · ${t('Tải lúc','Fetched')} ${new Date(data.fetchedAt).toLocaleTimeString(en?'en-GB':'vi-VN',{timeZone:'Asia/Ho_Chi_Minh'})}` : t('Chưa có dữ liệu trong khoảng thời gian này. Thử khung 1 ngày.','No data in this period. Try the daily timeframe.'));
   resetView(); if(same&&!wasLatest){pan.value=String(Math.min(oldPan,Number(pan.max)));draw();}
   timer=setTimeout(()=>load(true),Math.max(60,data.pollSeconds||300)*1000);
  } catch(error) { if(error.name==='AbortError'||id!==serial)return; text(status,(same&&bars.length?t('Đang giữ dữ liệu cũ. ','Showing previous data. '):'')+error.message); timer=setTimeout(()=>load(true),120000); }
  finally { if(id===serial){busy=false;root.removeAttribute('aria-busy');} }
 }
 function draw() {
  const width=plot.clientWidth||700, height=plot.clientHeight||430, dpr=window.devicePixelRatio||1;
  canvas.width=Math.round(width*dpr);canvas.height=Math.round(height*dpr);canvas.style.width=width+'px';canvas.style.height=height+'px';ctx.setTransform(dpr,0,0,dpr,0,0);ctx.clearRect(0,0,width,height);
  ctx.fillStyle='#fbfcfe';ctx.fillRect(0,0,width,height);ctx.font='11px system-ui';
  const left=10,right=width<500?65:82,top=28,bottom=28,ph=height*.66,vtop=height*.76,vh=height-vtop-bottom;
  visible=bars.slice(Number(pan.value),Number(pan.value)+Number(count.value));
  if(!visible.length){ctx.fillStyle='#64748b';ctx.textAlign='center';ctx.fillText(t('Biểu đồ sẽ hiển thị khi có dữ liệu','Chart will appear when data is available'),width/2,height/2);return;}
  const low=Math.min(...visible.map(b=>b.low)),high=Math.max(...visible.map(b=>b.high)),pad=(high-low)*.06||high*.01||1,min=low-pad,max=high+pad;
  const pw=width-left-right,step=pw/visible.length,y=v=>top+(max-v)/(max-min)*(ph-top),vol=Math.max(...visible.map(b=>b.volume),1);
  geometry={left,step,pw,width,height};
  ctx.textAlign='left';
  for(let i=0;i<=4;i++){const value=min+(max-min)*i/4,yy=y(value);ctx.strokeStyle='#e5eaf1';ctx.beginPath();ctx.moveTo(left,yy);ctx.lineTo(width-right,yy);ctx.stroke();ctx.fillStyle='#64748b';ctx.fillText(nf.format(value),width-right+7,yy+4);}
  ctx.fillStyle='#64748b';ctx.fillText(t('Khối lượng','Volume'),left,vtop-9);
  visible.forEach((bar,i)=>{const x=left+(i+.5)*step,color=bar.close>=bar.open?'#0b9878':'#e05260';ctx.strokeStyle=color;ctx.fillStyle=color;ctx.beginPath();ctx.moveTo(x,y(bar.high));ctx.lineTo(x,y(bar.low));ctx.stroke();ctx.fillRect(x-Math.max(1,step*.65)/2,Math.min(y(bar.open),y(bar.close)),Math.max(1,step*.65),Math.max(1,Math.abs(y(bar.open)-y(bar.close))));ctx.globalAlpha=.55;const v=bar.volume/vol*vh;ctx.fillRect(x-step*.32,vtop+vh-v,Math.max(1,step*.64),v);ctx.globalAlpha=1;});
  const labels=Math.min(width<500?3:5,visible.length);ctx.fillStyle='#64748b';ctx.textAlign='center';
  for(let i=0;i<labels;i++){const ix=Math.round(i*(visible.length-1)/Math.max(1,labels-1));const label=visible[ix].date;ctx.fillText(label.slice(5,10)+(frame.value==='1d'?'':' '+label.slice(11)),Math.max(40,Math.min(width-right-30,left+(ix+.5)*step)),height-7);}
  if(hover>=0&&hover<visible.length){const x=left+(hover+.5)*step;ctx.strokeStyle='#7890a8';ctx.setLineDash([3,3]);ctx.beginPath();ctx.moveTo(x,top);ctx.lineTo(x,vtop+vh);ctx.stroke();ctx.setLineDash([]);}
 }
 function showAt(clientX){const rect=canvas.getBoundingClientRect();hover=Math.max(0,Math.min(visible.length-1,Math.floor((clientX-rect.left-geometry.left)/geometry.step)));const b=visible[hover];if(!b)return;tip.replaceChildren();const title=document.createElement('strong');title.textContent=`${selected?.symbol||symbol.value} · ${b.date}`;tip.append(title);for(const [key,label] of [['open',t('Mở','Open')],['high',t('Cao','High')],['low',t('Thấp','Low')],['close',t('Đóng','Close')],['volume',t('KL','Vol')]]){const line=document.createElement('span');line.textContent=`${label}: ${nf.format(b[key])}`;tip.append(line);}tip.hidden=false;tip.style.left=(clientX-rect.left>rect.width/2?12:Math.max(12,rect.width-185))+'px';draw();}
 canvas.addEventListener('pointermove',e=>showAt(e.clientX));canvas.addEventListener('pointerleave',()=>{hover=-1;tip.hidden=true;draw();});canvas.addEventListener('pointerdown',e=>showAt(e.clientX));
 plot.addEventListener('keydown',e=>{if(!['ArrowLeft','ArrowRight'].includes(e.key))return;e.preventDefault();pan.value=String(Math.max(0,Math.min(Number(pan.max),Number(pan.value)+(e.key==='ArrowLeft'?-5:5))));draw();});
 form.addEventListener('submit',e=>{e.preventDefault();load();});frame.addEventListener('change',()=>load());
 board.addEventListener('change',()=>{symbol.value=board.value==='HOSE'?'FPT':board.value==='HNX'?'SHS':'ACV';loadSymbols();load();});
 count.addEventListener('change',resetView);pan.addEventListener('input',()=>{hover=-1;tip.hidden=true;draw();});root.querySelector('.dttl-stock-refresh').addEventListener('click',()=>load());
 new ResizeObserver(draw).observe(plot);loadSymbols();load();
});
})();
