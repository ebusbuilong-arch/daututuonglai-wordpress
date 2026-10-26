(() => {
  'use strict';
  if (!window.DTTL_AI || document.getElementById('dttl-ai')) return;
  const cfg = window.DTTL_AI;
  const copy = {
    vi: {ask:'Hỏi AI',title:'Trợ lý Đầu Tư Tương Lai',sub:'Cùng tìm hiểu về đầu tư',close:'Đóng',clear:'Cuộc trò chuyện mới',hello:'Chào anh/chị! Em có thể giúp tìm hiểu nội dung và cách sử dụng website. Anh/chị muốn hỏi gì?',suggest:['Người mới nên bắt đầu học từ đâu?','Đòn bẩy là gì?','Lấy AIM để mô phỏng thế nào?'],placeholder:'Nhập câu hỏi về nội dung website…',send:'Gửi',wait:'Đang tìm nội dung…',error:'Chưa kết nối được. Anh/chị thử lại sau nhé.',rate:'Anh/chị đã hỏi khá nhiều. Vui lòng thử lại sau khoảng 10 phút.',sources:'Bài tham khảo',privacy:'Khi gửi, câu hỏi và trích đoạn bài liên quan được chuyển tới nhà cung cấp AI. Đừng gửi mật khẩu, khóa ví hoặc thông tin nhạy cảm. AI có thể sai; hãy kiểm tra nguồn.',label:'Câu hỏi'},
    en: {ask:'Ask AI',title:'Future Investment Assistant',sub:'Explore investing together',close:'Close',clear:'New conversation',hello:'Hello! I can help you explore this website and learn how to use it. What would you like to know?',suggest:['Where should a beginner start learning?','What is leverage?','How do I get AIM for the simulation?'],placeholder:'Ask about this website…',send:'Send',wait:'Finding relevant content…',error:'Unable to connect. Please try again later.',rate:'Too many questions. Please try again in about 10 minutes.',sources:'Related articles',privacy:'Sending shares your question and relevant article excerpts with the AI provider. Do not share passwords, wallet keys or sensitive information. AI can be wrong; check the sources.',label:'Question'}
  };
  function language() {
    const url = new URL(location.href);
    const hint = url.searchParams.get('lang');
    if (hint === 'en' || hint === 'vi') return hint;
    return /^en(?:-|$)/i.test(document.documentElement.lang) || /^\/en(?:\/|$)/.test(url.pathname) ? 'en' : 'vi';
  }
  let lang = language(), t = copy[lang], history = [], busy = false, nonce = '', controller;
  const root=document.createElement('div');root.id='dttl-ai';root.style.setProperty('--dttl-ai-bottom',`${Number(cfg.bottom)||88}px`);
  const launcher=document.createElement('button');launcher.type='button';launcher.className='dttl-ai-launcher';launcher.setAttribute('aria-controls','dttl-ai-panel');launcher.setAttribute('aria-expanded','false');
  // Static trusted icon only; all conversation content is inserted with textContent.
  launcher.innerHTML='<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="12" cy="2.5" r="1.5" fill="currentColor"/><rect x="4" y="6" width="16" height="14" rx="5" stroke="currentColor" stroke-width="1.7"/><path d="M1.5 11v4M22.5 11v4M9 16h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="8.5" cy="11.5" r="1.5" fill="currentColor"/><circle cx="15.5" cy="11.5" r="1.5" fill="currentColor"/></svg>';
  const launchText=document.createElement('span');launchText.className='dttl-ai-label-desktop';const mobileText=document.createElement('span');mobileText.className='dttl-ai-label-mobile';mobileText.textContent='AI';launcher.append(launchText,mobileText);
  const panel=document.createElement('section');panel.id='dttl-ai-panel';panel.hidden=true;panel.setAttribute('role','dialog');panel.setAttribute('aria-labelledby','dttl-ai-title');
  const head=document.createElement('header');const titles=document.createElement('div');const title=document.createElement('strong');title.id='dttl-ai-title';const subtitle=document.createElement('small');titles.append(title,subtitle);
  const reset=document.createElement('button');reset.type='button';reset.textContent='↻';const close=document.createElement('button');close.type='button';close.textContent='×';head.append(titles,reset,close);
  const messages=document.createElement('div');messages.className='dttl-ai-messages';messages.setAttribute('role','log');messages.setAttribute('aria-live','polite');messages.setAttribute('aria-relevant','additions');
  const suggestions=document.createElement('div');suggestions.className='dttl-ai-suggestions';const privacy=document.createElement('p');privacy.className='dttl-ai-privacy';
  const form=document.createElement('form');const input=document.createElement('textarea');input.rows=2;input.maxLength=800;const send=document.createElement('button');send.type='submit';form.append(input,send);panel.append(head,messages,suggestions,privacy,form);root.append(launcher,panel);document.body.append(root);
  function add(text,role,links=[]) {
    const item=document.createElement('div');item.className=`dttl-ai-message dttl-ai-${role}`;const p=document.createElement('p');p.textContent=text;item.append(p);
    if(links.length){const label=document.createElement('strong');label.textContent=t.sources;item.append(label);const list=document.createElement('ol');for(const s of links){try{const u=new URL(s.url,location.origin);if(u.origin!==location.origin||!/^https?:$/.test(u.protocol))continue;const li=document.createElement('li'),a=document.createElement('a');a.href=u.href;a.textContent=s.title;li.append(a);list.append(li);}catch{ /* Ignore malformed sources. */ }}item.append(list);}
    messages.append(item);messages.scrollTop=messages.scrollHeight;return item;
  }
  function renderLabels(){t=copy[lang];launchText.textContent=t.ask;launcher.setAttribute('aria-label',t.ask);title.textContent=t.title;subtitle.textContent=t.sub;close.setAttribute('aria-label',t.close);reset.setAttribute('aria-label',t.clear);input.placeholder=t.placeholder;input.setAttribute('aria-label',t.label);send.textContent=t.send;privacy.textContent=t.privacy;suggestions.replaceChildren();t.suggest.forEach(q=>{const b=document.createElement('button');b.type='button';b.textContent=q;b.addEventListener('click',()=>{if(!busy){input.value=q;form.requestSubmit();}});suggestions.append(b);});}
  function clear(){const previous=controller;controller=null;previous?.abort();busy=false;history=[];messages.replaceChildren();send.disabled=false;input.disabled=false;suggestions.hidden=false;renderLabels();add(t.hello,'assistant');}
  function hide(){panel.hidden=true;launcher.setAttribute('aria-expanded','false');launcher.focus();}
  launcher.addEventListener('click',()=>{if(!panel.hidden){hide();return;}const next=language();if(next!==lang){lang=next;clear();}panel.hidden=false;launcher.setAttribute('aria-expanded','true');input.focus();});close.addEventListener('click',hide);reset.addEventListener('click',()=>{clear();input.focus();});
  panel.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();hide();}});
  input.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing){e.preventDefault();form.requestSubmit();}});
  async function request(data,signal){const r=await fetch(cfg.url,{method:'POST',credentials:'same-origin',body:new URLSearchParams(data),signal,cache:'no-store'});if(r.status===403)throw new Error('nonce');if(r.status===429)throw new Error('rate');if(!r.ok)throw new Error('network');const j=await r.json();if(!j.success)throw new Error('network');return j.data;}
  form.addEventListener('submit',async e=>{
    e.preventDefault();const q=input.value.trim();if(!q||busy)return;busy=true;input.value='';input.disabled=true;send.disabled=true;suggestions.hidden=true;add(q,'user');const waiting=add(t.wait,'assistant');controller=new AbortController();const localController=controller;const timeout=setTimeout(()=>localController.abort(),35000);
    try{
      const payload={action:'dttl_ai_chat',message:q,lang,history:JSON.stringify(history.slice(-6))};
      if(!nonce)nonce=(await request({action:'dttl_ai_token'},localController.signal)).nonce;
      let result;
      try{result=await request({...payload,nonce},localController.signal);}catch(err){if(err.message!=='nonce')throw err;nonce=(await request({action:'dttl_ai_token'},localController.signal)).nonce;result=await request({...payload,nonce},localController.signal);}
      if(controller!==localController)return;waiting.remove();add(result.reply,'assistant',result.sources||[]);history.push({role:'user',content:q},{role:'assistant',content:String(result.reply).slice(0,1000)});history=history.slice(-6);
    }catch(err){if(controller!==localController)return;waiting.remove();add(err.message==='rate'?t.rate:t.error,'assistant');input.value=q;}
    finally{clearTimeout(timeout);if(controller===localController){busy=false;input.disabled=false;send.disabled=false;if(!panel.hidden)input.focus();}}
  });
  // Follow a language switch that changes <html lang> without navigation.
  new MutationObserver(()=>{const next=language();if(next!==lang){lang=next;clear();}}).observe(document.documentElement,{attributes:true,attributeFilter:['lang']});
  clear();
})();
