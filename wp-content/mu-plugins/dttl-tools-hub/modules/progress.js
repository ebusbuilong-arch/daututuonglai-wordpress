// Translate the remaining visible labels without reading or changing account data.
(function(){
  const root=document.querySelector('[data-tool-page="progress"]');if(!root||root.dataset.lang!=='en')return;
  const translate=()=>{const walker=document.createTreeWalker(root,NodeFilter.SHOW_TEXT);let n;
    while((n=walker.nextNode())){
      const s=n.nodeValue;let next=s.replace('Thao tác & chi phí','Trading mechanics & costs');
      next=next.replace(/Bạn đã đánh dấu (\d+) bài học xong trong tài khoản\./g,'You have marked $1 lessons complete in your account.').replace(/Bạn đã đánh dấu (\d+) bài học xong trên thiết bị này\./g,'You have marked $1 lessons complete on this device.');
      if(next!==s)n.nodeValue=next;
    }
  };
  const observer=new MutationObserver(()=>{observer.disconnect();translate();observer.observe(root,{subtree:true,childList:true,characterData:true});});
  translate();observer.observe(root,{subtree:true,childList:true,characterData:true});
})();
// Read the existing widget only; account synchronization remains owned by the theme.
(function(){
 const root=document.querySelector('[data-tool-page="progress"]'),widget=root?.querySelector('[data-learning-progress]');if(!widget)return;
 const panel=document.createElement('aside');panel.className='th-result';panel.setAttribute('aria-live','polite');widget.before(panel);
 const update=()=>{const boxes=[...widget.querySelectorAll('[data-day]')],done=boxes.filter(x=>x.checked).length,next=boxes.find(x=>!x.checked),t=DTTLTools.t;
  const text=t('Tiến độ: ','Progress: ')+done+'/'+boxes.length+' ('+Math.round(done/Math.max(1,boxes.length)*100)+'%). '+(next?t('Chặng tiếp theo: ','Next stage: ')+next.parentElement.textContent.trim():t('Đã hoàn thành 7 chặng. Có thể ôn tập và làm lại bài kiểm tra.','All seven stages completed. You can review and retake the quiz.'));
  if(panel.textContent!==text)panel.textContent=text;
 };
 new MutationObserver(update).observe(widget,{subtree:true,childList:true,characterData:true,attributes:true,attributeFilter:['value']});widget.addEventListener('change',update);update();
})();
