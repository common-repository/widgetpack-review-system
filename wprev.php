<?php
/*
Plugin Name: WidgetPack Review System
Plugin URI: https://widgetpack.com/review-system
Description: The WidgetPack Review System replaces default WordPress comments with social review service to get more reviews mean more traffic and more sales.
Author: WidgetPack <contact@widgetpack.com>
Version: 1.2
Author URI: https://widgetpack.com
*/

require_once(dirname(__FILE__) . '/api/wprev-wp-api.php');

define('WPAC_VERSION',        '1.2');
define('WPAC_DOMAIN',         'widgetpack.com');
define('WPAC_EMBED_DOMAIN',   'embed.widgetpack.com');
define('WPAC_API_URL',        'http://api.widgetpack.com/1.0/review/');
define('WPAC_API_LIST_SIZE',  100);
define('WPAC_SYNC_TIMEOUT',   30);
define('WPAC_DEBUG',          get_option('wprev_debug'));

function wprev_options() {
    return array(
        '_wprev_sync_lock',
        '_wprev_sync_modif',
        'wprev_site_id',
        'wprev_api_key',
        'wprev_mdata_off',
        'wprev_best_rating',
        'wprev_replace',
        'wprev_active',
        'wprev_ext_js',
        'wprev_sync_off',
        'wprev_disable_ssr',
        'wprev_version',
        'wprev_last_id',
        'wprev_last_modif',
        'wprev_last_modif_offset_id',
        'wprev_last_modif_2',
        'wprev_debug',
    );
}

$wprev_api = new WPacReviewWordPressAPI(get_option('wprev_site_id'), get_option('wprev_api_key'));

/*-------------------------------- Admin --------------------------------*/
function wprev_load_admin_js($hook) {
    if ('comments_page_wprev' != $hook) {
        return;
    }
    $admin_vars = array(
        'indexUrl' => admin_url('index.php'),
        'siteId' => get_option('wprev_site_id'),
    );
    wp_register_script('wprev_admin_script', plugins_url('/static/js/admin.js', __FILE__));
    wp_localize_script('wprev_admin_script', 'adminVars', $admin_vars );
    wp_enqueue_script('wprev_admin_script', plugins_url('/static/js/admin.js', __FILE__), array('jQuery'));
}
add_action('admin_enqueue_scripts', 'wprev_load_admin_js');

/*-------------------------------- Menu --------------------------------*/
function wprev_admin_menu() {
     add_submenu_page(
         'edit-comments.php',
         'WidgetPack Review System',
         'WidgetPack',
         'moderate_comments',
         'wprev',
         'wprev_manage'
     );
}
add_action('admin_menu', 'wprev_admin_menu', 10);

function wprev_manage() {
    if (wprev_does_need_update()) {
        wprev_install();
    }
    include_once(dirname(__FILE__) . '/wprev-manage.php');
}

function wprev_plugin_action_links($links, $file) {
    $plugin_file = basename(__FILE__);
    if (basename($file) == $plugin_file) {
        if (!wprev_is_installed()) {
            $settings_link = '<a href="edit-comments.php?page=wprev">'.wprev_i('Configure').'</a>';
        } else {
            $settings_link = '<a href="edit-comments.php?page=wprev#wprev-plugin">'.wprev_i('Settings').'</a>';
        }
        array_unshift($links, $settings_link);
    }
    return $links;
}
add_filter('plugin_action_links', 'wprev_plugin_action_links', 10, 2);

/*-------------------------------- Database --------------------------------*/
function wprev_install($allow_db_install=true) {
    global $wpdb, $userdata;

    $version = (string)get_option('wprev_version');
    if (!$version) {
        $version = '0';
    }

    if ($allow_db_install) {
        wprev_install_db($version);
    }

    if (version_compare($version, WPAC_VERSION, '=')) {
        return;
    }

    if ($version == '0') {
        add_option('wprev_active', '0');
    } else {
        add_option('wprev_active', '1');
    }
    update_option('wprev_version', WPAC_VERSION);
}

function wprev_install_db($version=0) {
    global $wpdb;
    if (!wprev_is_wpvip()) {
        $wpdb->query($wpdb->prepare("CREATE INDEX wprev_meta_idx ON `".$wpdb->prefix."commentmeta` (meta_key, meta_value(11));"));
    }
}
function wprev_reset_db($version=0) {
    global $wpdb;
    if (!wprev_is_wpvip()) {
        $wpdb->query($wpdb->prepare("DROP INDEX wprev_meta_idx ON `".$wpdb->prefix."commentmeta`;"));
    }
}
function wprev_is_wpvip() {
    return defined('WPCOM_IS_VIP_ENV') && WPCOM_IS_VIP_ENV;
}

/*-------------------------------- Default --------------------------------*/
function wprev_pre_comment_on_post($comment_post_ID) {
    if (wprev_can_replace()) {
        wp_die(wprev_i('Sorry, the built-in commenting system is disabled because WidgetPack Reviews is active.') );
    }
    return $comment_post_ID;
}
add_action('pre_comment_on_post', 'wprev_pre_comment_on_post');

/*-------------------------------- WidgetPack --------------------------------*/
function wprev_output_count_js() {
    if (get_option('wprev_ext_js') == '1') {
        $widget_vars = array(
            'host' => WPAC_EMBED_DOMAIN,
            'id' => get_option('wprev_site_id'),
            'chan' => $post->ID,
        );
        wp_register_script('wprev_count_script', plugins_url('/static/js/count.js', __FILE__));
        wp_localize_script('wprev_count_script', 'countVars', $count_vars);
        wp_enqueue_script('wprev_count_script', plugins_url('/static/js/count.js', __FILE__));
    }
    else {
        ?>
        <script type="text/javascript">
        // <![CDATA[
        (function () {
            var nodes = document.getElementsByTagName('span');
            for (var i = 0, url; i < nodes.length; i++) {
                if (nodes[i].className.indexOf('wprev-postid') != -1) {
                    nodes[i].parentNode.setAttribute('data-wpac-chan', nodes[i].getAttribute('data-wpac-chan'));
                    url = nodes[i].parentNode.href.split('#', 1);
                    if (url.length == 1) { url = url[0]; }
                    else { url = url[1]; }
                    nodes[i].parentNode.href = url + '#wpac-review';
                }
            }
            wpac_init = window.wpac_init || [];
            wpac_init.push({widget: 'ReviewCount', id: <?php echo get_option('wprev_site_id'); ?>});
            (function() {
                if ('WIDGETPACK_LOADED' in window) return;
                WIDGETPACK_LOADED = true;
                var mc = document.createElement('script');
                mc.type = 'text/javascript';
                mc.async = true;
                mc.src = 'https://<?php echo WPAC_EMBED_DOMAIN; ?>/widget.js';
                var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(mc, s.nextSibling);
            })();
        }());
        // ]]>
        </script>
        <?php
    }
}

function wprev_output_footer_comment_js() {
    if (!wprev_can_replace()) {
        return;
    }
    wprev_output_count_js();
}
add_action('wp_footer', 'wprev_output_footer_comment_js');

$EMBED = false;
function wprev_comments_template($value) {
    global $EMBED;
    global $post;
    global $comments;

    if (!(is_singular() && (have_comments() || 'open' == $post->comment_status))) {
        return;
    }

    if (!wprev_is_installed() || !wprev_can_replace() ) {
        return $value;
    }

    $EMBED = true;
    return dirname(__FILE__) . '/wprev-comments.php';
}

function wprev_comments_text($comment_text) {
    global $post;

    if (wprev_can_replace()) {
        return '<span class="wprev-postid" data-wpac-chan="'.esc_attr(wprev_chan_id($post)).'">'.$comment_text.'</span>';
    } else {
        return $comment_text;
    }
}

function wprev_comments_number($count) {
    global $post;
    return $count;
}

add_filter('comments_template', 'wprev_comments_template');
add_filter('comments_number', 'wprev_comments_text');
add_filter('get_comments_number', 'wprev_comments_number');

function wprev_comment($comment, $args, $depth) {
    $GLOBALS['comment'] = $comment;
    switch ($comment->comment_type):
        case '' :
    ?>

    <?php if (!get_option('wprev_mdata_off') && preg_match('/^Wprev\/.+/', $comment->comment_agent) && $comment->comment_karma > 0) { ?>

    <li <?php comment_class(); ?> id="wprev-review-<?php echo comment_ID(); ?>" itemprop="review" itemscope itemtype="http://schema.org/Review">
        <span itemprop="reviewRating" itemscope itemtype="http://schema.org/Rating">
            <span itemprop="ratingValue"><?php echo $comment->comment_karma; ?></span>
        </span>
        <div id="wprev-review-header-<?php echo comment_ID(); ?>" class="wprev-review-header">
            <cite id="wprev-cite-<?php echo comment_ID(); ?>" itemprop="author" itemscope itemtype="http://schema.org/Person">
                <?php if(comment_author_url()) : ?>
                <a id="wprev-author-user-<?php echo comment_ID(); ?>" href="<?php echo comment_author_url(); ?>" target="_blank" rel="nofollow" itemprop="name"><?php echo comment_author(); ?></a>
                <?php else : ?>
                <span id="wprev-author-user-<?php echo comment_ID(); ?>" itemprop="name"><?php echo comment_author(); ?></span>
                <?php endif; ?>
            </cite>
        </div>
        <div id="wprev-review-body-<?php echo comment_ID(); ?>" class="wprev-review-body">
            <div id="wprev-review-message-<?php echo comment_ID(); ?>" class="wprev-review-message" itemprop="reviewBody"><?php echo wp_filter_kses(comment_text()); ?></div>
        </div>
        <meta itemprop="datePublished" content="<?php echo comment_time('Y-m-d\TH:i:s'); ?>">
    </li>

    <?php } else { ?>

    <li <?php comment_class(); ?> id="wprev-comment-<?php echo comment_ID(); ?>" itemtype="http://schema.org/Comment" itemscope="itemscope">
        <div id="wprev-comment-header-<?php echo comment_ID(); ?>" class="wprev-comment-header">
            <cite id="wprev-cite-<?php echo comment_ID(); ?>">
                <?php if(comment_author_url()) : ?>
                <a id="wprev-author-user-<?php echo comment_ID(); ?>" href="<?php echo comment_author_url(); ?>" target="_blank" rel="nofollow" itemprop="author"><?php echo comment_author(); ?></a>
                <?php else : ?>
                <span id="wprev-author-user-<?php echo comment_ID(); ?>" itemprop="author"><?php echo comment_author(); ?></span>
                <?php endif; ?>
            </cite>
        </div>
        <div id="wprev-comment-body-<?php echo comment_ID(); ?>" class="wprev-comment-body">
            <div id="wprev-comment-message-<?php echo comment_ID(); ?>" class="wprev-comment-message" itemprop="text"><?php echo wp_filter_kses(comment_text()); ?></div>
        </div>
        <meta itemprop="dateCreated" content="<?php echo comment_time('Y-m-d\TH:i:s'); ?>">
    </li>

    <?php } ?>

    <?php
        break;
        case 'pingback'  :
        case 'trackback' :
    ?>
    <li class="post pingback">
        <p><?php echo wprev_i('Pingback:'); ?> <?php comment_author_link(); ?>(<?php edit_comment_link(wprev_i('Edit'), ' '); ?>)</p>
    </li>
    <?php
        break;
    endswitch;
}

function wprev_comments_open($open, $post_id=null) {
    global $EMBED;
    if ($EMBED) return false;
    return $open;
}
add_filter('comments_open', 'wprev_comments_open');

/*-------------------------------- Channel --------------------------------*/
function wprev_chan_id($post) {
    return $post->ID;
}

function wprev_chan_url($post) {
    return get_permalink($post);
}

function wprev_chan_title($post) {
    $title = get_the_title($post);
    $title = strip_tags($title);
    return $title;
}

/*-------------------------------- Request --------------------------------*/
function wprev_request_handler() {
    global $post;
    global $wpdb;
    global $wprev_api;

    if (!empty($_GET['cf_action'])) {
        switch ($_GET['cf_action']) {
            case 'wprev_sync':
                if(!($post_id = $_GET['post_id'])) {
                    header("HTTP/1.0 400 Bad Request");
                    die();
                }
                // sync schedule after 5 minutes
                $ts = time() + 300;
                $sync_modif = get_option('_wprev_sync_modif');
                if ($sync_modif == '1') {
                    wp_schedule_single_event($ts, 'wprev_sync_modif');
                    die('// wprev_sync_modif scheduled');
                } else {
                    wp_schedule_single_event($ts, 'wprev_sync');
                    die('// wprev_sync scheduled');
                }
            break;
            case 'wprev_export':
                if (current_user_can('manage_options')) {
                    $msg = '';
                    $result = '';
                    $response = null;

                    $timestamp = intval($_GET['timestamp']);
                    $post_id = intval($_GET['post_id']);
                    if ( isset($_GET['_wprevexport_wpnonce']) === false ) {
                        $msg = wprev_i('Unable to export reviews. Make sure you are accessing this page from the Wordpress dashboard.');
                        $result = 'fail';
                    } else {
                        check_admin_referer('wprev-wpnonce_wprev_export', '_wprevexport_wpnonce');

                        $post = $wpdb->get_results($wpdb->prepare("
                            SELECT *
                            FROM $wpdb->posts
                            WHERE post_type != 'revision'
                            AND post_status = 'publish'
                            AND comment_count > 0
                            AND ID > %d
                            ORDER BY ID ASC
                            LIMIT 1
                        ", $post_id));
                        $post = $post[0];
                        $post_id = $post->ID;
                        $max_post_id = $wpdb->get_var($wpdb->prepare("
                            SELECT MAX(Id)
                            FROM $wpdb->posts
                            WHERE post_type != 'revision'
                            AND post_status = 'publish'
                            AND comment_count > 0
                        "));
                        $eof = (int)($post_id == $max_post_id);
                        if ($eof) {
                            $status = 'complete';
                            $msg = wprev_i('Your comments have been sent to WidgetPack and queued for import!');
                        }
                        else {
                            $status = 'partial';
                            $msg = wprev_i('Processed comments on post') . ' #'. $post_id . '&hellip;';
                        }
                        $result = 'fail';
                        if ($post) {
                            require_once(dirname(__FILE__) . '/wprev-export.php');
                            $json = wprev_export_json($post);
                        }
                    }
                    $site_id = get_option('wprev_site_id');
                    $wprev_api_key = get_option('wprev_api_key');
                    $signature = md5('site_id='.$site_id.$json.$wprev_api_key);
                    $response = compact('timestamp', 'status', 'post_id', 'site_id', 'json', 'signature');
                    header('Content-type: text/javascript');
                    echo cf_json_encode($response);
                    die();
                }
            break;
            case 'wprev_import':
                if (current_user_can('manage_options')) {
                    $msg = '';
                    $result = '';
                    $response = null;

                    if (isset($_GET['_wprevimport_wpnonce']) === false) {
                        $msg = wprev_i('Unable to import reviews. Make sure you are accessing this page from the Wordpress dashboard.');
                        $result = 'fail';
                    } else {
                        check_admin_referer('wprev-wpnonce_wprev_import', '_wprevimport_wpnonce');

                        if (!isset($_GET['last_id'])) $last_id = false;
                        else $last_id = $_GET['last_id'];

                        if ($_GET['wipe'] == '1') {
                            $wpdb->query($wpdb->prepare("DELETE FROM `".$wpdb->prefix."commentmeta` WHERE meta_key IN ('wprev_id')"));
                            $wpdb->query($wpdb->prepare("DELETE FROM `".$wpdb->prefix."comments` WHERE comment_agent LIKE 'Wprev/%%'"));
                        }

                        ob_start();
                        $response = wprev_sync($last_id, true);
                        $debug = ob_get_clean();
                        if (!$response) {
                            $status = 'error';
                            $result = 'fail';
                            $error = $wprev_api->get_last_error();
                            $msg = '<p class="status wprev-export-fail">'.wprev_i('There was an error downloading your reviews from WidgetPack.').'<br/>'.esc_attr($error).'</p>';
                        } else {
                            list($reviews, $last_id) = $response;
                            if (!$reviews) {
                                $status = 'complete';
                                $msg = wprev_i('Your reviews have been downloaded from WidgetPack and saved in your local database.');
                            } else {
                                $status = 'partial';
                                $msg = wprev_i('Import in progress (last review id: %s)', $last_id) . ' &hellip;';
                            }
                            $result = 'success';
                        }
                        $debug = explode("\n", $debug);
                        $response = compact('result', 'status', 'reviews', 'msg', 'last_id', 'debug');
                        header('Content-type: text/javascript');
                        echo cf_json_encode($response);
                        die();
                    }
                }
            break;
        }
    }
}
add_action('init', 'wprev_request_handler');

/*-------------------------------- Sync --------------------------------*/
function wprev_sync($last_id=false, $force=false) {
    global $wpdb;
    global $wprev_api;

    set_time_limit(WPAC_SYNC_TIMEOUT);

    if ($force) {
        $sync_time = null;
    } else {
        $sync_time = (int)get_option('_wprev_sync_lock');
    }

    // lock sync for 1 hour if previous sync isn't done
    if ($sync_time && $sync_time > time() - 60*60) {
        return false;
    } else {
        update_option('_wprev_sync_lock', time());
    }

    // init last_id as offset id cursor
    if ($last_id === false) {
        $last_id = get_option('wprev_last_id');
        if (!$last_id) {
            $last_id = 0;
        }
    }

    // init last_modif as offset modified cursor - do it here that's don't lose edited comments for sync period
    $last_modif = get_option('wprev_last_modif');
    if (!$last_modif) {
        update_option('wprev_last_modif', round(microtime(true) * 1000));
    }

    // Get comments from API
    $wprev_res = $wprev_api->review_list($last_id);
    if($wprev_res < 0 || $wprev_res === false) {
        update_option('_wprev_sync_modif', '1');
        return false;
    }
    // Sync comments with database.
    wprev_sync_comments($wprev_res);

    $total = 0;
    if ($wprev_res) {
        foreach ($wprev_res as $comment) {
            $total += 1;
            if ($comment->id > $last_id) $last_id = $comment->id;
        }
        if ($last_id > get_option('wprev_last_id')) {
            update_option('wprev_last_id', $last_id);
        }
    }
    unset($comment);

    if ($total < WPAC_API_LIST_SIZE) {
        // If get few comments to switch sync modif (edited)
        update_option('_wprev_sync_modif', '1');
    } else {
        // If get a lot of comments continue to sync (new)
        delete_option('_wprev_sync_modif');
    }

    delete_option('_wprev_sync_lock');
    return array($total, $last_id);
}
add_action('wprev_sync', 'wprev_sync');

function wprev_sync_comments($comments) {
    if (count($comments) < 1) {
        return;
    }

    global $wpdb;

    // user MUST be logged out during this process
    wp_set_current_user(0);

    foreach ($comments as $comment) {
        $results = $wpdb->get_results($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'wprev_id' AND meta_value = %s LIMIT 1", $comment->id));
        if (count($results)) {
            if (count($results) > 1) {
                $results = array_slice($results, 1);
                foreach ($results as $result) {
                    $wpdb->prepare("DELETE FROM $wpdb->commentmeta WHERE comment_id = %s LIMIT 1", $result);
                }
            }
            continue;
        }

        $commentdata = false;

        if (isset($comment->meta)) {
            $comment_meta = is_array($comment->meta) ? $comment->meta : array($comment->meta);
            foreach ($comment_meta as $meta) {
                if ($meta->meta_key == 'wp-id') {
                    $commentdata = $wpdb->get_row($wpdb->prepare("SELECT comment_ID FROM $wpdb->comments WHERE comment_ID = %s LIMIT 1", $meta->meta_value), ARRAY_A);
                }
            }
        }

        if (!$commentdata) {
            if ($comment->status == 1) {
                $status = 1;
            } elseif ($comment->status == 3) {
                $status = 'spam';
            } else {
                $status = 0;
            }
            $unix_time = intval($comment->created) / 1000;
            $commentdata = array(
                'comment_post_ID' => $comment->site_chan->chan,
                'comment_date' => date('Y-m-d\TH:i:s', $unix_time + (get_option('gmt_offset') * 3600)),
                'comment_date_gmt' => date('Y-m-d\TH:i:s', $unix_time),
                'comment_karma' => $comment->star,
                'comment_content' => apply_filters('pre_comment_content', $comment->comment),
                'comment_approved' => $status,
                'comment_agent' => 'Wprev/1.0('.WPAC_VERSION.'):'.intval($comment->id),
                'comment_type' => '',
                'comment_author_IP' => $comment->ip
            );
            if ($comment->user) {
                $commentdata['comment_author'] = $comment->user->name;
                $commentdata['comment_author_email'] = $comment->user->email;
                $commentdata['comment_author_url'] = $comment->user->www;
            } else {
                $commentdata['comment_author'] = $comment->name;
                $commentdata['comment_author_email'] = $comment->email;
            }
            $commentdata = wp_filter_comment($commentdata);

            // test again for comment exist
            if ($wpdb->get_row($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'wprev_id' AND meta_value = %s LIMIT 1", $comment->id))) {
                continue;
            }

            $commentdata['comment_ID'] = wp_insert_comment($commentdata);
        }
        $comment_id = $commentdata['comment_ID'];
        update_comment_meta($comment_id, 'wprev_id', $comment->id);
    }
    unset($comment);
}

/*-------------------------------- Sync modif --------------------------------*/
function wprev_sync_modif() {
    global $wpdb;
    global $wprev_api;

    set_time_limit(WPAC_SYNC_TIMEOUT);

    $sync_time = (int)get_option('_wprev_sync_lock');

    // lock sync for 1 hour if previous sync isn't done
    if ($sync_time && $sync_time > time() - 60*60) {
        return false;
    } else {
        update_option('_wprev_sync_lock', time());
    }

    $last_modif = get_option('wprev_last_modif');
    if (!$last_modif) {
        $last_modif = 0;
    }

    $last_modif_offset_id = get_option('wprev_last_modif_offset_id');
    if ($last_modif_offset_id) {
        $wprev_res = $wprev_api->review_list_modif($last_modif, $last_modif_offset_id);
    } else {
        $wprev_res = $wprev_api->review_list_modif($last_modif);
        $last_modif_offset_id = 0;
    }

    if($wprev_res < 0 || $wprev_res === false) {
        return false;
    }
    // Sync comments with database.
    wprev_sync_comments_modif($wprev_res);

    $total = 0;
    if ($wprev_res) {
        foreach ($wprev_res as $comment) {
            $total += 1;
            if ($comment->modif > $last_modif) $last_modif = $comment->modif;
            if ($comment->id > $last_modif_offset_id) $last_modif_offset_id = $comment->id;
        }
        unset($comment);
        if ($total < WPAC_API_LIST_SIZE) {
            if ($last_modif > get_option('wprev_last_modif')) {
                update_option('wprev_last_modif', $last_modif);
            }
        } else {
            update_option('wprev_last_modif_2', $last_modif);
            update_option('wprev_last_modif_offset_id', $last_modif_offset_id);
        }
    }

    if ($total == 0) {
        $last_modif_2 = get_option('wprev_last_modif_2');
        if ($last_modif_2 > get_option('wprev_last_modif')) {
            update_option('wprev_last_modif', $last_modif_2);
        }
        delete_option('wprev_last_modif_offset_id');
    }

    delete_option('_wprev_sync_modif');
    delete_option('_wprev_sync_lock');
    return true;
}
add_action('wprev_sync_modif', 'wprev_sync_modif');

function wprev_sync_comments_modif($comments) {
    if (count($comments) < 1) {
        return;
    }

    global $wpdb;

    // user MUST be logged out during this process
    wp_set_current_user(0);

    foreach ($comments as $comment) {
        $results = $wpdb->get_results($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'wprev_id' AND meta_value = %s LIMIT 1", $comment->id));
        if (count($results)) {
            if (count($results) > 1) {
                $results = array_slice($results, 1);
                foreach ($results as $result) {
                    $wpdb->prepare("DELETE FROM $wpdb->commentmeta WHERE comment_id = %s LIMIT 1", $result);
                }
            }
        }

        $wp_comment_id = $wpdb->get_var($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'wprev_id' AND meta_value = %s LIMIT 1", $comment->id));
        if ($wp_comment_id) {
            if ($comment->status == 1) {
                $status = 1;
            } elseif ($comment->status == 3) {
                $status = 'spam';
            } else {
                $status = 0;
            }
            $unix_time = intval($comment->created) / 1000;
            $commentdata = array(
                'comment_ID' => $wp_comment_id,
                'comment_post_ID' => $comment->site_chan->chan,
                'comment_karma' => $comment->star,
                'comment_content' => apply_filters('pre_comment_content', $comment->comment),
                'comment_approved' => $status,
                'comment_author_IP' => $comment->ip
            );
            if ($comment->user) {
                $commentdata['comment_author'] = $comment->user->name;
                $commentdata['comment_author_email'] = $comment->user->email;
                $commentdata['comment_author_url'] = $comment->user->www;
            } else {
                $commentdata['comment_author'] = $comment->name;
                $commentdata['comment_author_email'] = $comment->email;
            }
            $commentdata = wp_filter_comment($commentdata);
            wp_update_comment($commentdata);
            update_comment_meta($wp_comment_id, 'wprev_id', $comment->id);
        } else {
            wprev_sync_comments(array($comment));
        }
    }
    unset($comment);
}

/*-------------------------------- Helpers --------------------------------*/
function wprev_is_installed() {
    $wprev_site_id = get_option('wprev_site_id');
    $wprev_api_key = get_option('wprev_api_key');
    if (is_numeric($wprev_site_id) > 0 && strlen($wprev_api_key) > 0) {
        return true;
    } else {
        return false;
    }
}

function wprev_does_need_update() {
    $version = (string)get_option('wprev_version');
    if (empty($version)) {
        $version = '0';
    }
    if (version_compare($version, '1.0', '<')) {
        return true;
    }
    return false;
}

function wprev_can_replace() {
    global $id, $post;

    if (get_option('wprev_active') === '0'){ return false; }

    $replace = get_option('wprev_replace');

    if (is_feed())                         { return false; }
    if (!isset($post))                     { return false; }
    if ('draft' == $post->post_status)     { return false; }
    if (!get_option('wprev_site_id'))      { return false; }
    else if ('all' == $replace)            { return true; }

    if (!isset($post->comment_count)) {
        $num_comments = 0;
    } else {
        if ('empty' == $replace) {
            if ( $post->comment_count > 0 ) {
                $comments = get_approved_comments($post->ID);
                foreach ($comments as $comment) {
                    if ($comment->comment_type != 'trackback' && $comment->comment_type != 'pingback') {
                        $num_comments++;
                    }
                }
            } else {
                $num_comments = 0;
            }
        } else {
            $num_comments = $post->comment_count;
        }
    }
    return (('empty' == $replace && 0 == $num_comments) || ('closed' == $replace && 'closed' == $post->comment_status));
}

function wprev_i($text, $params=null) {
    if (!is_array($params)) {
        $params = func_get_args();
        $params = array_slice($params, 1);
    }
    return vsprintf(__($text, 'wprev'), $params);
}

if (!function_exists('esc_html')) {
function esc_html( $text ) {
    $safe_text = wp_check_invalid_utf8( $text );
    $safe_text = _wp_specialchars( $safe_text, ENT_QUOTES );
    return apply_filters( 'esc_html', $safe_text, $text );
}
}

if (!function_exists('esc_attr')) {
function esc_attr( $text ) {
    $safe_text = wp_check_invalid_utf8( $text );
    $safe_text = _wp_specialchars( $safe_text, ENT_QUOTES );
    return apply_filters( 'attribute_escape', $safe_text, $text );
}
}

/**
 * JSON ENCODE for PHP < 5.2.0
 * Checks if json_encode is not available and defines json_encode
 * to use php_json_encode in its stead
 * Works on iteratable objects as well - stdClass is iteratable, so all WP objects are gonna be iteratable
 */
if(!function_exists('cf_json_encode')) {
    function cf_json_encode($data) {

        // json_encode is sending an application/x-javascript header on Joyent servers
        // for some unknown reason.
        return cfjson_encode($data);
    }

    function cfjson_encode_string($str) {
        if(is_bool($str)) {
            return $str ? 'true' : 'false';
        }

        return str_replace(
            array(
                '"'
                , '/'
                , "\n"
                , "\r"
            )
            , array(
                '\"'
                , '\/'
                , '\n'
                , '\r'
            )
            , $str
        );
    }

    function cfjson_encode($arr) {
        $json_str = '';
        if (is_array($arr)) {
            $pure_array = true;
            $array_length = count($arr);
            for ( $i = 0; $i < $array_length ; $i++) {
                if (!isset($arr[$i])) {
                    $pure_array = false;
                    break;
                }
            }
            if ($pure_array) {
                $json_str = '[';
                $temp = array();
                for ($i=0; $i < $array_length; $i++) {
                    $temp[] = sprintf("%s", cfjson_encode($arr[$i]));
                }
                $json_str .= implode(',', $temp);
                $json_str .="]";
            }
            else {
                $json_str = '{';
                $temp = array();
                foreach ($arr as $key => $value) {
                    $temp[] = sprintf("\"%s\":%s", $key, cfjson_encode($value));
                }
                $json_str .= implode(',', $temp);
                $json_str .= '}';
            }
        }
        else if (is_object($arr)) {
            $json_str = '{';
            $temp = array();
            foreach ($arr as $k => $v) {
                $temp[] = '"'.$k.'":'.cfjson_encode($v);
            }
            $json_str .= implode(',', $temp);
            $json_str .= '}';
        }
        else if (is_string($arr)) {
            $json_str = '"'. cfjson_encode_string($arr) . '"';
        }
        else if (is_numeric($arr)) {
            $json_str = $arr;
        }
        else if (is_bool($arr)) {
            $json_str = $arr ? 'true' : 'false';
        }
        else {
            $json_str = '"'. cfjson_encode_string($arr) . '"';
        }
        return $json_str;
    }
}
?>