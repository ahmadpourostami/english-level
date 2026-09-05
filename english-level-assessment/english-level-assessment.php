<?php
/**
 * Plugin Name: English Level Assessment
 * Description: Automatic English placement test with CEFR scoring, admin question management, and optional AI evaluation hooks.
 * Version: 0.1.0
 * Author: Ahmad Pourostami
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

define('ELA_VERSION', '0.1.0');
define('ELA_FILE', __FILE__);
define('ELA_DIR', plugin_dir_path(__FILE__));

class English_Level_Assessment {
    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_post_ela_save_question', [$this, 'save_question']);
        add_action('admin_post_ela_delete_question', [$this, 'delete_question']);
        add_shortcode('english_level_test', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
    }

    public function activate() {
        global $wpdb;
        $table = $wpdb->prefix . 'ela_questions';
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question TEXT NOT NULL,
            option_a TEXT NOT NULL,
            option_b TEXT NOT NULL,
            option_c TEXT NOT NULL,
            option_d TEXT NOT NULL,
            correct_answer CHAR(1) NOT NULL,
            level VARCHAR(2) NOT NULL DEFAULT 'A1',
            skill VARCHAR(30) NOT NULL DEFAULT 'grammar',
            points INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY skill (skill)
        ) $charset;";
        dbDelta($sql);
        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM $table") === 0) $this->seed_questions();
    }

    private function seed_questions() {
        global $wpdb;
        $table = $wpdb->prefix . 'ela_questions';
        $questions = [
            ['She ___ a student.', 'am','is','are','be','B','A1','grammar'],
            ['I ___ coffee every morning.', 'drink','drinks','drank','drinking','A','A1','grammar'],
            ['They ___ to London last year.', 'go','goes','went','going','C','A2','grammar'],
            ['If I had more time, I ___ another language.', 'learn','will learn','would learn','learned','C','B1','grammar'],
            ['The report ___ by Friday.', 'will finish','will be finished','finished','has finish','B','B2','grammar'],
            ['The word “rapid” is closest in meaning to:', 'slow','quick','heavy','late','B','A2','vocabulary'],
            ['“Accurate” means:', 'correct','expensive','ancient','simple','A','B1','vocabulary'],
            ['Choose the best word: The evidence was ___ to prove his claim.', 'sufficient','sleepy','narrow','casual','A','B2','vocabulary'],
        ];
        foreach ($questions as $q) {
            $wpdb->insert($table, [
                'question'=>$q[0], 'option_a'=>$q[1], 'option_b'=>$q[2], 'option_c'=>$q[3], 'option_d'=>$q[4],
                'correct_answer'=>$q[5], 'level'=>$q[6], 'skill'=>$q[7], 'points'=>1
            ]);
        }
    }

    public function admin_menu() {
        add_menu_page('English Level', 'English Level', 'manage_options', 'ela-questions', [$this, 'admin_page'], 'dashicons-welcome-learn-more');
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $table = $wpdb->prefix . 'ela_questions';
        $questions = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
        ?>
        <div class="wrap">
            <h1>English Level Assessment</h1>
            <p>مدیریت سوالات آزمون تعیین سطح. شورت‌کد آزمون: <code>[english_level_test]</code></p>
            <hr>
            <h2>افزودن سوال</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ela_save_question">
                <?php wp_nonce_field('ela_save_question'); ?>
                <table class="form-table">
                    <tr><th>Question</th><td><textarea name="question" rows="3" class="large-text" required></textarea></td></tr>
                    <tr><th>Options</th><td>
                        <input name="option_a" class="regular-text" placeholder="A" required><br><br>
                        <input name="option_b" class="regular-text" placeholder="B" required><br><br>
                        <input name="option_c" class="regular-text" placeholder="C" required><br><br>
                        <input name="option_d" class="regular-text" placeholder="D" required>
                    </td></tr>
                    <tr><th>Correct</th><td><select name="correct_answer"><option>A</option><option>B</option><option>C</option><option>D</option></select></td></tr>
                    <tr><th>CEFR Level</th><td><select name="level"><?php foreach(['A1','A2','B1','B2','C1','C2'] as $l) echo '<option>'.$l.'</option>'; ?></select></td></tr>
                    <tr><th>Skill</th><td><select name="skill"><?php foreach(['grammar','vocabulary','reading','listening'] as $s) echo '<option value="'.esc_attr($s).'">'.esc_html(ucfirst($s)).'</option>'; ?></select></td></tr>
                </table>
                <?php submit_button('Add Question'); ?>
            </form>
            <hr>
            <h2>Questions (<?php echo count($questions); ?>)</h2>
            <table class="widefat striped"><thead><tr><th>ID</th><th>Question</th><th>Level</th><th>Skill</th><th>Correct</th><th></th></tr></thead><tbody>
            <?php foreach($questions as $q): ?><tr>
                <td><?php echo (int)$q->id; ?></td><td><?php echo esc_html($q->question); ?></td><td><?php echo esc_html($q->level); ?></td><td><?php echo esc_html($q->skill); ?></td><td><?php echo esc_html($q->correct_answer); ?></td>
                <td><a class="button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ela_delete_question&id='.$q->id), 'ela_delete_'.$q->id)); ?>">Delete</a></td>
            </tr><?php endforeach; ?></tbody></table>
        </div>
        <?php
    }

    public function save_question() {
        if (!current_user_can('manage_options') || !check_admin_referer('ela_save_question')) wp_die('Unauthorized');
        global $wpdb; $table = $wpdb->prefix . 'ela_questions';
        $wpdb->insert($table, [
            'question'=>sanitize_textarea_field(wp_unslash($_POST['question'] ?? '')),
            'option_a'=>sanitize_text_field(wp_unslash($_POST['option_a'] ?? '')),
            'option_b'=>sanitize_text_field(wp_unslash($_POST['option_b'] ?? '')),
            'option_c'=>sanitize_text_field(wp_unslash($_POST['option_c'] ?? '')),
            'option_d'=>sanitize_text_field(wp_unslash($_POST['option_d'] ?? '')),
            'correct_answer'=>sanitize_text_field(wp_unslash($_POST['correct_answer'] ?? 'A')),
            'level'=>sanitize_text_field(wp_unslash($_POST['level'] ?? 'A1')),
            'skill'=>sanitize_text_field(wp_unslash($_POST['skill'] ?? 'grammar')),
        ]);
        wp_safe_redirect(admin_url('admin.php?page=ela-questions&saved=1')); exit;
    }

    public function delete_question() {
        $id = absint($_GET['id'] ?? 0);
        if (!current_user_can('manage_options') || !$id || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'ela_delete_'.$id)) wp_die('Unauthorized');
        global $wpdb; $wpdb->delete($wpdb->prefix.'ela_questions', ['id'=>$id]);
        wp_safe_redirect(admin_url('admin.php?page=ela-questions&deleted=1')); exit;
    }

    public function assets() {
        if (!is_singular()) return;
        global $post;
        if ($post && has_shortcode($post->post_content, 'english_level_test')) {
            wp_enqueue_style('ela-style', plugins_url('assets/style.css', __FILE__), [], ELA_VERSION);
            wp_enqueue_script('ela-script', plugins_url('assets/app.js', __FILE__), [], ELA_VERSION, true);
        }
    }

    public function shortcode() {
        global $wpdb;
        $questions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ela_questions ORDER BY level ASC, id ASC");
        if (!$questions) return '<p>هنوز سوالی برای آزمون تعریف نشده است.</p>';
        ob_start(); ?>
        <div id="ela-test" class="ela-test" dir="ltr">
            <div class="ela-progress"><span id="ela-progress-bar"></span></div>
            <div id="ela-question-wrap"></div>
            <button type="button" id="ela-next">Next</button>
            <div id="ela-result" hidden></div>
        </div>
        <script>window.ELA_QUESTIONS=<?php echo wp_json_encode(array_map(function($q){return ['id'=>(int)$q->id,'question'=>$q->question,'options'=>['A'=>$q->option_a,'B'=>$q->option_b,'C'=>$q->option_c,'D'=>$q->option_d],'answer'=>$q->correct_answer,'level'=>$q->level,'skill'=>$q->skill];}, $questions)); ?>;</script>
        <?php return ob_get_clean();
    }
}
new English_Level_Assessment();
