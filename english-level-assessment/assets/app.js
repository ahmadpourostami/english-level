(function(){
 const root=document.getElementById('ela-test');if(!root||!window.ELA_QUESTIONS)return;
 const pool=window.ELA_QUESTIONS,wrap=document.getElementById('ela-question-wrap'),next=document.getElementById('ela-next'),result=document.getElementById('ela-result'),bar=document.getElementById('ela-progress-bar');
 const levels=['A1','A2','B1','B2','C1','C2'],skills=['grammar','vocabulary','reading','listening'],MAX=12;
 let currentLevel=0,index=0,asked=[],responses=[],current=null,busy=false;
 const counts={grammar:0,vocabulary:0,reading:0,listening:0};
 function unused(list){return list.filter(q=>!asked.includes(q.id));}
 function candidate(){
   const preferredSkill=skills.slice().sort((a,b)=>counts[a]-counts[b])[0];
   let list=unused(pool.filter(q=>q.level===levels[currentLevel]&&q.skill===preferredSkill));
   if(list.length)return list;
   list=unused(pool.filter(q=>q.level===levels[currentLevel]));
   if(list.length)return list;
   list=unused(pool.filter(q=>q.skill===preferredSkill));
   if(list.length)return list;
   for(let d=1;d<levels.length;d++){
     list=unused(pool.filter(q=>q.level===levels[currentLevel-d]||q.level===levels[currentLevel+d]));
     if(list.length)return list;
   }
   return unused(pool);
 }
 function pick(){const list=candidate();return list.length?list[Math.floor(Math.random()*list.length)]:null;}
 function render(){
   current=pick();if(!current){finish();return;}asked.push(current.id);
   bar.style.width=Math.round((index/MAX)*100)+'%';
   wrap.innerHTML='<h3>Question '+(index+1)+' of '+MAX+'</h3><p class="ela-question">'+escapeHtml(current.question)+'</p><div class="ela-meta"><span>Adaptive level: '+current.level+'</span><span>Skill: '+escapeHtml(current.skill)+'</span></div><div class="ela-options">'+Object.entries(current.options).map(([k,v])=>'<label><input type="radio" name="ela-answer" value="'+k+'"> <span>'+k+'. '+escapeHtml(v)+'</span></label>').join('')+'</div>';
   next.textContent=index===MAX-1?'Finish':'Next';
 }
 next.addEventListener('click',async function(){
   if(busy)return;const selected=document.querySelector('input[name="ela-answer"]:checked');
   if(!selected){alert('Please choose an answer.');return;}busy=true;next.disabled=true;
   try{
     const data=new URLSearchParams({action:'ela_check_answer',nonce:ELA_CONFIG.nonce,question_id:String(current.id),answer:selected.value});
     const res=await fetch(ELA_CONFIG.ajax_url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:data.toString()});
     const json=await res.json();if(!json.success)throw new Error('Answer check failed');
     const r=json.data,correct=!!r.correct;
     responses.push({question:current,correct:correct,points:Number(current.points)||1});counts[current.skill]=(counts[current.skill]||0)+1;
     if(correct)currentLevel=Math.min(5,currentLevel+1);else currentLevel=Math.max(0,currentLevel-1);
     index++;if(index>=MAX||asked.length>=pool.length)finish();else render();
   }catch(e){alert('Could not evaluate the answer. Please try again.');asked.pop();}
   finally{busy=false;next.disabled=false;}
 });
 function finish(){
   const earned=responses.reduce((sum,r)=>sum+(r.correct?r.points:0),0),possible=responses.reduce((sum,r)=>sum+r.points,0),pct=possible?Math.round(earned/possible*100):0;
   const levelIndex=Math.max(0,Math.min(5,Math.round(currentLevel)));let level=levels[levelIndex];
   if(pct<35)level=levels[Math.max(0,levelIndex-1)];else if(pct>=85&&levelIndex<5)level=levels[levelIndex+1];
   const skillLevels={};skills.forEach(s=>{const rows=responses.filter(r=>r.question.skill===s),e=rows.reduce((sum,r)=>sum+(r.correct?r.points:0),0),p=rows.reduce((sum,r)=>sum+r.points,0),sp=p?Math.round(e/p*100):0;skillLevels[s]=skillToLevel(sp);});
   wrap.innerHTML='';next.hidden=true;result.hidden=false;result.innerHTML='<div class="ela-result-card"><h2>Your estimated level: '+escapeHtml(level)+'</h2><div class="ela-level-badge">'+escapeHtml(level)+'</div><p>Weighted score: <strong>'+earned+' / '+possible+'</strong> ('+pct+'%)</p><div class="ela-skills">'+Object.entries(skillLevels).map(([s,l])=>'<div><strong>'+escapeHtml(s)+'</strong>: '+escapeHtml(l)+'</div>').join('')+'</div><p>Adaptive assessment completed in '+responses.length+' questions.</p></div>';bar.style.width='100%';
   if(window.ELA_CONFIG){const body=new URLSearchParams({action:'ela_save_result',nonce:ELA_CONFIG.nonce,score:String(pct),level:level,skills:JSON.stringify(skillLevels)});fetch(ELA_CONFIG.ajax_url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(r=>r.json()).then(json=>{if(json.success&&json.data&&json.data.url)window.location.href=json.data.url;}).catch(function(){});}
 }
 function skillToLevel(p){return p<35?'A1':p<50?'A2':p<65?'B1':p<78?'B2':p<90?'C1':'C2';}
 function escapeHtml(s){return String(s).replace(/[&<>'\"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','\"':'&quot;'}[c]));}
 render();
})();
