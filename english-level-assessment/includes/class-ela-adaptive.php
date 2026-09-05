<?php
if (!defined('ABSPATH')) exit;

class ELA_Adaptive_API {
    private $levels = ['A1','A2','B1','B2','C1','C2'];
    private $skills = ['grammar','vocabulary','reading','listening'];
    private $max_questions = 12;
    private $ttl = 3600;

    public function __construct() {
        add_action('wp_ajax_ela_start_test', [$this,'start']);
        add_action('wp_ajax_nopriv_ela_start_test', [$this,'start']);
        add_action('wp_ajax_ela_submit_answer', [$this,'submit_answer']);
        add_action('wp_ajax_nopriv_ela_submit_answer', [$this,'submit_answer']);
        add_action('init', function(){ add_shortcode('english_level_test', [$this,'shortcode']); }, 20);
    }
    private function nonce(){check_ajax_referer('ela_result','nonce');}
    private function token(){return wp_generate_password(32,false,false);}
    private function key($token){return 'ela_test_'.hash('sha256',$token);}
    private function load($token){if(!is_string($token)||strlen($token)<20)return false;$state=get_transient($this->key($token));return is_array($state)?$state:false;}
    private function save($token,$state){set_transient($this->key($token),$state,$this->ttl);}
    private function public_question($q){
        $media=ELA_Media::get($q->id);
        return [
            'id'=>(int)$q->id,
            'question'=>$q->question,
            'options'=>['A'=>$q->option_a,'B'=>$q->option_b,'C'=>$q->option_c,'D'=>$q->option_d],
            'level'=>$q->level,
            'skill'=>$q->skill,
            'audio_url'=>$media&&!empty($media->audio_url)?esc_url_raw($media->audio_url):'',
            'passage'=>$media&&!empty($media->passage)?wp_kses_post($media->passage):''
        ];
    }
    private function next_question($state){
        global $wpdb;$table=$wpdb->prefix.'ela_questions';$used=array_map('absint',$state['asked']);$current=max(0,min(5,(int)$state['level_index']));$level=$this->levels[$current];$counts=$state['skill_counts'];$skills=$this->skills;usort($skills,function($a,$b)use($counts){return ($counts[$a]??0)<=>($counts[$b]??0);});$preferred=$skills[0];$where_not=$used?' AND id NOT IN ('.implode(',',$used).')':'';
        $queries=[$wpdb->prepare("SELECT * FROM $table WHERE level=%s AND skill=%s$where_not ORDER BY RAND() LIMIT 1",$level,$preferred),$wpdb->prepare("SELECT * FROM $table WHERE level=%s$where_not ORDER BY RAND() LIMIT 1",$level),$wpdb->prepare("SELECT * FROM $table WHERE skill=%s$where_not ORDER BY RAND() LIMIT 1",$preferred)];
        for($d=1;$d<count($this->levels);$d++){if($current-$d>=0)$queries[]=$wpdb->prepare("SELECT * FROM $table WHERE level=%s$where_not ORDER BY RAND() LIMIT 1",$this->levels[$current-$d]);if($current+$d<count($this->levels))$queries[]=$wpdb->prepare("SELECT * FROM $table WHERE level=%s$where_not ORDER BY RAND() LIMIT 1",$this->levels[$current+$d]);}
        $queries[]="SELECT * FROM $table WHERE 1=1$where_not ORDER BY RAND() LIMIT 1";foreach($queries as $sql){$q=$wpdb->get_row($sql);if($q)return $q;}return null;
    }
    public function start(){
        $this->nonce();$token=$this->token();$state=['asked'=>[],'responses'=>[],'level_index'=>0,'skill_counts'=>array_fill_keys($this->skills,0),'started_at'=>time()];$q=$this->next_question($state);if(!$q)wp_send_json_error(['message'=>'No questions are available.'],500);$state['asked'][]=(int)$q->id;$this->save($token,$state);wp_send_json_success(['token'=>$token,'number'=>1,'total'=>$this->max_questions,'question'=>$this->public_question($q)]);
    }
    public function submit_answer(){
        $this->nonce();$token=sanitize_text_field(wp_unslash($_POST['token']??''));$question_id=absint($_POST['question_id']??0);$answer=strtoupper(sanitize_text_field(wp_unslash($_POST['answer']??'')));if(!$question_id||!in_array($answer,['A','B','C','D'],true))wp_send_json_error(['message'=>'Invalid answer.'],400);$state=$this->load($token);if(!$state)wp_send_json_error(['message'=>'Test session expired. Please restart the test.'],410);$asked=array_map('absint',$state['asked']);if(!$asked||end($asked)!==$question_id)wp_send_json_error(['message'=>'Invalid test state.'],400);
        global $wpdb;$table=$wpdb->prefix.'ela_questions';$q=$wpdb->get_row($wpdb->prepare("SELECT id,correct_answer,level,skill,points FROM $table WHERE id=%d",$question_id));if(!$q)wp_send_json_error(['message'=>'Question not found.'],404);$correct=hash_equals($q->correct_answer,$answer);$points=max(1,min(10,(int)$q->points));$state['responses'][]=['question_id'=>(int)$q->id,'level'=>$q->level,'skill'=>$q->skill,'correct'=>$correct?1:0,'points'=>$points];$state['skill_counts'][$q->skill]=($state['skill_counts'][$q->skill]??0)+1;$state['level_index']=$correct?min(5,(int)$state['level_index']+1):max(0,(int)$state['level_index']-1);
        if(count($state['responses'])<$this->max_questions){$next=$this->next_question($state);if($next){$state['asked'][]=(int)$next->id;$this->save($token,$state);wp_send_json_success(['done'=>false,'number'=>count($state['responses'])+1,'total'=>$this->max_questions,'question'=>$this->public_question($next)]);}}
        $result=$this->calculate($state);$id=ELA_DB::save_result(get_current_user_id(),$result['score'],$result['level'],$result['skills']);delete_transient($this->key($token));if(!$id)wp_send_json_error(['message'=>'Could not save result.'],500);$page_id=absint(get_option('ela_page_result',0));$url=$page_id?add_query_arg('ela_result',(int)$id,get_permalink($page_id)):home_url('/');wp_send_json_success(['done'=>true,'result'=>$result,'url'=>$url]);
    }
    private function calculate($state){
        $earned=0;$possible=0;foreach($state['responses'] as $r){$possible+=(int)$r['points'];if(!empty($r['correct']))$earned+=(int)$r['points'];}$score=$possible?(int)round(($earned/$possible)*100):0;$idx=max(0,min(5,(int)$state['level_index']));if($score<35)$idx=max(0,$idx-1);elseif($score>=85)$idx=min(5,$idx+1);$skills=[];foreach($this->skills as $skill){$e=0;$p=0;foreach($state['responses'] as $r){if($r['skill']!==$skill)continue;$p+=(int)$r['points'];if(!empty($r['correct']))$e+=(int)$r['points'];}$pct=$p?(int)round(($e/$p)*100):0;$skills[$skill]=$pct<35?'A1':($pct<50?'A2':($pct<65?'B1':($pct<78?'B2':($pct<90?'C1':'C2'))));}return ['score'=>$score,'level'=>$this->levels[$idx],'skills'=>$skills];
    }
    public function shortcode(){return '<div id="ela-test" class="ela-test" dir="ltr"><div class="ela-progress"><span id="ela-progress-bar"></span></div><div id="ela-question-wrap"></div><button type="button" id="ela-next">Start Test</button><div id="ela-result" hidden></div></div>';}
}
new ELA_Adaptive_API();
