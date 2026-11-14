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
