<?php
if (!defined('ABSPATH')) exit;

class ELA_Media {
    public static function table() { global $wpdb; return $wpdb->prefix . 'ela_question_media'; }

    public static function install() {
        global $wpdb;
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (question_id BIGINT UNSIGNED NOT NULL,audio_url TEXT NULL,passage LONGTEXT NULL,updated_at DATETIME NOT NULL,PRIMARY KEY(question_id)) $charset;");
    }

    public static function get($question_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE question_id=%d', absint($question_id)));
    }

    public static function save($question_id, $audio_url, $passage) {
        global $wpdb;
        $id = absint($question_id);
        if (!$id) return false;
        return $wpdb->replace(self::table(), [
            'question_id' => $id,
            'audio_url' => esc_url_raw($audio_url),
            'passage' => wp_kses_post($passage),
            'updated_at' => current_time('mysql')
        ], ['%d','%s','%s','%s']);
    }
}

add_action('plugins_loaded', function(){ ELA_Media::install(); }, 5);

add_action('admin_menu', function(){
    add_submenu_page('ela-questions', 'Question Media', 'Listening / Reading Media', 'manage_options', 'ela-media', 'ela_media_admin_page');
});

add_action('admin_post_ela_save_media', function(){
    if (!current_user_can('manage_options') || !check_admin_referer('ela_save_media')) wp_die('Unauthorized');
    $question_id = absint($_POST['question_id'] ?? 0);
    global $wpdb;
    $exists = $question_id ? $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . $wpdb->prefix . 'ela_questions WHERE id=%d', $question_id)) : 0;
    if (!$exists) wp_die('Question not found.');
    ELA_Media::save($question_id, sanitize_text_field(wp_unslash($_POST['audio_url'] ?? '')), wp_kses_post(wp_unslash($_POST['passage'] ?? '')));
    wp_safe_redirect(admin_url('admin.php?page=ela-media&question_id=' . $question_id . '&saved=1')); exit;
});

function ela_media_admin_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'ela_questions';
    $question_id = absint($_GET['question_id'] ?? 0);
    $questions = $wpdb->get_results("SELECT id,question,level,skill FROM $table ORDER BY FIELD(level,'A1','A2','B1','B2','C1','C2'),id ASC");
    $media = $question_id ? ELA_Media::get($question_id) : null;
    ?>
    <div class="wrap">
        <h1>Listening / Reading Media</h1>
        <p>برای سؤال‌های Listening فایل صوتی و برای سؤال‌های Reading متن passage را ثبت کن. لینک صوت باید از Media Library یا یک URL معتبر وردپرس باشد.</p>
        <form method="get" style="margin:20px 0">
            <input type="hidden" name="page" value="ela-media">
            <select name="question_id" onchange="this.form.submit()" style="min-width:420px">
                <option value="0">Select a question...</option>
                <?php foreach ($questions as $q): ?>
                    <option value="<?php echo (int)$q->id; ?>" <?php selected($question_id, $q->id); ?>><?php echo esc_html('#'.$q->id.' · '.$q->level.' · '.ucfirst($q->skill).' · '.$q->question); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($question_id && $media !== null): ?>
            <div class="card" style="max-width:900px;padding:20px">
                <h2>Question #<?php echo (int)$question_id; ?></h2>
                <?php $selected_q = null; foreach ($questions as $q) { if ((int)$q->id === $question_id) { $selected_q = $q; break; } } ?>
                <?php if ($selected_q): ?><p><strong><?php echo esc_html($selected_q->level.' · '.ucfirst($selected_q->skill)); ?></strong><br><?php echo esc_html($selected_q->question); ?></p><?php endif; ?>
                <?php if (!empty($_GET['saved'])): ?><div class="notice notice-success"><p>Media settings saved.</p></div><?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="ela_save_media">
                    <input type="hidden" name="question_id" value="<?php echo (int)$question_id; ?>">
                    <?php wp_nonce_field('ela_save_media'); ?>
                    <p><label><strong>Audio URL</strong></label><br><input type="url" name="audio_url" class="large-text" value="<?php echo esc_attr($media->audio_url ?? ''); ?>" placeholder="https://example.com/audio.mp3"></p>
                    <p><label><strong>Reading passage</strong></label><br><textarea name="passage" rows="10" class="large-text" placeholder="Paste the reading passage here..."><?php echo esc_textarea($media->passage ?? ''); ?></textarea></p>
                    <?php submit_button('Save Media'); ?>
                </form>
            </div>
        <?php elseif ($question_id): ?>
            <div class="card" style="max-width:900px;padding:20px">
                <h2>Question #<?php echo (int)$question_id; ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="ela_save_media"><input type="hidden" name="question_id" value="<?php echo (int)$question_id; ?>"><?php wp_nonce_field('ela_save_media'); ?>
                    <p><label><strong>Audio URL</strong></label><br><input type="url" name="audio_url" class="large-text" placeholder="https://example.com/audio.mp3"></p>
                    <p><label><strong>Reading passage</strong></label><br><textarea name="passage" rows="10" class="large-text" placeholder="Paste the reading passage here..."></textarea></p>
                    <?php submit_button('Save Media'); ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
