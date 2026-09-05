<?php
if (!defined('ABSPATH')) exit;
class ELA_DB {
 public static function questions(){global $wpdb; return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ela_questions ORDER BY FIELD(level,'A1','A2','B1','B2','C1','C2'), id ASC");}
 public static function save_result($user_id,$score,$level,$skills){global $wpdb;$table=$wpdb->prefix.'ela_results';$ok=$wpdb->insert($table,['user_id'=>$user_id,'score'=>$score,'level'=>$level,'skills'=>wp_json_encode($skills),'created_at'=>current_time('mysql')]);return $ok?$wpdb->insert_id:false;}
 public static function get_result($id){global $wpdb;$table=$wpdb->prefix.'ela_results';return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",absint($id)));}
 public static function install_results(){global $wpdb;$table=$wpdb->prefix.'ela_results';$charset=$wpdb->get_charset_collate();require_once ABSPATH.'wp-admin/includes/upgrade.php';dbDelta("CREATE TABLE $table (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,score INT NOT NULL DEFAULT 0,level VARCHAR(2) NOT NULL,skills LONGTEXT NULL,created_at DATETIME NOT NULL,PRIMARY KEY(id),KEY user_id(user_id)) $charset;");}
}
require_once __DIR__.'/class-ela-adaptive.php';
