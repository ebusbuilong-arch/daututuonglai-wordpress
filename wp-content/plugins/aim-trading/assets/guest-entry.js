(()=>{'use strict';const c=window.AIMTradingConfig;if(!c||c.logged)return;
// Keep market navigation and chart interactions. No guest order/account submissions.
document.addEventListener('submit',e=>{if(e.target.matches('#order-form,#f-open,#f-transfer,#st-form')){e.preventDefault();e.stopImmediatePropagation();}},true);
for(const id of ['submit-order','start-account','cancel-many','f-submit','f-mode-switch','f-retry','f-dialog-confirm','ex-submit','export','refresh-account']){const b=document.getElementById(id);if(b)b.disabled=true;}
const login=document.getElementById('login');if(login){login.textContent='Đăng nhập để giao dịch';login.hidden=false;}
for(const id of ['ex-panel-positions','ex-panel-orders','ex-panel-history']){
 const panel=document.getElementById(id);if(panel){const p=document.createElement('p');p.textContent='Đăng nhập để xem tài sản, lệnh và lịch sử giao dịch của bạn.';panel.replaceChildren(p);}
}
const empty=document.getElementById('holdings-empty');if(empty)empty.textContent='Đăng nhập để xem tài sản và giao dịch của bạn.';
})();
