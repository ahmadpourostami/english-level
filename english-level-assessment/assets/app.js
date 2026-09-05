(function(){
 const root=document.getElementById('ela-test');if(!root||!window.ELA_CONFIG)return;
 const wrap=document.getElementById('ela-question-wrap'),next=document.getElementById('ela-next'),result=document.getElementById('ela-result'),bar=document.getElementById('ela-progress-bar');
 let token='',current=null,number=0,total=12,busy=false;
 function escapeHtml(s){return String(s).replace(/[&<>'\"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','\"':'&quot;'}[c]));}
 function request(action,extra){const body=new URLSearchParams(Object.assign({action:action,nonce:ELA_CONFIG.nonce},extra||{}));return fetch(ELA_CONFIG.ajax_url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(r=>r.json());}
 function renderQuestion(q,n,t){
   current=q;number=n;total=t||total;bar.style.width=Math.round(((number-1)/total)*100)+'%';
   let media='';
   if(q.skill==='listening'&&q.audio_url){media='<div class="ela-audio"><p><strong>Listening</strong></p><audio controls preload="metadata" src="'+escapeHtml(q.audio_url)+'"><span>Your browser does not support audio.</span></audio></div>';}
   if(q.skill==='reading'&&q.passage){media='<div class="ela-passage"><p><strong>Read the passage</strong></p><div>'+q.passage+'</div></div>';}
   wrap.innerHTML='<div class="ela-meta"><span>Question '+number+' of '+total+'</span><span>Level: '+escapeHtml(q.level)+' · '+escapeHtml(q.skill)+'</span></div>'+media+'<p class="ela-question">'+escapeHtml(q.question)+'</p><div class="ela-options">'+Object.entries(q.options).map(([k,v])=>'<label><input type="radio" name="ela-answer" value="'+k+'"> <span>'+k+'. '+escapeHtml(v)+'</span></label>').join('')+'</div>';
   next.textContent=number===total?'Finish':'Next';next.hidden=false;
 }
 function start(){busy=true;next.disabled=true;next.textContent='Loading...';request('ela_start_test').then(json=>{if(!json.success)throw new Error();token=json.data.token;renderQuestion(json.data.question,json.data.number,json.data.total);}).catch(()=>{wrap.innerHTML='<div class="ela-error">Could not start the assessment. Please try again.</div>';next.textContent='Start Test';}).finally(()=>{busy=false;next.disabled=false;});}
 next.addEventListener('click',function(){
   if(busy)return;if(!token){start();return;}
   const selected=document.querySelector('input[name="ela-answer"]:checked');if(!selected){alert('Please choose an answer.');return;}
   busy=true;next.disabled=true;next.textContent='Checking...';
   request('ela_submit_answer',{token:token,question_id:String(current.id),answer:selected.value}).then(json=>{
     if(!json.success)throw new Error(json.data&&json.data.message?json.data.message:'Answer failed');
     if(json.data.done){bar.style.width='100%';next.hidden=true;result.hidden=false;const r=json.data.result;wrap.innerHTML='';result.innerHTML='<div class="ela-result-card"><h2>Your estimated level: '+escapeHtml(r.level)+'</h2><div class="ela-level-badge">'+escapeHtml(r.level)+'</div><p>Score: <strong>'+Number(r.score)+'%</strong></p><div class="ela-skills">'+Object.entries(r.skills).map(([s,l])=>'<div><strong>'+escapeHtml(s)+'</strong>: '+escapeHtml(l)+'</div>').join('')+'</div><p>Your result has been saved securely.</p></div>';if(json.data.url)window.location.href=json.data.url;return;}
     renderQuestion(json.data.question,json.data.number,json.data.total);
   }).catch(e=>{alert(e.message||'Could not evaluate the answer. Please try again.');}).finally(()=>{busy=false;next.disabled=false;});
 });
})();
