DTTLTools.register('compound', (v,{t,fmt}) => {
  const rate=Math.pow(1+v.rate/100,1/12)-1,rows=[[0,v.initial,v.initial,0]];
  let balance=v.initial;
  for(let m=1;m<=v.years*12;m++) {balance=balance*(1+rate)+v.monthly;if(m%12===0){const paid=v.initial+v.monthly*m;rows.push([m/12,paid,balance,balance-paid]);}}
  const contributed=v.initial+v.monthly*v.years*12;
  return {stats:[[t('Vốn đã góp','Total contributions'),fmt(contributed)+' VND'],[t('Giá trị cuối kỳ','Ending value'),fmt(balance)+' VND'],[t('Lãi/lỗ theo giả định','Assumed gain/loss'),fmt(balance-contributed)+' VND']],rows};
});
