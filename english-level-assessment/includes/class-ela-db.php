<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/class-ela-question-bank.php';

class ELA_DB {
 public static function questions(){global $wpdb; return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ela_questions ORDER BY FIELD(level,'A1','A2','B1','B2','C1','C2'), id ASC");}
 public static function save_result($user_id,$score,$level,$skills){global $wpdb;$table=$wpdb->prefix.'ela_results';$ok=$wpdb->insert($table,['user_id'=>$user_id,'score'=>$score,'level'=>$level,'skills'=>wp_json_encode($skills),'created_at'=>current_time('mysql')]);return $ok?$wpdb->insert_id:false;}
 public static function get_result($id){global $wpdb;$table=$wpdb->prefix.'ela_results';return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",absint($id)));}
 public static function install_results(){global $wpdb;$table=$wpdb->prefix.'ela_results';$charset=$wpdb->get_charset_collate();require_once ABSPATH.'wp-admin/includes/upgrade.php';dbDelta("CREATE TABLE $table (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,score INT NOT NULL DEFAULT 0,level VARCHAR(2) NOT NULL,skills LONGTEXT NULL,created_at DATETIME NOT NULL,PRIMARY KEY(id),KEY user_id(user_id)) $charset;");}
 public static function seed_expanded_bank(){
  global $wpdb;
  $table=$wpdb->prefix.'ela_questions';
  if(!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$table))) return;
  $counts=$wpdb->get_results("SELECT level,skill,COUNT(*) total FROM $table GROUP BY level,skill");
  $existing=[]; foreach($counts as $row)$existing[$row->level.'|'.$row->skill]=(int)$row->total;
  foreach(ELA_Question_Bank::all() as $q){
   $key=$q[6].'|'.$q[7];
   if(($existing[$key]??0)>=2) continue;
   $wpdb->insert($table,['question'=>$q[0],'option_a'=>$q[1],'option_b'=>$q[2],'option_c'=>$q[3],'option_d'=>$q[4],'correct_answer'=>$q[5],'level'=>$q[6],'skill'=>$q[7],'points'=>1]);
   if(!isset($existing[$key]))$existing[$key]=0;
   $existing[$key]++;
  }
 }
}
