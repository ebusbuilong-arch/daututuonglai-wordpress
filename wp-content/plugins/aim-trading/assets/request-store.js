(function(root,factory){if(typeof module==='object'&&module.exports)module.exports=factory();else root.AIMRequestStore=factory();})(typeof globalThis!=='undefined'?globalThis:this,function(){
'use strict';
function create(storage,userId){
 const prefix='aimtr_pending_v1_'+String(userId)+'_';
 function valid(r){return r&&r.version===1&&typeof r.path==='string'&&/^(trade|orders|orders\/[1-9]\d*\/amend)$/.test(r.path)&&r.payload&&typeof r.payload.request_id==='string'&&/^[a-zA-Z0-9-]{16,64}$/.test(r.payload.request_id)&&typeof r.payload.symbol==='string'&&['buy','sell'].includes(r.payload.side)&&typeof r.payload.quantity==='string'&&Number.isFinite(r.at);}
 return {
  put(record){const r=JSON.parse(JSON.stringify(record));if(!valid(r))throw Error('Yêu cầu khôi phục không hợp lệ.');const key=prefix+r.payload.request_id,raw=JSON.stringify(r);storage.setItem(key,raw);if(storage.getItem(key)!==raw)throw Error('Chưa lưu được mã yêu cầu.');return r;},
  remove(id){storage.removeItem(prefix+id);},
  list(){const rows=[];for(let i=0;i<storage.length;i++){const key=storage.key(i);if(!key||!key.startsWith(prefix))continue;try{const r=JSON.parse(storage.getItem(key));if(valid(r)&&key===prefix+r.payload.request_id)rows.push(r);}catch{}}return rows.sort((a,b)=>a.at-b.at);}
 };
}
return {create};
});
