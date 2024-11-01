<?php
if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field() {}
}

global $wprev_api;

require(ABSPATH . 'wp-includes/version.php');

if (!current_user_can('moderate_comments')) {
    die('The account you\'re logged in to doesn\'t have permission to access this page.');
}

function wprev_has_valid_nonce() {
    $nonce_actions = array('wprev_upgrade', 'wprev_reset', 'wprev_install', 'wprev_settings', 'wprev_active');
    $nonce_form_prefix = 'wprev-form_nonce_';
    $nonce_action_prefix = 'wprev-wpnonce_';
    foreach ($nonce_actions as $key => $value) {
        if (isset($_POST[$nonce_form_prefix.$value])) {
            check_admin_referer($nonce_action_prefix.$value, $nonce_form_prefix.$value);
            return true;
        }
    }
    return false;
}

if (!empty($_POST)) {
    $nonce_result_check = wprev_has_valid_nonce();
    if ($nonce_result_check === false) {
        die('Unable to save changes. Make sure you are accessing this page from the Wordpress dashboard.');
    }
}

// Reset
if (isset($_POST['reset'])) {
    foreach (wprev_options() as $opt) {
        delete_option($opt);
    }
    unset($_POST);
    wprev_reset_db();
?>
<div class="wrap">
    <h3><?php echo wprev_i('WidgetPack Reset'); ?></h3>
    <form method="POST" action="?page=wprev">
        <?php wp_nonce_field('wprev-wpnonce_wprev_reset', 'wprev-form_nonce_wprev_reset'); ?>
        <p><?php echo wprev_i('WidgetPack has been reset successfully.') ?></p>
        <ul style="list-style: circle;padding-left:20px;">
            <li><?php echo wprev_i('Local settings for the plugin were removed.') ?></li>
            <li><?php echo wprev_i('Database changes by WidgetPack were reverted.') ?></li>
        </ul>
        <p>
            <?php echo wprev_i('If you wish to reinstall, you can do that now.') ?>
            <a href="?page=wprev">&nbsp;<?php echo wprev_i('Reinstall') ?></a>
        </p>
    </form>
</div>
<?php
die();
}

// Post fields that require verification.
$valid_fields = array(
    'wpac_site_data' => array(
        'key_name' => 'wpac_site_data',
        'regexp' => '/^\d+:[0-9a-zA-Z]+$/'
    ),
    'wprev_site_id' => array(
        'key_name' => 'wprev_site_id',
        'type' => 'int'
    ),
    'wprev_api_key' => array(
        'key_name' => 'wprev_api_key',
        'regexp' => '/^[0-9a-zA-Z]{64,64}+$/'
    ),
    'wprev_mdata_off' => array(
        'key_name' => 'wprev_mdata_off',
        'values' => array('on')
    ),
    'wprev_replace' => array(
        'key_name' => 'wprev_replace',
        'values' => array('all', 'closed')
    ),
    'wprev_best_rating' => array(
        'key_name' => 'wprev_best_rating',
        'type' => 'int'
    ),
    'wprev_active' => array(
        'key_name' => 'wprev_active',
        'values' => array('Disable', 'Enable')
    ),
    'wprev_ext_js' => array(
        'key_name' => 'wprev_ext_js',
        'values' => array('on')
    ),
    'wprev_sync_off' => array(
        'key_name' => 'wprev_sync_off',
        'values' => array('on')
    ),
    'wprev_disable_ssr' => array(
        'key_name' => 'wprev_disable_ssr',
        'values' => array('on')
    ),
    'wprev_debug' => array(
        'key_name' => 'wprev_debug',
        'values' => array('on')
    ));

// Check POST fields and remove bad input.
foreach ($valid_fields as $key) {

    if (isset($_POST[$key['key_name']]) ) {

        // SANITIZE first
        $_POST[$key['key_name']] = trim(sanitize_text_field($_POST[$key['key_name']]));

        // Validate
        if ($key['regexp']) {
            if (!preg_match($key['regexp'], $_POST[$key['key_name']])) {
                unset($_POST[$key['key_name']]);
            }

        } else if ($key['type'] == 'int') {
            if (!intval($_POST[$key['key_name']])) {
                unset($_POST[$key['key_name']]);
            }

        } else {
            $valid = false;
            $vals = $key['values'];
            foreach ($vals as $val) {
                if ($_POST[$key['key_name']] == $val) {
                    $valid = true;
                }
            }
            if (!$valid) {
                unset($_POST[$key['key_name']]);
            }
        }
    }
}

if (isset($_POST['wprev_site_id']) && isset($_POST['wprev_replace']) ) {
    update_option('wprev_mdata_off', isset($_POST['wprev_mdata_off']));
    update_option('wprev_best_rating', $_POST['wprev_best_rating']);
    update_option('wprev_replace', isset($_POST['wprev_replace']) ? esc_attr( $_POST['wprev_replace'] ) : 'all');
    update_option('wprev_sync_off', isset($_POST['wprev_sync_off']));
    update_option('wprev_disable_ssr', isset($_POST['wprev_disable_ssr']));
    update_option('wprev_debug', isset($_POST['wprev_debug']));
}

if (isset($_POST['wprev_active']) && isset($_GET['wprev_active'])) {
    update_option('wprev_active', ($_GET['wprev_active'] == '1' ? '1' : '0'));
}

if (isset($_POST['wprev_install']) && isset($_POST['wpac_site_data'])) {
    list($wprev_site_id, $wprev_api_key) = explode(':', $_POST['wpac_site_data']);
    update_option('wprev_site_id', $wprev_site_id);
    update_option('wprev_api_key', $wprev_api_key);
    update_option('wprev_replace', 'all');
    update_option('wprev_active', '1');
    update_option('wprev_ext_js', '0');
}

wp_enqueue_script('jquery');
wp_enqueue_script('jquery-ui-draggable');
wp_register_script('wprev_bootstrap_js', plugins_url('/static/js/bootstrap.min.js', __FILE__));
wp_enqueue_script('wprev_bootstrap_js', plugins_url('/static/js/bootstrap.min.js', __FILE__));

wp_register_style('wprev_bootstrap_css', plugins_url('/static/css/bootstrap.min.css', __FILE__));
wp_enqueue_style('wprev_bootstrap_css', plugins_url('/static/css/bootstrap.min.css', __FILE__));
wp_register_style('wprev_wpac_admin_css', plugins_url('/static/css/wpac-admin.css', __FILE__));
wp_enqueue_style('wprev_wpac_admin_css', plugins_url('/static/css/wpac-admin.css', __FILE__));
wp_register_style('wprev_admin_css', plugins_url('/static/css/admin.css', __FILE__));
wp_enqueue_style('wprev_admin_css', plugins_url('/static/css/admin.css', __FILE__));
?>

<?php if (!wprev_is_installed()) { ?>
<form method="POST" action="#wprev-plugin">
    <?php wp_nonce_field('wprev-wpnonce_wprev_install', 'wprev-form_nonce_wprev_install'); ?>
    <input type="hidden" name="wprev_install"/>
    <div id="wpac-setup"></div>
</form>
<script type="text/javascript">
    wpac_init = window.wpac_init || [];
    wpac_init.push({widget: 'Setup'});
    (function() {
        var mc = document.createElement('script');
        mc.type = 'text/javascript';
        mc.async = true;
        mc.src = 'https://<?php echo WPAC_EMBED_DOMAIN; ?>/widget.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(mc, s.nextSibling);
    })();
</script>
<?php
} else {
?>
<div class="wrap" id="wprev-wrap">
    <ul class="nav nav-tabs nav-justified">
        <li class="active">
            <a href="#/site/<?php echo get_option('wprev_site_id'); ?>/menu/review/submenu/moderation" class="wprev-tab">
            <?php echo wprev_i('Moderate'); ?>
            </a>
        </li>
        <li>
            <a href="#/site/<?php echo get_option('wprev_site_id'); ?>/menu/review/submenu/setting" class="wprev-tab">
            <?php echo wprev_i('Widget Settings'); ?>
            </a>
        </li>
        <li>
            <a href="#/site/<?php echo get_option('wprev_site_id'); ?>/menu/site/submenu/setting" class="wprev-tab">
            <?php echo wprev_i('Site Settings'); ?>
            </a>
        </li>
        <li>
            <a href="#/site/<?php echo get_option('wprev_site_id'); ?>/menu/site/submenu/admin" class="wprev-tab">
            <?php echo wprev_i('Moderators'); ?>
            </a>
        </li>
        <li>
            <a href="#/site/<?php echo get_option('wprev_site_id'); ?>/menu/site/submenu/stopword" class="wprev-tab">
            <?php echo wprev_i('Words Filter'); ?>
            </a>
        </li>
        <li>
            <a href="#/site/<?php echo get_option('wprev_site_id'); ?>/menu/site/submenu/ban" class="wprev-tab">
            <?php echo wprev_i('Banned'); ?>
            </a>
        </li>
        <li>
            <a href="#wprev-plugin" class="wprev-tab">
            <?php echo wprev_i('Plugin Settings'); ?>
            </a>
        </li>
    </ul>
    <br>
    <div class="tab-content">
        <div role="tabpanel" class="tab-pane active" id="wprev-main">
            <div id="wpac-admin"></div>
            <script type="text/javascript">
                wpac_init = window.wpac_init || [];
                wpac_init.push({widget: 'Admin', popup: 'https://<?php echo WPAC_DOMAIN; ?>/login', id: <?php echo get_option('wprev_site_id'); ?>});
                (function() {
                    var mc = document.createElement('script');
                    mc.type = 'text/javascript';
                    mc.async = true;
                    mc.src = 'https://<?php echo WPAC_EMBED_DOMAIN; ?>/widget.js';
                    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(mc, s.nextSibling);
                })();
            </script>
        </div>
        <div role="tabpanel" class="tab-pane" id="wprev-plugin-pane">
            <?php
                $wprev_site_id = get_option('wprev_site_id');
                $wprev_mdata_off = get_option('wprev_mdata_off');
                $wprev_best_rating = get_option('wprev_best_rating');
                $wprev_replace = get_option('wprev_replace');
                $wprev_sync_off = get_option('wprev_sync_off');
                $wprev_disable_ssr = get_option('wprev_disable_ssr');
                $wprev_debug = get_option('wprev_debug');
                $wprev_enabled = get_option('wprev_active') == '1';
                $wprev_enabled_state = $wprev_enabled ? 'enabled' : 'disabled';
            ?>
            <!-- Settings -->
            <h3><?php echo wprev_i('Settings'); ?></h3>
            <p><?php echo wprev_i('Version: %s', esc_html(WPAC_VERSION)); ?></p>

            <!-- Enable/disable WidgetPack comment toggle -->
            <form method="POST" action="?page=wprev&amp;wprev_active=<?php echo (string)((int)($wprev_enabled != true)); ?>#wprev-plugin">
                <?php wp_nonce_field('wprev-wpnonce_wprev_active', 'wprev-form_nonce_wprev_active'); ?>
                <p class="status">
                    <?php echo wprev_i('WidgetPack reviews are currently '); ?>
                    <span class="wprev-<?php echo esc_attr($wprev_enabled_state); ?>-text"><b><?php echo $wprev_enabled_state; ?></b></span>
                </p>
                <input type="submit" name="wprev_active" class="button" value="<?php echo $wprev_enabled ? wprev_i('Disable') : wprev_i('Enable'); ?>" />
            </form>

            <!-- Configuration form -->
            <form method="POST" enctype="multipart/form-data">
            <?php wp_nonce_field('wprev-wpnonce_wprev_settings', 'wprev-form_nonce_wprev_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row" valign="top"><?php echo '<h3>' . wprev_i('General') . '</h3>'; ?></th>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wprev_i('Site ID'); ?></th>
                    <td>
                        <input type="hidden" name="wprev_site_id" value="<?php echo esc_attr($wprev_site_id); ?>"/>
                        <code><?php echo esc_attr($wprev_site_id); ?></code>
                        <br>
                        <?php echo wprev_i('This is the unique identifier for your website in WidgetPack, automatically set during installation.'); ?>
                        <br>
                        <?php echo wprev_i('Please include it to email when your request to support team at contact@widgetpack.com.'); ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row" valign="top" colspan="2"><?php echo '<h3>' . wprev_i('Google Rich Snippets for Reviews') . '</h3>'; ?></th>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wprev_i('Disable'); ?></th>
                    <td>
                        <input type="checkbox" id="wprev_mdata_off" name="wprev_mdata_off" <?php if($wprev_mdata_off) {echo 'checked="checked"';}?> >
                        <label for="wprev_mdata_off"><?php echo wprev_i('Disable Google Rich Snippets for Reviews'); ?></label>
                        <br><?php echo wprev_i('If you do not want to show rating snippets just off it.'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wprev_i('Best Rating'); ?></th>
                    <td>
                        <input type="text" id="wprev_best_rating" name="wprev_best_rating" value="<?php if ($wprev_best_rating > 0) { echo $wprev_best_rating; } else { ?>5<?php } ?>"/>
                        <br />
                        <?php echo wprev_i('The highest value allowed in this rating system. Default is 5 stars.'); ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row" valign="top"><?php echo '<h3>' . wprev_i('Appearance') . '</h3>'; ?></th>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wprev_i('Use WidgetPack reviews on'); ?></th>
                    <td>
                        <select name="wprev_replace" tabindex="1" class="wprev-replace">
                            <option value="all" <?php if($wprev_replace == 'all'){echo 'selected';}?>><?php echo wprev_i('All blog posts.'); ?></option>
                            <option value="closed" <?php if('closed'==$wprev_replace){echo 'selected';}?>><?php echo wprev_i('Blog posts with closed comments only.'); ?></option>
                        </select>
                        <br />
                        <?php
                            if ($wprev_replace == 'closed') echo '<p class="wprev-alert">'.wprev_i('You have selected to only enable WidgetPack on posts with closed comments. If you aren\'t seeing WidgetPack on new posts, change this option to "All blog posts".').'</p>';
                            else echo wprev_i('Shows reviews on either all blog posts, or ones with closed comments. Select the "Blog posts with closed comments only" option if you plan on disabling WidgetPack, but want to keep it on posts which already have comments.');
                        ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row" valign="top"><?php echo '<h3>' . wprev_i('Synchronization') . '</h3>'; ?></th>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wprev_i('Review Importing'); ?></th>
                    <td>
                        <input type="checkbox" id="wprev_sync_off" name="wprev_sync_off" <?php if($wprev_sync_off) {echo 'checked="checked"';}?> >
                        <label for="wprev_sync_off"><?php echo wprev_i('Disable automated review importing'); ?></label>
                        <br><?php echo wprev_i('If you have problems with WP-Cron taking too long, or have a large number of reviews, you may wish to disable automated sync. Reviews will only be imported to your local Wordpress database if you do so manually.'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wprev_i('Server-Side Rendering'); ?></th>
                    <td>
                        <input type="checkbox" id="wprev_disable_ssr" name="wprev_disable_ssr" <?php if($wprev_disable_ssr){echo 'checked="checked"';}?> >
                        <label for="wprev_disable_ssr"><?php echo wprev_i('Disable server-side rendering of reviews'); ?></label>
                        <br><?php echo wprev_i('Hides reviews from nearly all search engines.'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo wprev_i('Debug Mode'); ?></th>
                    <td>
                        <input type="checkbox" id="wprev_debug" name="wprev_debug" <?php if($wprev_debug){echo 'checked="checked"';}?> >
                        <label for="wprev_debug"><?php echo wprev_i('Show debug information'); ?></label>
                        <br><?php echo wprev_i('Turn it on only if WidgetPack support team asked to do this.'); ?>
                    </td>
                </tr>
            </table>
            <p class="submit" style="text-align: left">
                <input type="hidden" name="wprev_site_id" value="<?php echo esc_attr($wprev_site_id); ?>"/>
                <input type="hidden" name="wprev_api_key" value="<?php echo esc_attr($wprev_api_key); ?>"/>
                <input name="submit" type="submit" value="Save" class="button-primary button" tabindex="4">
            </p>
            </form>

            <h3>Import</h3>
            <table class="form-table">
                <!--tr id="export">
                    <th scope="row" valign="top"><?php echo wprev_i('Export reviews to WidgetPack'); ?></th>
                    <td>
                        <div id="wprev_export">
                            <form method="POST" action="">
                                <?php wp_nonce_field('wprev-wpnonce_wprev_export', 'wprev-form_nonce_wprev_export'); ?>
                                <p class="status">
                                    <a href="#" class="button"><?php echo wprev_i('Export Reviews'); ?></a>
                                    <?php echo wprev_i('This will export your existing WordPress comments to WidgetPack as reviews'); ?>
                                </p>
                            </form>
                        </div>
                    </td>
                </tr-->
                <tr>
                    <th scope="row" valign="top"><?php echo wprev_i('Sync WidgetPack with WordPress'); ?></th>
                    <td>
                        <div id="wprev_import">
                            <form method="POST" action="">
                            <?php wp_nonce_field('wprev-wpnonce_wprev_import', 'wprev-form_nonce_wprev_import'); ?>
                                <div class="status">
                                    <p>
                                        <a href="#" class="button"><?php echo wprev_i('Sync Reviews'); ?></a>
                                        <?php echo wprev_i('This will download your WidgetPack reviews and store them locally in WordPress'); ?>
                                    </p>
                                    <label>
                                        <input type="checkbox" id="wprev_import_wipe" name="wprev_import_wipe" value="1"/>
                                        <?php echo wprev_i('Remove all imported WidgetPack reviews before syncing.'); ?>
                                    </label>
                                    <br/>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
            </table>

            <h3>Reset</h3>
            <table class="form-table">
                <tr>
                    <th scope="row" valign="top"><?php echo wprev_i('Reset WidgetPack'); ?></th>
                    <td>
                        <form action="?page=wprev" method="POST">
                            <?php wp_nonce_field('wprev-wpnonce_wprev_reset', 'wprev-form_nonce_wprev_reset'); ?>
                            <p>
                                <input type="submit" value="Reset" name="reset" onclick="return confirm('<?php echo wprev_i('Are you sure you want to reset the WidgetPack plugin?'); ?>')" class="button" />
                                <?php echo wprev_i('This removes all WidgetPack-specific settings. Reviews will remain unaffected.') ?>
                            </p>
                            <?php echo wprev_i('If you have problems with resetting taking too long you may wish to first manually drop the \'wprev_meta_idx\' index from your \'commentmeta\' table.') ?>
                        </form>
                    </td>
                </tr>
            </table>
            <br/>
        </div>
    </div>
</div>
<?php } ?>