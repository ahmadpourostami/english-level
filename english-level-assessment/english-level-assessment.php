<?php
/**
 * Plugin Name: English Level Assessment
 * Description: CEFR English placement test with secure adaptive evaluation, skill scoring and saved user results.
 * Version: 0.4.0
 * Author: Ahmad Pourostami
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;
define('ELA_VERSION','0.4.0');
require_once __DIR__.'/includes/class-ela-db.php';

class English_Level_Assessment {
    private $levels = ['A1','A2','B1','B2','C1','C2'];
    private $skills = ['grammar','vocabulary','reading','listening'];

    public function __construct(){
        register_activation_hook(__FILE__,[$this,'activate']);
        add_action('admin_menu',[$this,'admin_menu']);
        add_action('admin_post_ela_save_question',[$this,'save_question']);
        add_action('admin_post_ela_delete_question',[$this,'delete_question']);
        add_action('wp_ajax_ela_check_answer',[$this,'check_answer']);
        add_action('wp_ajax_nopriv_ela_check_answer',[$this,'check_answer']);
        add_action('wp_ajax_ela_save_result',[$this,'save_result']);
        add_action('wp_ajax_nopriv_ela_save_result',[$this,'save_result']);
        add_shortcode('english_level_test',[$this,'shortcode']);
        add_action('wp_enqueue_scripts',[$this,'assets']);
    }

    public function activate(){
        global $wpdb;
        $table=$wpdb->prefix.'ela_questions';
        $charset=$wpdb->get_charset_collate();
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,question TEXT NOT NULL,option_a TEXT NOT NULL,option_b TEXT NOT NULL,option_c TEXT NOT NULL,option_d TEXT NOT NULL,correct_answer CHAR(1) NOT NULL,level VARCHAR(2) NOT NULL DEFAULT 'A1',skill VARCHAR(30) NOT NULL DEFAULT 'grammar',points INT NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY level(level),KEY skill(skill)) $charset;");
        ELA_DB::install_results();
        $count=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table");
        if(!$count)$this->seed_questions(); elseif($count<12)$this->seed_advanced();
    }

    private function seed_questions(){
        $qs=[
            ['She ___ a student.','am','is','are','be','B','A1','grammar'],['I ___ coffee every morning.','drink','drinks','drank','drinking','A','A1','grammar'],
            ['They ___ to London last year.','go','goes','went','going','C','A2','grammar'],['Rapid is closest in meaning to:','slow','quick','heavy','late','B','A2','vocabulary'],
            ['If I had more time, I ___ another language.','learn','will learn','would learn','learned','C','B1','grammar'],['Accurate means:','correct','expensive','ancient','simple','A','B1','vocabulary'],
            ['The report ___ by Friday.','will finish','will be finished','finished','has finish','B','B2','grammar'],['The evidence was ___ to prove his claim.','sufficient','sleepy','narrow','casual','A','B2','vocabulary']
        ]; foreach($qs as $q)$this->insert_question($q);
    }
    private function seed_advanced(){
        foreach([
            ['Had I known about the delay, I ___ earlier.','would leave','would have left','left','will leave','B','C1','grammar'],
            ['Her argument was nuanced, yet remarkably ___.','coherent','empty','fragile','random','A','C1','vocabulary'],
            ['The policy is intended to ___ the negative effects of inflation.','mitigate','imitate','compile','allocate','A','C2','vocabulary'],
            ['Were the findings independently verified, the claim ___ considerably stronger.','is','would be','will be','has been','B','C2','grammar']
        ] as $q)$this->insert_question($q);
    }
    private function insert_question($q){
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'ela_questions',['question'=>$q[0],'option_a'=>$q[1],'option_b'=>$q[2],'option_c'=>$q[3],'option_d'=>$q[4],'correct_answer'=>$q[5],'level'=>$q[6],'skill'=>$q[7],'points'=>1]);
    }

    public function admin_menu(){add_menu_page('English Level','English Level','manage_options','ela-questions',[$this,'admin_page'],'dashicons-welcome-learn-more');}

    private function clean_choice($value,$allowed,$fallback){
        $value=sanitize_text_field(wp_unslash($value??''));
        return in_array($value,$allowed,true)?$value:$fallback;
    }

    public function admin_page(){
        if(!current_user_can('manage_options'))return;
        global $wpdb;
        $table=$wpdb->prefix.'ela_questions';
        $edit_id=absint($_GET['edit']??0);
        $editing=$edit_id?(object)$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$edit_id)):null;
        if($edit_id&&!$editing){$edit_id=0;}

        $level=$this->clean_choice($_GET['level']??'',$this->levels,'');
        $skill=$this->clean_choice($_GET['skill']??'',$this->skills,'');
        $search=sanitize_text_field(wp_unslash($_GET['s']??''));
        $paged=max(1,absint($_GET['paged']??1));
        $per_page=20;
        $where=[];$params=[];
        if($level){$where[]='level=%s';$params[]=$level;}
        if($skill){$where[]='skill=%s';$params[]=$skill;}
        if($search){$where[]='question LIKE %s';$params[]='%'.$wpdb->esc_like($search).'%';}
        $where_sql=$where?' WHERE '.implode(' AND ',$where):'';
        $count_sql="SELECT COUNT(*) FROM $table$where_sql";
        $total=$params?$wpdb->get_var($wpdb->prepare($count_sql,$params)):$wpdb->get_var($count_sql);
        $offset=($paged-1)*$per_page;
        $list_sql="SELECT * FROM $table$where_sql ORDER BY id DESC LIMIT %d OFFSET %d";
        $list_params=$params; $list_params[]=$per_page; $list_params[]=$offset;
        $qs=$wpdb->get_results($wpdb->prepare($list_sql,$list_params));
        $pages=max(1,(int)ceil($total/$per_page));
        $level_counts=$wpdb->get_results("SELECT level,COUNT(*) total FROM $table GROUP BY level ORDER BY FIELD(level,'A1','A2','B1','B2','C1','C2')");
        $skill_counts=$wpdb->get_results("SELECT skill,COUNT(*) total FROM $table GROUP BY skill ORDER BY skill");
        $level_map=[];foreach($level_counts as $row)$level_map[$row->level]=(int)$row->total;
        $skill_map=[];foreach($skill_counts as $row)$skill_map[$row->skill]=(int)$row->total;
        $results=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}ela_results ORDER BY id DESC LIMIT 50");
        ?>
        <div class="wrap">
            <h1>English Level Assessment</h1>
            <p>شورت‌کد آزمون: <code>[english_level_test]</code></p>
            <style>
                .ela-admin-grid{display:grid;grid-template-columns:repeat(7,minmax(100px,1fr));gap:10px;margin:20px 0}
                .ela-admin-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px}.ela-admin-card strong{display:block;font-size:22px;margin-top:5px}.ela-admin-form{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;margin:20px 0}.ela-admin-form .ela-row{display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px}.ela-admin-form input[type=text],.ela-admin-form textarea,.ela-admin-form select{width:100%}.ela-filter{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:18px 0}.ela-filter input,.ela-filter select{min-height:34px}.ela-question-cell{max-width:520px}.ela-actions{white-space:nowrap}.ela-danger{color:#b32d2e}.ela-pagination{margin-top:16px}
                @media(max-width:900px){.ela-admin-grid{grid-template-columns:repeat(3,1fr)}.ela-admin-form .ela-row{grid-template-columns:1fr}}
            </style>
            <div class="ela-admin-grid">
                <div class="ela-admin-card">Total<strong><?php echo (int)$total; ?></strong></div>
                <?php foreach($this->levels as $l): ?><div class="ela-admin-card"><?php echo esc_html($l); ?><strong><?php echo (int)($level_map[$l]??0); ?></strong></div><?php endforeach; ?>
            </div>

            <div class="ela-admin-form">
                <h2><?php echo $editing?'ویرایش سؤال #'.(int)$editing->id:'افزودن سؤال'; ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="ela_save_question">
                    <input type="hidden" name="id" value="<?php echo $editing?(int)$editing->id:0; ?>">
                    <?php wp_nonce_field('ela_save_question'); ?>
                    <p><textarea name="question" rows="3" class="large-text" placeholder="Question" required><?php echo $editing?esc_textarea($editing->question):''; ?></textarea></p>
                    <div class="ela-row">
                        <?php foreach(['a','b','c','d'] as $letter): $field='option_'.$letter; ?><p><label><strong><?php echo strtoupper($letter); ?></strong></label><input type="text" name="<?php echo esc_attr($field); ?>" value="<?php echo $editing?esc_attr($editing->$field):''; ?>" required></p><?php endforeach; ?>
                    </div>
                    <div class="ela-row">
                        <p><label>Correct answer</label><select name="correct_answer"><?php foreach(['A','B','C','D'] as $c): ?><option value="<?php echo $c; ?>" <?php selected($editing?$editing->correct_answer:'A',$c); ?>><?php echo $c; ?></option><?php endforeach; ?></select></p>
                        <p><label>Level</label><select name="level"><?php foreach($this->levels as $l): ?><option value="<?php echo $l; ?>" <?php selected($editing?$editing->level:'A1',$l); ?>><?php echo $l; ?></option><?php endforeach; ?></select></p>
                        <p><label>Skill</label><select name="skill"><?php foreach($this->skills as $s): ?><option value="<?php echo $s; ?>" <?php selected($editing?$editing->skill:'grammar',$s); ?>><?php echo ucfirst($s); ?></option><?php endforeach; ?></select></p>
                    </div>
                    <p><label>Points</label> <input type="number" name="points" min="1" max="10" value="<?php echo $editing?max(1,(int)$editing->points):1; ?>" style="width:90px"></p>
                    <?php submit_button($editing?'Update Question':'Add Question'); ?>
                    <?php if($editing): ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ela-questions')); ?>">Cancel edit</a><?php endif; ?>
                </form>
            </div>

            <h2>Question Bank</h2>
            <form class="ela-filter" method="get">
                <input type="hidden" name="page" value="ela-questions">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search questions...">
                <select name="level"><option value="">All levels</option><?php foreach($this->levels as $l): ?><option value="<?php echo $l; ?>" <?php selected($level,$l); ?>><?php echo $l; ?></option><?php endforeach; ?></select>
                <select name="skill"><option value="">All skills</option><?php foreach($this->skills as $s): ?><option value="<?php echo $s; ?>" <?php selected($skill,$s); ?>><?php echo ucfirst($s); ?></option><?php endforeach; ?></select>
                <button class="button button-primary">Filter</button>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ela-questions')); ?>">Reset</a>
            </form>
            <p><strong>Skills:</strong> <?php foreach($this->skills as $s) echo esc_html(ucfirst($s)).': '.(int)($skill_map[$s]??0).' &nbsp; '; ?></p>
            <table class="widefat striped">
                <thead><tr><th>ID</th><th>Question</th><th>Level</th><th>Skill</th><th>Points</th><th>Correct</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if($qs): foreach($qs as $q): ?>
                    <tr><td><?php echo (int)$q->id; ?></td><td class="ela-question-cell"><?php echo esc_html($q->question); ?></td><td><strong><?php echo esc_html($q->level); ?></strong></td><td><?php echo esc_html(ucfirst($q->skill)); ?></td><td><?php echo (int)$q->points; ?></td><td><?php echo esc_html($q->correct_answer); ?></td><td class="ela-actions"><a href="<?php echo esc_url(add_query_arg(['page'=>'ela-questions','edit'=>$q->id],admin_url('admin.php'))); ?>">Edit</a> | <a class="ela-danger" onclick="return confirm('Delete this question?');" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ela_delete_question&id='.$q->id),'ela_delete_'.$q->id)); ?>">Delete</a></td></tr>
                <?php endforeach; else: ?><tr><td colspan="7">No questions found.</td></tr><?php endif; ?>
                </tbody>
            </table>
            <?php if($pages>1): ?><div class="ela-pagination"><?php echo wp_kses_post(paginate_links(['base'=>add_query_arg(['page'=>'ela-questions','paged'=>'%#%','s'=>$search,'level'=>$level,'skill'=>$skill],admin_url('admin.php')),'format'=>'','current'=>$paged,'total'=>$pages,'type'=>'plain'])); ?></div><?php endif; ?>

            <h2>Latest Results</h2>
            <table class="widefat striped"><thead><tr><th>User</th><th>Score</th><th>Level</th><th>Date</th></tr></thead><tbody>
            <?php foreach($results as $r): ?><tr><td><?php echo $r->user_id?(int)$r->user_id:'Guest'; ?></td><td><?php echo (int)$r->score; ?>%</td><td><?php echo esc_html($r->level); ?></td><td><?php echo esc_html($r->created_at); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    public function save_question(){
        if(!current_user_can('manage_options')||!check_admin_referer('ela_save_question'))wp_die('Unauthorized');
        global $wpdb;
        $table=$wpdb->prefix.'ela_questions';
        $id=absint($_POST['id']??0);
        $level=$this->clean_choice($_POST['level']??'A1',$this->levels,'A1');
        $skill=$this->clean_choice($_POST['skill']??'grammar',$this->skills,'grammar');
        $correct=$this->clean_choice($_POST['correct_answer']??'A',['A','B','C','D'],'A');
        $points=max(1,min(10,absint($_POST['points']??1)));
        $data=[
            'question'=>sanitize_textarea_field(wp_unslash($_POST['question']??'')),
            'option_a'=>sanitize_text_field(wp_unslash($_POST['option_a']??'')),
            'option_b'=>sanitize_text_field(wp_unslash($_POST['option_b']??'')),
            'option_c'=>sanitize_text_field(wp_unslash($_POST['option_c']??'')),
            'option_d'=>sanitize_text_field(wp_unslash($_POST['option_d']??'')),
            'correct_answer'=>$correct,'level'=>$level,'skill'=>$skill,'points'=>$points
        ];
        if($id)$wpdb->update($table,$data,['id'=>$id]); else $wpdb->insert($table,$data);
        wp_safe_redirect(admin_url('admin.php?page=ela-questions'));exit;
    }

    public function delete_question(){
        $id=absint($_GET['id']??0);
        if(!current_user_can('manage_options')||!$id||!wp_verify_nonce($_GET['_wpnonce']??'','ela_delete_'.$id))wp_die('Unauthorized');
        global $wpdb;$wpdb->delete($wpdb->prefix.'ela_questions',['id'=>$id]);
        wp_safe_redirect(admin_url('admin.php?page=ela-questions'));exit;
    }

    public function check_answer(){
        check_ajax_referer('ela_result','nonce');global $wpdb;
        $id=absint($_POST['question_id']??0);$choice=strtoupper(sanitize_text_field(wp_unslash($_POST['answer']??'')));
        if(!$id||!in_array($choice,['A','B','C','D'],true))wp_send_json_error(['message'=>'Invalid answer.'],400);
        $q=$wpdb->get_row($wpdb->prepare("SELECT id,correct_answer,level,skill,points FROM {$wpdb->prefix}ela_questions WHERE id=%d",$id));
        if(!$q)wp_send_json_error(['message'=>'Question not found.'],404);
        wp_send_json_success(['correct'=>hash_equals($q->correct_answer,$choice),'level'=>$q->level,'skill'=>$q->skill,'points'=>(int)$q->points]);
    }

    public function save_result(){
        check_ajax_referer('ela_result','nonce');$score=max(0,min(100,(int)($_POST['score']??0)));$level=$this->clean_choice($_POST['level']??'A1',$this->levels,'A1');$skills=isset($_POST['skills'])?json_decode(wp_unslash($_POST['skills']),true):[];ELA_DB::save_result(get_current_user_id(),$score,$level,is_array($skills)?$skills:[]);wp_send_json_success();
    }

    public function assets(){
        if(!is_singular())return;global $post;
        if($post&&has_shortcode($post->post_content,'english_level_test')){wp_enqueue_style('ela-style',plugins_url('assets/style.css',__FILE__),[],ELA_VERSION);wp_enqueue_script('ela-script',plugins_url('assets/app.js',__FILE__),[],ELA_VERSION,true);wp_localize_script('ela-script','ELA_CONFIG',['ajax_url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('ela_result')]);}
    }

    public function shortcode(){
        global $wpdb;$questions=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}ela_questions ORDER BY FIELD(level,'A1','A2','B1','B2','C1','C2'),id ASC");
        if(!$questions)return '<p>No questions available.</p>';ob_start();
        ?><div id="ela-test" class="ela-test" dir="ltr"><div class="ela-progress"><span id="ela-progress-bar"></span></div><div id="ela-question-wrap"></div><button type="button" id="ela-next">Next</button><div id="ela-result" hidden></div></div><script>window.ELA_QUESTIONS=<?php echo wp_json_encode(array_map(function($q){return ['id'=>(int)$q->id,'question'=>$q->question,'options'=>['A'=>$q->option_a,'B'=>$q->option_b,'C'=>$q->option_c,'D'=>$q->option_d],'level'=>$q->level,'skill'=>$q->skill];},$questions));?>;</script><?php return ob_get_clean();
    }
}
new English_Level_Assessment();
