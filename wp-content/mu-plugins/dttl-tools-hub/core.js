(function () {
  'use strict';
  const root = document.querySelector('[data-tool-page]');
  const en = root && root.dataset.lang === 'en';
  const t = (vi, eng) => en ? eng : vi;
  const fmt = n => new Intl.NumberFormat(en ? 'en-US' : 'vi-VN', {maximumFractionDigits: 2}).format(n);
  window.DTTLTools = {t, fmt, register(key, calculate) {
    const form = root && root.querySelector('[data-calculator="' + key + '"]');
    if (!form) return;
    const output = form.querySelector('.th-result');
    form.addEventListener('submit', e => {
      e.preventDefault(); output.replaceChildren(); output.removeAttribute('data-error');
      try {
        const values = {};
        for (const input of form.querySelectorAll('input[type=number]')) {
          const n = Number(input.value);
          if (input.value.trim() === '' || !Number.isFinite(n) || !input.checkValidity()) throw Error(t('Vui lòng nhập số hợp lệ trong giới hạn của từng ô.', 'Enter valid numbers within each field’s limits.'));
          values[input.name] = n;
        }
        const result = calculate(values, {t, fmt});
        const list = document.createElement('dl'); list.className = 'th-stats';
        for (const [label, value] of result.stats) {
          const box = document.createElement('div'), dt = document.createElement('dt'), dd = document.createElement('dd');
          dt.textContent = label; dd.textContent = value; box.append(dt, dd); list.append(box);
        }
        output.append(list);
        if (result.rows) {
          const ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg');
          svg.setAttribute('viewBox','0 0 600 240');svg.setAttribute('role','img');svg.setAttribute('aria-label',t('Xanh: giá trị cuối kỳ. Vàng: vốn đã góp. Số liệu trong bảng bên dưới.','Green: ending value. Gold: contributions. Exact values are in the table below.'));svg.classList.add('th-chart');
          const max=Math.max(1,...result.rows.map(r=>Math.max(r[1],r[2]))),years=result.rows.length-1;
          for(const [column,color] of [[1,'#b88517'],[2,'#087f70']]){const line=document.createElementNS(ns,'polyline');line.setAttribute('points',result.rows.map(r=>(25+r[0]/years*550)+','+(210-r[column]/max*180)).join(' '));line.setAttribute('fill','none');line.setAttribute('stroke',color);line.setAttribute('stroke-width','3');svg.append(line);}
          output.append(svg);const legend=document.createElement('p');legend.textContent=t('Xanh: giá trị cuối kỳ · Vàng: vốn đã góp. Trục dọc từ 0 đến ','Green: ending value · Gold: contributions. Vertical scale: 0 to ')+fmt(max)+' VND.';output.append(legend);
          const wrap = document.createElement('div'); wrap.className = 'th-table';wrap.tabIndex=0;

          const table = document.createElement('table');
          const caption = table.createCaption(); caption.textContent = t('Tăng trưởng theo từng năm (VNĐ)', 'Annual growth (VND)');
          const head = table.createTHead().insertRow();
          for (const title of [t('Năm','Year'),t('Vốn đã góp','Contributions'),t('Giá trị cuối kỳ','Ending value'),t('Lãi/lỗ','Gain/loss')]) { const th=document.createElement('th');th.scope='col';th.textContent=title;head.append(th); }
          const body=table.createTBody();
          for (const row of result.rows) { const tr=body.insertRow(); for(const n of row) tr.insertCell().textContent=fmt(n); }
          wrap.append(table); output.append(wrap);
        }
      } catch (err) { output.dataset.error='1'; output.textContent=err.message; }
    });
    form.addEventListener('input', () => { output.removeAttribute('data-error'); output.textContent=t('Dữ liệu đã thay đổi. Bấm tính để cập nhật kết quả.', 'Inputs changed. Calculate again to update the result.'); });
  }};
})();
