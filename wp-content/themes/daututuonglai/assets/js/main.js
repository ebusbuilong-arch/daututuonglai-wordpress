document.addEventListener('DOMContentLoaded', function () {
  var toggle=document.querySelector('.menu-toggle');
  var nav=document.querySelector('.main-nav');
  if (toggle && nav) toggle.addEventListener('click', function () {
    var open=toggle.getAttribute('aria-expanded')==='true';
    toggle.setAttribute('aria-expanded', String(!open));
    nav.classList.toggle('is-open', !open);
  });
  function closeMenu() {
    if(!toggle || !nav) return;
    toggle.setAttribute('aria-expanded','false');
    nav.classList.remove('is-open');
  }
  if(nav) nav.addEventListener('click',function(event){if(event.target.closest('a')) closeMenu();});
  document.addEventListener('keydown',function(event){
    if(event.key==='Escape' && toggle && toggle.getAttribute('aria-expanded')==='true') {closeMenu();toggle.focus();}
  });
  document.addEventListener('click',function(event){if(!event.target.closest('.site-header')) closeMenu();});
  var topButton=document.querySelector('.back-to-top');
  var adminBar=document.getElementById('wpadminbar');
  var scrollQueued=false;
  function updateReadingPosition(){
    scrollQueued=false;
    var offset=window.scrollY || document.documentElement.scrollTop;
    var distance=document.documentElement.scrollHeight-window.innerHeight;
    if(topButton){
      topButton.hidden=offset<300;
      topButton.style.setProperty('--read-progress',Math.min(100,Math.max(0,distance>0?offset/distance*100:0))+'%');
    }
    if(adminBar && window.innerWidth<=600){
      document.body.style.setProperty('--header-admin-offset',Math.max(0,adminBar.getBoundingClientRect().bottom)+'px');
    }else document.body.style.removeProperty('--header-admin-offset');
  }
  function queueReadingPosition(){if(!scrollQueued){scrollQueued=true;window.requestAnimationFrame(updateReadingPosition);}}
  window.addEventListener('scroll',queueReadingPosition,{passive:true});
  window.addEventListener('resize',function(){queueReadingPosition();if(window.innerWidth>1000) closeMenu();});
  window.addEventListener('load',queueReadingPosition);
  if(topButton) topButton.addEventListener('click',function(){
    closeMenu();
    var header=document.getElementById('page-top');
    if(header) header.focus({preventScroll:true});
    window.scrollTo({top:0,behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
  });
  updateReadingPosition();
  var search=document.getElementById('app-guide-search');
  if(search) {
    var fold=function(text){return text.normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d').replace(/Đ/g,'D').toLowerCase();};
    search.addEventListener('input',function(){
      var query=fold(search.value.trim()),visible=0;
      document.querySelectorAll('.app-guide-row').forEach(function(row){
        row.hidden=fold(row.textContent).indexOf(query)<0;
        if(!row.hidden) visible++;
      });
      document.querySelectorAll('.app-guide-group').forEach(function(group){
        group.hidden=Array.from(group.querySelectorAll('.app-guide-row')).every(function(row){return row.hidden;});
      });
      document.getElementById('app-guide-empty').hidden=visible>0;
    });
  }
  var glossarySearch=document.getElementById('glossary-search');
  var glossaryCategory=document.getElementById('glossary-category');
  if(glossarySearch && glossaryCategory) {
    var normalizeTerm=function(text){return text.normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d').replace(/Đ/g,'D').toLowerCase();};
    var filterTerms=function(){
      var query=normalizeTerm(glossarySearch.value.trim()),category=glossaryCategory.value,count=0;
      var terms=document.querySelectorAll('.glossary-term');
      terms.forEach(function(term){
        term.hidden=(category && term.getAttribute('data-group')!==category) || normalizeTerm(term.textContent).indexOf(query)<0;
        if(!term.hidden) count++;
      });
      document.querySelectorAll('.glossary-group').forEach(function(group){group.hidden=Array.from(group.querySelectorAll('.glossary-term')).every(function(term){return term.hidden;});});
      document.getElementById('glossary-count').textContent='Đang hiển thị '+count+'/'+terms.length+' thuật ngữ.';
      document.getElementById('glossary-empty').hidden=count>0;
    };
    glossarySearch.addEventListener('input',filterTerms);
    glossaryCategory.addEventListener('change',filterTerms);
    document.getElementById('glossary-reset').addEventListener('click',function(){glossarySearch.value='';glossaryCategory.value='';filterTerms();glossarySearch.focus();});
  }
});

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-stock-quiz]').forEach(function(form) {
    var fields=Array.from(form.querySelectorAll('.quiz-question'));
    var result=form.querySelector('.quiz-result');
    var progress=form.querySelector('.quiz-progress');
    var graded=false;
    function clearGrade() {
      fields.forEach(function(field) {
        field.classList.remove('quiz-correct','quiz-incorrect');
        field.querySelector('.quiz-explanation').hidden=true;
      });
      result.textContent='';
      graded=false;
    }
    form.addEventListener('change',function() {
      if(graded) clearGrade();
      result.textContent='';
      progress.textContent='Đã trả lời '+fields.filter(function(f){return !!f.querySelector('input:checked');}).length+'/'+fields.length+' câu';
    });
    form.addEventListener('submit',function(event) {
      event.preventDefault();
      var missing=fields.filter(function(f){return !f.querySelector('input:checked');});
      if(missing.length) {
        result.textContent='Còn '+missing.length+' câu chưa trả lời. Hãy chọn đáp án cho từng câu trước khi chấm điểm.';
        missing[0].querySelector('input').focus();
        return;
      }
      var score=0;
      fields.forEach(function(field) {
        var correct=field.querySelector('input:checked').value===field.dataset.answer;
        if(correct) score++;
        field.classList.toggle('quiz-correct',correct);
        field.classList.toggle('quiz-incorrect',!correct);
        var answer=field.querySelector('input[value="'+field.dataset.answer+'"]').nextElementSibling.textContent;
        field.querySelector('.quiz-verdict').textContent=(correct?'Đúng. ':'Chưa đúng. ')+'Đáp án: '+answer;
        field.querySelector('.quiz-explanation').hidden=false;
      });
      result.textContent='Kết quả: '+score+'/'+fields.length+' câu đúng. '+(score>=10?'Nền tảng khá chắc; hãy đọc giải thích để kiểm tra cách hiểu.':score>=6?'Hãy ôn các câu còn nhầm và tự tạo ví dụ tương tự.':'Nên đọc lại các bài nền tảng rồi thử lại.')+' Điểm số không xác nhận khả năng đầu tư có lãi.';
      graded=true;
      result.focus();
    });
    form.addEventListener('reset',function() {
      clearGrade();
      progress.textContent='Đã trả lời 0/'+fields.length+' câu';
      fields[0].querySelector('input').focus();
    });
  });
});
