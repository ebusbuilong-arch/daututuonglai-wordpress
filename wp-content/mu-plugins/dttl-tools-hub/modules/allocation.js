DTTLTools.register('allocation', (v,{t,fmt}) => {
  const weights=[v.w1,v.w2,v.w3,v.w4],sum=weights.reduce((a,b)=>a+b,0);
  if(Math.abs(sum-100)>0.000001)throw Error(t('Tổng tỷ trọng phải bằng 100%. Hiện tại: ','Weights must total 100%. Current total: ')+fmt(sum)+'%.');
  return {stats:weights.map((w,i)=>[i===3?t('Tiền dự phòng','Cash reserve'):t('Nhóm tài sản ','Asset group ')+(i+1),fmt(v.capital*w/100)+' VND'])};
});
