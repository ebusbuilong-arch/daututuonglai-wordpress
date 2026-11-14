(function(){
  const root=document.querySelector('[data-tool-catalog]');if(!root)return;
  const form=root.querySelector('form'),cards=[...root.querySelectorAll('.th-card')],params=new URLSearchParams(location.search);
  const normalize=s=>s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d');
  form.elements.tim.value=params.get('tim')||'';
  const group=params.get('nhom')||'';if([...form.elements.nhom.options].some(o=>o.value===group))form.elements.nhom.value=group;
  function filter(){const q=normalize(form.elements.tim.value.trim()),g=form.elements.nhom.value;let count=0;
    for(const card of cards){card.hidden=!!((g&&card.dataset.group!==g)||(q&&!normalize(card.textContent).includes(q)));if(!card.hidden)count++;}
    root.querySelector('.th-empty').hidden=count>0;
    root.querySelector('.th-count').textContent= count+' / '+cards.length;
  }
  form.addEventListener('submit',e=>{e.preventDefault();filter();const url=new URL(location.href);for(const name of ['tim','nhom']){const v=form.elements[name].value.trim();if(v)url.searchParams.set(name,v);else url.searchParams.delete(name);}url.searchParams.delete('trang');history.replaceState(null,'',url);});
  form.elements.tim.addEventListener('input',filter);form.elements.nhom.addEventListener('change',filter);filter();
})();
