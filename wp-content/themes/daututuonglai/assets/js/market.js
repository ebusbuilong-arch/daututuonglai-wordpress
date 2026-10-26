(function () {
  'use strict';
  const picker = document.getElementById('market-asset');
  if (!picker) return;
  const interval = document.getElementById('market-interval');
  const assets = [
    {symbol:'BINANCE:BTCUSDT',name:'Bitcoin · BTC/USDT',group:'crypto',label:'TIỀN SỐ',badge:'TradingView · cập nhật liên tục',note:'Giá trên Binance tính bằng USDT cho 1 BTC. USDT là một đồng tiền số neo theo USD; không phải báo giá bằng VND.',link:'https://www.tradingview.com/symbols/BTCUSDT/?exchange=BINANCE'},
    {symbol:'BINANCE:ETHUSDT',name:'Ethereum · ETH/USDT',group:'crypto',label:'TIỀN SỐ',badge:'TradingView · cập nhật liên tục',note:'Giá trên Binance tính bằng USDT cho 1 ETH. Mỗi sàn có giá và thanh khoản riêng; không phải báo giá bằng VND.',link:'https://www.tradingview.com/symbols/ETHUSDT/?exchange=BINANCE'},
    {symbol:'HNX:HNXINDEX',name:'HNX-Index · chỉ số HNX',group:'stocks',label:'CHỨNG KHOÁN VIỆT NAM',badge:'TradingView · dữ liệu cuối ngày (EOD)',note:'HNX-Index tính bằng điểm, không phải giá một cổ phiếu. Nguồn nhúng chỉ cung cấp dữ liệu cuối ngày; xem ngày của cây nến cuối. Dữ liệu HOSE/VN-Index trực tiếp cần nguồn riêng được cấp quyền.',link:'https://www.tradingview.com/symbols/HNX-HNXINDEX/'},
    {symbol:'HNX:SHS',name:'SHS · Chứng khoán Sài Gòn – Hà Nội',group:'stocks',label:'CHỨNG KHOÁN VIỆT NAM',badge:'TradingView · dữ liệu cuối ngày (EOD)',note:'SHS là một mã cổ phiếu trên HNX, dùng để trải nghiệm biểu đồ. Giá theo đơn vị VND mà nguồn hiển thị; dữ liệu cuối ngày, không phải giá trong phiên. Đây không phải đề xuất mua mã SHS.',link:'https://www.tradingview.com/symbols/HNX-SHS/'},
    {symbol:'OANDA:XAUUSD',name:'Vàng quốc tế · XAU/USD',group:'gold',label:'VÀNG QUỐC TẾ',badge:'TradingView · nguồn OANDA',note:'XAU/USD là báo giá tham chiếu vàng quốc tế bằng USD cho một ounce troy. Không phải giá vàng miếng SJC hay báo giá mua/bán của cửa hàng Việt Nam. Khối lượng nếu có tùy dữ liệu nguồn.',link:'https://www.tradingview.com/symbols/XAUUSD/?exchange=OANDA'},
    {symbol:'FX:EURUSD',name:'Euro / Đô la Mỹ · EUR/USD',group:'forex',label:'TIỀN TỆ',badge:'TradingView · cập nhật theo nguồn FX',note:'EUR/USD biểu thị số USD cho 1 EUR. Đây là tỷ giá thị trường tham chiếu, không phải giá mua/bán ngoại tệ của ngân hàng Việt Nam.',link:'https://www.tradingview.com/symbols/EURUSD/?exchange=FX'}
  ];
  const defaultAsset = assets.find(a=>a.symbol==='HNX:HNXINDEX');
  let activeGroup = 'stocks';
  let selected = defaultAsset;
  let generation = 0;
  let saved = [];
  try {const v=JSON.parse(localStorage.getItem('dttl-followed-assets')||'[]');if(Array.isArray(v)) saved=v.filter(s=>assets.some(a=>a.symbol===s));} catch (_) {}
  const allowedIntervals = ['60','240','D','W','M'];
  function updateFollow() {
    const on=saved.includes(selected.symbol);const b=document.getElementById('save-asset');b.textContent=on?'★ Đã theo dõi':'☆ Theo dõi';b.setAttribute('aria-pressed',String(on));
  }
  function widget(id, scriptName, config, token) {
    const host=document.getElementById(id);if(!host || host.dataset.staticNews==='true') return;host.replaceChildren();
    const box=document.createElement('div');box.className='tradingview-widget-container';box.style.height='100%';box.style.width='100%';
    const inner=document.createElement('div');inner.className='tradingview-widget-container__widget';inner.style.height='calc(100% - 32px)';inner.style.width='100%';box.appendChild(inner);
    const credit=document.createElement('div');credit.className='tradingview-widget-copyright';const link=document.createElement('a');link.href=selected.link;link.target='_blank';link.rel='noopener';link.textContent='Dữ liệu và biểu đồ bởi TradingView';credit.appendChild(link);box.appendChild(credit);
    const loader=document.createElement('p');loader.className='widget-loading';loader.textContent='Đang kết nối nguồn dữ liệu…';inner.appendChild(loader);
    const script=document.createElement('script');script.src='https://s3.tradingview.com/external-embedding/embed-widget-'+scriptName+'.js';script.async=true;script.type='text/javascript';script.textContent=JSON.stringify(config);
    script.onerror=function(){if(token!==generation)return;inner.textContent='Chưa tải được dữ liệu. Hãy thử tải lại hoặc xem tại nguồn.';document.getElementById('market-load-help').hidden=false;};box.appendChild(script);host.appendChild(box);
  }
  function draw() {
    const token=++generation;document.getElementById('market-load-help').hidden=true;
    const v=allowedIntervals.includes(interval.value)?interval.value:'D';
    document.getElementById('asset-name').textContent=selected.name;document.getElementById('asset-class').textContent=selected.label;
    document.getElementById('asset-note').textContent=selected.note;document.getElementById('data-badge').textContent=selected.badge;document.getElementById('asset-source').href=selected.link;
    interval.querySelectorAll('option').forEach(o=>{o.disabled=selected.group==='stocks'&&(o.value==='60'||o.value==='240');});
    if(selected.group==='stocks'&&(v==='60'||v==='240'))interval.value='D';
    const frame=interval.value;const common={symbol:selected.symbol,locale:'vi_VN',colorTheme:'light',isTransparent:false};
    widget('price-widget','symbol-info',Object.assign({},common,{width:'100%'}),token);
    widget('chart-widget','advanced-chart',{autosize:true,symbol:selected.symbol,interval:frame,timezone:'Asia/Ho_Chi_Minh',theme:'light',style:'1',locale:'vi_VN',allow_symbol_change:false,hide_top_toolbar:false,hide_side_toolbar:false,hide_volume:false,withdateranges:true,save_image:false,calendar:false,support_host:'https://www.tradingview.com'},token);
    widget('technical-widget','technical-analysis',Object.assign({},common,{interval:frame==='60'?'1h':frame==='240'?'4h':frame==='W'?'1W':frame==='M'?'1M':'1D',width:'100%',height:420,showIntervalTabs:true,displayMode:'multiple'}),token);
    widget('news-widget','timeline',Object.assign({},common,{feedMode:'symbol',displayMode:'regular',width:'100%',height:420}),token);
    updateFollow();
    const url=new URL(location.href);url.searchParams.set('asset',selected.symbol);history.replaceState(null,'',url);
  }
  function chooseGroup(group,preferred) {
    activeGroup=group;
    document.getElementById('bluechip-panel').hidden=group!=='stocks';
    document.querySelectorAll('[data-market]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.market===group)));
    let list=group==='saved'?assets.filter(a=>saved.includes(a.symbol)):assets.filter(a=>a.group===group);
    picker.replaceChildren();
    if(!list.length){const o=document.createElement('option');o.textContent='Chưa lưu tài sản — chọn thị trường rồi bấm ☆ Theo dõi';o.value='';picker.appendChild(o);picker.disabled=true;return;}
    picker.disabled=false;list.forEach(a=>{const o=document.createElement('option');o.value=a.symbol;o.textContent=a.name;picker.appendChild(o);});
    selected=list.find(a=>a.symbol===preferred)||list[0];picker.value=selected.symbol;draw();
  }
  document.querySelectorAll('[data-market]').forEach(b=>b.addEventListener('click',()=>chooseGroup(b.dataset.market)));
  picker.addEventListener('change',()=>{selected=assets.find(a=>a.symbol===picker.value)||defaultAsset;draw();});
  interval.addEventListener('change',draw);document.getElementById('retry-market').addEventListener('click',draw);
  document.getElementById('save-asset').addEventListener('click',()=>{saved=saved.includes(selected.symbol)?saved.filter(s=>s!==selected.symbol):saved.concat(selected.symbol);try{localStorage.setItem('dttl-followed-assets',JSON.stringify(saved));}catch(_){}updateFollow();if(activeGroup==='saved')chooseGroup('saved',selected.symbol);});
  const bluechipRow=document.getElementById('bluechip-row');
  document.querySelectorAll('[data-bluechip-scroll]').forEach(button=>button.addEventListener('click',()=>{
    bluechipRow.scrollBy({left:Number(button.dataset.bluechipScroll)*Math.max(220,bluechipRow.clientWidth*0.75),behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
  }));
  const requested=new URLSearchParams(location.search).get('asset');const start=assets.find(a=>a.symbol===requested)||defaultAsset;chooseGroup(start.group,start.symbol);
})();
