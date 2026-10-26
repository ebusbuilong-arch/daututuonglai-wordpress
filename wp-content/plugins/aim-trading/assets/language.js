/* Presentation-only English layer; never translate input values, payloads or storage. */
(()=>{'use strict';const dictionary=window.AIMTradingEnglish;if(!dictionary)return;
const keys=Object.keys(dictionary).sort((a,b)=>b.length-a.length),escape=s=>s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),pattern=new RegExp(keys.map(k=>(/^[\p{L}]/u.test(k)?'(?<![\\p{L}])':'')+escape(k)+(/[\p{L}]$/u.test(k)?'(?![\\p{L}])':'')).join('|'),'gu');
const translate=s=>s.replace(pattern,k=>dictionary[k]);
const skip='script,style,textarea,input,code,pre,.avatar,[data-no-translation]';
function text(n){if(n.parentElement&&!n.parentElement.closest(skip)){const next=translate(n.data);if(next!==n.data)n.data=next;}}
function element(el){if(el.matches('script,style,textarea,code,pre,.avatar,[data-no-translation]'))return;for(const a of ['placeholder','title','aria-label']){const v=el.getAttribute(a);if(v){const t=translate(v);if(t!==v)el.setAttribute(a,t);}}
 if(el.tagName==='A'){const href=el.getAttribute('href');if(href&&!href.startsWith('#')){try{const u=new URL(href,location.href);if(u.origin===location.origin&&!/^\/(?:en|wp-admin|wp-json|wp-content)(?:\/|$)/.test(u.pathname)){u.pathname='/en'+(u.pathname.startsWith('/')?'':'/')+u.pathname;el.setAttribute('href',u.pathname+u.search+u.hash);}}catch{}}}}
function scan(root){if(root.nodeType===3){text(root);return;}if(root.nodeType!==1)return;element(root);if(root.matches(skip))return;const walk=document.createTreeWalker(root,NodeFilter.SHOW_ELEMENT|NodeFilter.SHOW_TEXT);let n;while(n=walk.nextNode()){if(n.nodeType===3)text(n);else element(n);}}
function start(){document.documentElement.lang='en';scan(document.body);document.title=translate(document.title);const observer=new MutationObserver(records=>{observer.disconnect();for(const r of records){if(r.type==='characterData')text(r.target);else if(r.type==='attributes')element(r.target);else r.addedNodes.forEach(scan);}observe();});function observe(){observer.observe(document.body,{subtree:true,childList:true,characterData:true,attributes:true,attributeFilter:['placeholder','title','aria-label','href']});}observe();}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
