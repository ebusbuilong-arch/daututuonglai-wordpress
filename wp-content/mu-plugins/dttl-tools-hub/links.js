(function(){
  const en=/^\/en(?:\/|$)/.test(location.pathname),base=(en?'/en':'')+'/cong-cu-mien-phi/';
  const anchors={'tinh-lai-lo':'tinh-lai-lo','gia-von-binh-quan':'gia-von-binh-quan','tang-de-hoa-von':'tang-de-hoa-von','phan-bo-von':'phan-bo-von','lai-kep':'lai-kep','nhat-ky':'nhat-ky-dau-tu','tien-do':'tien-do-hoc'};
  document.querySelectorAll('a[href]').forEach(a=>{
    const url=new URL(a.href,location.href);if(url.origin!==location.origin||!/^\/(en\/)?cong-cu-dau-tu\/?$/.test(url.pathname))return;
    const hash=url.hash.slice(1),progress=/^(Xem tiến độ học|View learning progress)/i.test(a.textContent.trim());
    a.href=base+(anchors[hash]?anchors[hash]+'/':progress?'tien-do-hoc/':'');
    if(/^(Công cụ & tiến độ|Tools & progress)$/i.test(a.textContent.trim()))a.textContent=en?'Free tools':'Công cụ miễn phí';
  });
  if(/^\/(en\/)?cong-cu-dau-tu\/?$/.test(location.pathname)){
    location.replace(base+(anchors[location.hash.slice(1)]?anchors[location.hash.slice(1)]+'/':''));
  }
})();
