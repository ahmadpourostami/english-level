(function(){
  const root=document.getElementById('ela-test');
  if(!root||!window.ELA_QUESTIONS)return;
  const questions=window.ELA_QUESTIONS, wrap=document.getElementById('ela-question-wrap'), next=document.getElementById('ela-next'), result=document.getElementById('ela-result'), bar=document.getElementById('ela-progress-bar');
  let index=0, answers={};
  function render(){
    const q=questions[index];
    bar.style.width=((index/questions.length)*100)+'%';
    wrap.innerHTML='<h3>Question '+(index+1)+' of '+questions.length+'</h3><p class="ela-question">'+escapeHtml(q.question)+'</p><div class="ela-options">'+Object.entries(q.options).map(([k,v])=>'<label><input type="radio" name="ela-answer" value="'+k+'" '+(answers[q.id]===k?'checked':'')+'> <span>'+k+'. '+escapeHtml(v)+'</span></label>').join('')+'</div>';
    next.textContent=index===questions.length-1?'Finish':'Next';
  }
  next.addEventListener('click',function(){
    const selected=document.querySelector('input[name="ela-answer"]:checked');
    if(!selected){alert('Please choose an answer.');return;}
    answers[questions[index].id]=selected.value;
    if(index<questions.length-1){index++;render();return;}
    finish();
  });
  function finish(){
    let correct=0, byLevel={A1:0,A2:0,B1:0,B2:0,C1:0,C2:0};
    questions.forEach(q=>{if(answers[q.id]===q.answer){correct++;byLevel[q.level]++;}});
    const pct=Math.round(correct/questions.length*100);
    let level=pct<35?'A1':pct<50?'A2':pct<65?'B1':pct<78?'B2':pct<90?'C1':'C2';
    wrap.innerHTML=''; next.hidden=true; result.hidden=false; result.innerHTML='<div class="ela-result-card"><h2>Your estimated level: '+level+'</h2><p>Score: '+correct+' / '+questions.length+' ('+pct+'%)</p><p>This is an initial automated estimate. Writing and speaking evaluation can be added through an AI provider in a later version.</p></div>';
    bar.style.width='100%';
  }
  function escapeHtml(s){return String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
  render();
})();
