DTTLTools.register('average', (v,{t,fmt}) => {
  let qty=0,total=v.cost;
  for(let i=1;i<=3;i++) {if(v['q'+i]>0&&v['p'+i]<=0)throw Error(t('Lần mua có số lượng phải có giá lớn hơn 0.','A purchase with positive quantity needs a positive price.'));qty+=v['q'+i];total+=v['p'+i]*v['q'+i];}
  if(qty<=0)throw Error(t('Cần ít nhất một lần mua có số lượng lớn hơn 0.','At least one purchase must have positive quantity.'));
  return {stats:[[t('Tổng số lượng','Total quantity'),fmt(qty)],[t('Tổng vốn gồm phí','Total cost including fees'),fmt(total)],[t('Giá vốn / đơn vị','Average cost / unit'),fmt(total/qty)]]};
});
