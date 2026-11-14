DTTLTools.register('profit', (v,{t,fmt}) => {
  if(v.buy<=0) throw Error(t('Giá mua phải lớn hơn 0.','Buy price must be greater than zero.'));
  if(v.sfee+v.tax>=100) throw Error(t('Tổng phí bán và thuế phải nhỏ hơn 100%.','Combined sell fees and taxes must be below 100%.'));
  const cost=v.buy*v.qty*(1+v.bfee/100),net=v.sell*v.qty*(1-(v.sfee+v.tax)/100),gain=net-cost;
  return {stats:[[t('Vốn mua','Purchase cost'),fmt(cost)],[t('Tiền bán ròng','Net proceeds'),fmt(net)],[t('Lãi/lỗ','Profit/loss'),fmt(gain)+' ('+fmt(gain/cost*100)+'%)'],[t('Giá bán hòa vốn','Break-even sell price'),fmt(cost/v.qty/(1-(v.sfee+v.tax)/100))]]};
});
