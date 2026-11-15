DTTLTools.register('average', (v,{t,fmt}) => {
  let qty=0,purchases=0;const lots=[];
  for(let i=1;i<=20;i++) {const q=v['q'+i]||0,p=v['p'+i]||0;if(q>0&&p<=0)throw Error(t('Lần mua có số lượng phải có giá lớn hơn 0.','A purchase with positive quantity needs a positive price.'));qty+=q;purchases+=p*q;if(q>0)lots.push([i,p,q,p*q]);}
  if(qty<=0)throw Error(t('Cần ít nhất một lần mua có số lượng lớn hơn 0.','At least one purchase must have positive quantity.'));
  const total=purchases+v.cost,stats=[[t('Tổng số lượng','Total quantity'),fmt(qty)],[t('Tổng vốn gồm phí','Total cost including fees'),fmt(total)],[t('Giá vốn / đơn vị','Average cost / unit'),fmt(total/qty)]];
  if(v.market>0)stats.push([t('Giá trị tham chiếu','Reference value'),fmt(v.market*qty)],[t('Lãi/lỗ chưa trừ phí bán','Profit/loss before selling fees'),fmt(v.market*qty-total)+' ('+fmt((v.market*qty/total-1)*100)+'%)']);
  return {stats,tables:[{title:t('Chi tiết từng lần mua','Purchase lot breakdown'),headers:[t('Lần','Lot'),t('Giá','Price'),t('Số lượng','Quantity'),t('Giá trị mua','Purchase value'),t('Tỷ trọng trước phí (%)','Weight before fees (%)')],rows:lots.map(r=>[...r,r[3]/purchases*100])}],notes:[t('Tổng phí được cộng một lần vào tổng vốn. Tỷ trọng từng lần mua tính trên giá trị mua trước phí.','Total fees are added once. Lot weights use purchase values before fees.')]};
});
if(typeof document!=='undefined'){
  const form=document.querySelector('[data-calculator="average"]');
  if(form){const grid=form.querySelector('.th-fields'),add=document.createElement('button');add.type='button';add.className='th-secondary';add.textContent=DTTLTools.t('Thêm lần mua','Add purchase');grid.after(add);let next=4;
    add.addEventListener('click',()=>{const i=Array.from({length:17},(_,j)=>j+4).find(n=>!form.elements.namedItem('p'+n));if(!i)return;const row=document.createElement('div');row.className='th-lot';row.dataset.extraLot='1';
      for(const [prefix,vi,en] of [['p','Giá mua lần ','Purchase price, lot '],['q','Số lượng lần ','Quantity, lot ']]){const label=document.createElement('label'),input=document.createElement('input');label.textContent=DTTLTools.t(vi,en)+i;input.type='number';input.name=prefix+i;input.min='0';input.max='1000000000000';input.step='any';input.value='0';input.required=true;label.append(input);row.append(label);}
      const remove=document.createElement('button');remove.type='button';remove.className='th-secondary';remove.textContent=DTTLTools.t('Bỏ lần mua ','Remove lot ')+i;remove.addEventListener('click',()=>{row.remove();add.disabled=false;form.dispatchEvent(new Event('input',{bubbles:true}));});row.append(remove);grid.append(row);add.disabled=form.querySelectorAll('[data-extra-lot]').length>=17;row.querySelector('input').focus();form.dispatchEvent(new Event('input',{bubbles:true}));});
    form.addEventListener('reset',()=>{form.querySelectorAll('[data-extra-lot]').forEach(x=>x.remove());next=4;add.disabled=false;});
  }
}
