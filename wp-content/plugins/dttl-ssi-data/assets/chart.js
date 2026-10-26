(() => {
'use strict';

const calc = values => {
 const sma=n=>values.map((v,i)=>i<n-1?null:values.slice(i-n+1,i+1).reduce((a,b)=>a+b,0)/n);
 const ema=(a,n)=>{let last=null,seed=[];return a.map(v=>{if(v===null)return null;if(last===null){seed.push(v);if(seed.length<n)return null;last=seed.reduce((x,y)=>x+y,0)/n;}else last+=(v-last)*2/(n+1);return last;});};
 const ma=sma(20), e12=ema(values,12),e26=ema(values,26),macd=values.map((v,i)=>e26[i]===null?null:e12[i]-e26[i]),signal=ema(macd,9);
 let gain=0,loss=0;
 const rsi=values.map((v,i)=>{if(!i)return null;const delta=v-values[i-1];if(i<=14){gain+=Math.max(delta,0)/14;loss+=Math.max(-delta,0)/14;}else{gain=(gain*13+Math.max(delta,0))/14;loss=(loss*13+Math.max(-delta,0))/14;}return i<14?null:loss===0?(gain===0?50:100):100-100/(1+gain/loss);});
 const sd=values.map((v,i)=>ma[i]===null?null:Math.sqrt(values.slice(i-19,i+1).reduce((sum,x)=>sum+(x-ma[i])**2,0)/20));
 return {ma20:ma,ma50:sma(50),ema20:ema(values,20),upper:ma.map((v,i)=>v===null?null:v+2*sd[i]),lower:ma.map((v,i)=>v===null?null:v-2*sd[i]),rsi,macd,signal,histogram:macd.map((v,i)=>signal[i]===null?null:v-signal[i])};
};
let uid = 0;
function syncTheme(){let theme=new URLSearchParams(location.search).get('theme')||'light';try{if(window.parent!==window&&window.parent.document.documentElement.dataset.theme)theme=window.parent.document.documentElement.dataset.theme;}catch{}document.documentElement.dataset.ssiTheme=theme==='dark'?'dark':'light';}
syncTheme();
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

 const indicators=document.createElement('div');indicators.className='dttl-indicators';
 const overlay=document.createElement('select'),oscillator=document.createElement('select');
 for(const [value,label] of [['none',t('Không','None')],['ma','MA20 / MA50'],['ema','EMA20'],['bb','Bollinger (20, 2)']])overlay.add(new Option(label,value));
 for(const [value,label] of [['none',t('Không','None')],['rsi','RSI (14)'],['macd','MACD (12, 26, 9)']])oscillator.add(new Option(label,value));
 for(const [control,label] of [[overlay,t('Đường giá','Price overlay')],[oscillator,t('Chỉ báo','Indicator')]]){const el=document.createElement('label');el.textContent=label+' ';el.append(control);indicators.append(el);control.addEventListener('change',draw);}
 const legend=document.createElement('span');legend.className='dttl-indicator-legend';indicators.append(legend);plot.before(indicators);
 const sub=document.createElement('canvas');sub.className='dttl-indicator-panel';sub.hidden=true;plot.after(sub);
 let studies={};
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
  if (!same) { bars=[]; studies={}; selected=null; name.textContent=query.symbol; price.textContent='—'; tip.hidden=true; draw(); }
  text(status,t('Đang tải dữ liệu SSI…','Loading SSI data…')); root.setAttribute('aria-busy','true');
  try {
   const data = await get('history', query, controller.signal); if(id!==serial)return;
   if (!Array.isArray(data.bars)) throw new Error(t('Dữ liệu chưa hợp lệ.','Invalid data.'));
   const wasLatest = Number(pan.value)===Number(pan.max); const oldPan=Number(pan.value);
   bars=data.bars; studies=calc(bars.map(b=>b.close)); selected=data.security; activeQuery=JSON.stringify(query);
   text(name,`${selected.symbol} · ${en ? selected.nameEn : selected.name} · ${selected.board}`);
   const last=bars.at(-1), previous=bars.at(-2);
   if(data.provisionalAt) text(status,t('Nến ngày đang hình thành từ dữ liệu phút','Daily candle derived from minute data'));
   if(last) { const change=previous ? (last.close-previous.close) : 0; const pct=previous && previous.close ? change/previous.close*100:0; text(price,`${nf.format(last.close)}${previous ? `  ${change>=0?'+':''}${nf.format(change)} (${pct>=0?'+':''}${nf.format(pct)}%)`:''}`); price.style.color=change>=0?'#087e63':'#d54454'; }
   text(status,bars.length ? `${t('Nến cuối','Last candle')}: ${data.provisionalAt||last.date}${data.provisionalAt?t(' (nến ngày tạm tính)',' (provisional daily candle)'):''} · ${bars.length} ${t('nến','candles')} · ${t('Tải lúc','Fetched')} ${new Date(data.fetchedAt).toLocaleTimeString(en?'en-GB':'vi-VN',{timeZone:'Asia/Ho_Chi_Minh'})}` : t('Chưa có dữ liệu trong khoảng thời gian này. Thử khung 1 ngày.','No data in this period. Try the daily timeframe.'));
   resetView(); if(same&&!wasLatest){pan.value=String(Math.min(oldPan,Number(pan.max)));draw();}
   timer=setTimeout(()=>load(true),Math.max(60,data.pollSeconds||300)*1000);
  } catch(error) { if(error.name==='AbortError'||id!==serial)return; text(status,(same&&bars.length?t('Đang giữ dữ liệu cũ. ','Showing previous data. '):'')+error.message); timer=setTimeout(()=>load(true),120000); }
  finally { if(id===serial){busy=false;root.removeAttribute('aria-busy');} }
 }
 function draw() {
  const start=Number(pan.value),end=start+Number(count.value);
  const series=(key)=> (studies[key]||[]).slice(start,end);
  const overlays=overlay.value==='ma'?[['ma20','#f59e0b'],['ma50','#38bdf8']]:overlay.value==='ema'?[['ema20','#f59e0b']]:overlay.value==='bb'?[['ma20','#f59e0b'],['upper','#a78bfa'],['lower','#a78bfa']]:[];
  legend.textContent=overlay.value==='ma'?'MA20 · 🟠 / MA50 · 🔵':overlay.value==='ema'?'EMA20':overlay.value==='bb'?'BB (20, 2σ)':'';
  sub.hidden=oscillator.value==='none'||!bars.length;

  const width=plot.clientWidth||700, height=plot.clientHeight||430, dpr=window.devicePixelRatio||1;
  canvas.width=Math.round(width*dpr);canvas.height=Math.round(height*dpr);canvas.style.width=width+'px';canvas.style.height=height+'px';ctx.setTransform(dpr,0,0,dpr,0,0);ctx.clearRect(0,0,width,height);
  const dark=document.documentElement.dataset.ssiTheme==='dark';ctx.fillStyle=dark?'#101923':'#fbfcfe';ctx.fillRect(0,0,width,height);ctx.font='11px system-ui';
  const left=10,right=width<500?65:82,top=28,bottom=28,ph=height*.66,vtop=height*.76,vh=height-vtop-bottom;
  visible=bars.slice(Number(pan.value),Number(pan.value)+Number(count.value));
  if(!visible.length){ctx.fillStyle=dark?'#94a3b8':'#64748b';ctx.textAlign='center';ctx.fillText(t('Biểu đồ sẽ hiển thị khi có dữ liệu','Chart will appear when data is available'),width/2,height/2);return;}
  const extra=overlays.flatMap(([key])=>series(key).filter(v=>v!==null)),low=Math.min(...visible.map(b=>b.low),...extra),high=Math.max(...visible.map(b=>b.high),...extra),pad=(high-low)*.06||high*.01||1,min=low-pad,max=high+pad;
  const pw=width-left-right,step=pw/visible.length,y=v=>top+(max-v)/(max-min)*(ph-top),vol=Math.max(...visible.map(b=>b.volume),1);
  geometry={left,step,pw,width,height};
  ctx.textAlign='left';
  for(let i=0;i<=4;i++){const value=min+(max-min)*i/4,yy=y(value);ctx.strokeStyle=dark?'#263445':'#e5eaf1';ctx.beginPath();ctx.moveTo(left,yy);ctx.lineTo(width-right,yy);ctx.stroke();ctx.fillStyle=dark?'#94a3b8':'#64748b';ctx.fillText(nf.format(value),width-right+7,yy+4);}
  ctx.fillStyle=dark?'#94a3b8':'#64748b';ctx.fillText(t('Khối lượng','Volume'),left,vtop-9);
  visible.forEach((bar,i)=>{const x=left+(i+.5)*step,color=bar.close>=bar.open?'#18c49a':'#ed5470';ctx.strokeStyle=color;ctx.fillStyle=color;ctx.beginPath();ctx.moveTo(x,y(bar.high));ctx.lineTo(x,y(bar.low));ctx.stroke();ctx.fillRect(x-Math.max(1,step*.65)/2,Math.min(y(bar.open),y(bar.close)),Math.max(1,step*.65),Math.max(1,Math.abs(y(bar.open)-y(bar.close))));ctx.globalAlpha=.55;const v=bar.volume/vol*vh;ctx.fillRect(x-step*.32,vtop+vh-v,Math.max(1,step*.64),v);ctx.globalAlpha=1;});

  function line(context, data, color, scale){context.strokeStyle=color;context.lineWidth=1.5;context.beginPath();let begun=false;data.forEach((v,i)=>{if(v===null||!Number.isFinite(v)){begun=false;return;}const x=left+(i+.5)*step;if(begun)context.lineTo(x,scale(v));else context.moveTo(x,scale(v));begun=true;});context.stroke();context.lineWidth=1;}
  overlays.forEach(([key,color])=>line(ctx,series(key),color,y));
  if(!sub.hidden){
   const h=150;sub.width=width*dpr;sub.height=h*dpr;sub.style.width='100%';sub.style.height=h+'px';
   const sc=sub.getContext('2d');sc.setTransform(dpr,0,0,dpr,0,0);sc.fillStyle=dark?'#101923':'#fbfcfe';sc.fillRect(0,0,width,h);sc.font='11px system-ui';sc.fillStyle=dark?'#94a3b8':'#64748b';
   const keys=oscillator.value==='rsi'?['rsi']:['macd','signal'],data=(oscillator.value==='rsi'?keys:[...keys,'histogram']).flatMap(k=>series(k).filter(v=>v!==null));
   const lo=oscillator.value==='rsi'?0:Math.min(0,...data),hi=oscillator.value==='rsi'?100:Math.max(0,...data),scale=v=>26+(hi-v)/(hi-lo||1)*102;
   sc.fillText(oscillator.value==='rsi'?'RSI (14)':'MACD (12,26) / Signal (9)',left,15);
   (oscillator.value==='rsi'?[30,70]:[0]).forEach(v=>{sc.strokeStyle=dark?'#334155':'#cbd5e1';sc.beginPath();sc.moveTo(left,scale(v));sc.lineTo(width-right,scale(v));sc.stroke();sc.fillText(String(v),width-right+5,scale(v)+4);});
   if(oscillator.value==='macd'){series('histogram').forEach((v,i)=>{if(v===null)return;sc.fillStyle=v>=0?'#18c49a':'#ed5470';const zero=scale(0),yy=scale(v);sc.fillRect(left+(i+.18)*step,Math.min(zero,yy),Math.max(1,step*.64),Math.max(1,Math.abs(zero-yy)));});}
   keys.forEach((key,i)=>line(sc,series(key),i?'#f59e0b':'#a78bfa',scale));
   if(!data.length)sc.fillText(t('Chưa đủ nến để tính chỉ báo','Not enough candles'),left,80);
  }
  const labels=Math.min(width<500?3:5,visible.length);ctx.fillStyle=dark?'#94a3b8':'#64748b';ctx.textAlign='center';
  for(let i=0;i<labels;i++){const ix=Math.round(i*(visible.length-1)/Math.max(1,labels-1));const label=visible[ix].date;ctx.fillText(label.slice(5,10)+(frame.value==='1d'?'':' '+label.slice(11)),Math.max(40,Math.min(width-right-30,left+(ix+.5)*step)),height-7);}
  if(hover>=0&&hover<visible.length){const x=left+(hover+.5)*step;ctx.strokeStyle='#7890a8';ctx.setLineDash([3,3]);ctx.beginPath();ctx.moveTo(x,top);ctx.lineTo(x,vtop+vh);ctx.stroke();ctx.setLineDash([]);}
 }
 function showAt(clientX){const rect=canvas.getBoundingClientRect();hover=Math.max(0,Math.min(visible.length-1,Math.floor((clientX-rect.left-geometry.left)/geometry.step)));const b=visible[hover];if(!b)return;tip.replaceChildren();const title=document.createElement('strong');title.textContent=`${selected?.symbol||symbol.value} · ${b.date}`;tip.append(title);for(const [key,label] of [['open',t('Mở','Open')],['high',t('Cao','High')],['low',t('Thấp','Low')],['close',t('Đóng','Close')],['volume',t('KL','Vol')]]){const line=document.createElement('span');line.textContent=`${label}: ${nf.format(b[key])}`;tip.append(line);}tip.hidden=false;tip.style.left=(clientX-rect.left>rect.width/2?12:Math.max(12,rect.width-185))+'px';draw();}
 canvas.addEventListener('pointermove',e=>showAt(e.clientX));canvas.addEventListener('pointerleave',()=>{hover=-1;tip.hidden=true;draw();});canvas.addEventListener('pointerdown',e=>showAt(e.clientX));
 plot.addEventListener('keydown',e=>{if(!['ArrowLeft','ArrowRight'].includes(e.key))return;e.preventDefault();pan.value=String(Math.max(0,Math.min(Number(pan.max),Number(pan.value)+(e.key==='ArrowLeft'?-5:5))));draw();});
 form.addEventListener('submit',e=>{e.preventDefault();load();});frame.addEventListener('change',()=>load());
 board.addEventListener('change',()=>{symbol.value=board.value==='HOSE'?'FPT':board.value==='HNX'?'SHS':'ACV';loadSymbols();load();});
 count.addEventListener('change',resetView);pan.addEventListener('input',()=>{hover=-1;tip.hidden=true;draw();});root.querySelector('.dttl-stock-refresh').addEventListener('click',()=>load());
 try{if(window.parent!==window)new MutationObserver(()=>{syncTheme();draw();}).observe(window.parent.document.documentElement,{attributes:true,attributeFilter:['data-theme']});}catch{}
 new ResizeObserver(draw).observe(plot);loadSymbols();load();
});

document.querySelectorAll('.dttl-fx').forEach(root=>{
 const button=root.querySelector('.dttl-fx-open'),pair=root.querySelector('select'),host=root.querySelector('.dttl-fx-chart');
 button.addEventListener('click',()=>{
  const en=root.dataset.lang==='en',symbol='FX:'+pair.value;
  host.replaceChildren();host.style.height='520px';
  const box=document.createElement('div');box.className='tradingview-widget-container';box.style.height='100%';
  const widget=document.createElement('div');widget.className='tradingview-widget-container__widget';widget.style.height='calc(100% - 32px)';box.append(widget);
  const credit=document.createElement('div');credit.className='tradingview-widget-copyright';const link=document.createElement('a');link.href='https://www.tradingview.com/symbols/'+pair.value+'/';link.target='_blank';link.rel='noopener nofollow';link.textContent=pair.value+' chart by TradingView';credit.append(link);box.append(credit);
  const script=document.createElement('script');script.src='https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js';script.async=true;
  script.textContent=JSON.stringify({autosize:true,symbol,interval:'D',timezone:'Asia/Ho_Chi_Minh',theme:document.documentElement.dataset.ssiTheme==='dark'?'dark':'light',style:'1',locale:en?'en':'vi_VN',allow_symbol_change:true,hide_top_toolbar:false,hide_side_toolbar:false,withdateranges:true,save_image:true,studies:[]});box.append(script);host.append(box);root.querySelector('.dttl-fx-link').href=link.href;
 });
});
})();

