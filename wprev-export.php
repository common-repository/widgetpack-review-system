<?php
@set_time_limit(0);
@ini_set('memory_limit', '256M');

function wprev_export_json($post, $comments=null) {
    global $wpdb;

    if (!$comments) {
        $comments = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_parent = 0 AND comment_agent NOT LIKE 'Wprev/%%'", $post->ID) );
    }

    ob_start();
?>
{
  "chan": "<?php echo $post->ID; ?>",
  "url": "<?php the_permalink_rss() ?>",
  "title": "<?php echo apply_filters('the_title_rss', $post->post_title); ?>",
  "reviews": [
    <?php if ($comments) { foreach ($comments as $key => $c) { if ($key > 0) {?>,<?php } ?>
    {
      "id": <?php echo $c->comment_ID; ?>,
      "ip": "<?php echo $c->comment_author_IP; ?>",
      "star": 5,
      "comment": "<?php echo $c->comment_content; ?>",
      <?php
      if ($c->comment_approved == 1) {
         $status = 1;
      } elseif ($c->comment_approved == 'spam') {
         $status = 3;
      } elseif ($c->comment_approved == 'trash') {
         $status = 2;
      } else {
         $status = 0;
      }
      ?>
      "status": <?php echo $status; ?>,
      "created": <?php echo round(strtotime($c->comment_date) * 1000); ?>,
      "meta": "wp",
      <?php
      if ($c->user_id == 0) {
      ?>
      "name": "<?php echo $c->comment_author; ?>",
      "email": "<?php echo $c->comment_author_email; ?>"
      <?php
      } else {
      $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->users WHERE id = %d", $c->user_id));
      $avatar_tag = get_avatar($user->ID);
      $avatar_data = array();
      preg_match('/(src)=((\'|")[^(\'|")]*(\'|"))/i', $avatar_tag, $avatar_data);
      $avatar = str_replace(array('"', "'"), '', $avatar_data[2]);
      ?>
      "user": {
        "id": <?php echo $user->ID; ?>,
        "name": "<?php echo $user->display_name; ?>",
        "email": "<?php echo $user->user_email; ?>",
        "avatar": "<?php echo $avatar; ?>",
        "www": "<?php echo $user->user_url; ?>"
      }
      <?php } ?>
    }
    <?php }} ?>
  ]
}
<?php
    $json = ob_get_clean();
    return preg_replace('/^\s+|\n|\r|\s+$/m', '', $json);
}

?>
