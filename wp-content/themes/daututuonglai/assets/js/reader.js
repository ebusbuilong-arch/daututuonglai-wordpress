(function(){'use strict';
const box=document.querySelector('.article-reader');if(!box)return;
const toggle=document.getElementById('reader-toggle'),status=document.getElementById('reader-status');
const english=/^en(?:[-_]|$)/i.test(document.documentElement.lang)||/^\/en(?:\/|$)/i.test(location.pathname);
const speechLang=english?'en-US':'vi-VN';
const labels=english?{unsupported:'This browser does not support reading aloud. Try Chrome or Edge with an English voice.',pause:'Pause reading',resume:'Resume reading',read:'Read article',done:'Finished reading the article.',playing:'Reading the article.',failed:'Unable to play the voice. Check your connection or try a browser with an English voice.',timeout:'The voice did not respond. Try again with an English voice enabled.',paused:'Reading paused.',missing:'No English voice is available. Enable an English voice in your device settings or try Chrome or Edge.',empty:'No article content found to read.',opening:'Starting the voice…'}:{unsupported:'Trình duyệt này chưa hỗ trợ đọc bài. Anh thử Chrome hoặc Edge có giọng tiếng Việt.',pause:'Tạm dừng đọc',resume:'Tiếp tục đọc',read:'Đọc bài viết',done:'Đã đọc xong bài viết.',playing:'Đang đọc bài viết.',failed:'Chưa phát được giọng đọc. Anh kiểm tra kết nối hoặc thử trình duyệt có giọng tiếng Việt.',timeout:'Giọng đọc chưa phản hồi. Anh thử lại trên trình duyệt có giọng tiếng Việt.',paused:'Đã tạm dừng.',missing:'Thiết bị chưa có giọng tiếng Việt. Anh thử Chrome/Edge hoặc thêm giọng tiếng Việt trong cài đặt thiết bị.',empty:'Không tìm thấy nội dung để đọc.',opening:'Đang mở giọng đọc…'};
function message(text,error=false){status.textContent=text;status.className=error?'reader-error':'reader-sr';}
if(!('speechSynthesis' in window)||!('SpeechSynthesisUtterance' in window)){toggle.addEventListener('click',()=>message(labels.unsupported,true));return;}
const synth=window.speechSynthesis;let voices=[],chunks=[],index=0,mode='idle',token=0,current=null,watchdog=null;
function loadVoices(){voices=synth.getVoices().filter(v=>(english?/^en(?:[-_]|$)/i:/^vi(?:[-_]|$)/i).test(v.lang));}
function getChunks(){const source=document.querySelector('.entry-content');if(!source)return [];const clone=source.cloneNode(true);clone.querySelectorAll('[data-read-post]').forEach(e=>e.closest('section')?.remove());clone.querySelectorAll('nav,#ez-toc-container,.ez-toc-container,[data-learning-progress],form,script,style,button,label,input,select,textarea,[hidden]').forEach(e=>e.remove());
 const blocks=[document.querySelector('article.post h1')?.textContent||''];
 clone.querySelectorAll('h2,h3,h4,p,li,tr').forEach(e=>{if(e.parentElement?.closest('p,li,tr'))return;blocks.push(e.textContent);});
 const result=[];for(const block of blocks){const words=block.replace(/\s+/g,' ').trim().split(' ');let part='';for(const word of words){if(!word)continue;if(part.length+word.length>180&&part){result.push(part);part='';}part+=(part?' ':'')+word;}if(part)result.push(part);}return result;
}
function controls(){const playing=mode==='playing',label=playing?labels.pause:mode==='paused'?labels.resume:labels.read;toggle.setAttribute('aria-label',label);toggle.title=label;toggle.setAttribute('aria-pressed',String(playing));toggle.classList.toggle('is-playing',playing);}
function cancel(){token++;clearTimeout(watchdog);synth.cancel();current=null;}
function fail(text){cancel();mode='idle';controls();message(text,true);}
function speak(){if(index>=chunks.length){mode='idle';controls();message(labels.done);return;}
 const t=++token;current=new SpeechSynthesisUtterance(chunks[index]);current.voice=voices.find(v=>v.lang.replace('_','-').toLowerCase()===speechLang.toLowerCase()&&v.localService)||voices.find(v=>v.lang.replace('_','-').toLowerCase()===speechLang.toLowerCase())||voices.find(v=>v.localService)||voices[0];current.lang=current.voice?.lang||speechLang;current.rate=1;controls();
 current.onstart=()=>{if(t!==token)return;clearTimeout(watchdog);message(labels.playing);};
 current.onend=()=>{if(t!==token||mode!=='playing')return;clearTimeout(watchdog);index++;speak();};
 current.onerror=()=>{if(t!==token)return;fail(labels.failed);};
 watchdog=setTimeout(()=>{if(t===token)fail(labels.timeout);},12000);
 synth.speak(current);
}
toggle.addEventListener('click',()=>{
 if(mode==='playing'){cancel();mode='paused';controls();message(labels.paused);return;}
 loadVoices();if(!voices.length){message(labels.missing,true);return;}
 if(mode==='idle'){cancel();chunks=getChunks();index=0;if(!chunks.length){message(labels.empty,true);return;}}
 mode='playing';message(labels.opening);speak();
});
synth.addEventListener('voiceschanged',loadVoices);window.addEventListener('pagehide',()=>{cancel();mode='idle';controls();});loadVoices();controls();
})();
