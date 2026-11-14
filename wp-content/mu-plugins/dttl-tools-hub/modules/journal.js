(function(){
  const button=document.querySelector('[data-download-journal]');if(!button)return;
  button.addEventListener('click',()=>{
    const t=DTTLTools.t;
    const csv='\uFEFF'+t('Ngày,Tài sản,Chiều giao dịch,Số lượng,Giá,Phí và thuế,Lý do giao dịch,Rủi ro dự kiến,Điều kiện xem xét lại,Kết quả,Bài học','Date,Asset,Side,Quantity,Price,Fees and taxes,Reason,Expected risk,Review conditions,Outcome,Lessons')+'\r\n';
    const url=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8;'})),a=document.createElement('a');
    a.href=url;a.download=t('nhat-ky-dau-tu.csv','investment-journal.csv');document.body.append(a);a.click();a.remove();
    document.querySelector('[data-journal-status]').textContent=t('Đã tạo mẫu CSV để tải xuống.','CSV template created for download.');
    setTimeout(()=>URL.revokeObjectURL(url),1000);
  });
})();
