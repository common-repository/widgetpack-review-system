<div id="wpac-review">
    <?php if (!get_option('wprev_disable_ssr') && have_comments()): ?>
    <div id="wprev-content">

        <?php if (get_comment_pages_count() > 1 && get_option('page_comments')): ?>
        <div class="navigation">
            <div class="nav-previous">
                <span class="meta-nav">&larr;</span>&nbsp;
                <?php previous_comments_link(wprev_i('Older Reviews')); ?>
            </div>
            <div class="nav-next">
                <?php next_comments_link(wprev_i('Newer Reviews')); ?>
                &nbsp;<span class="meta-nav">&rarr;</span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!get_option('wprev_mdata_off')) { ?>
        <div itemscope itemtype="http://schema.org/Product">
            <?php
            global $post;
            $sum = 0;
            $count = 0;
            $comments = $comments = get_comments(array(
                'post_id' => $post->ID,
                'status' => 'approve'
            ));
            foreach($comments as $comment){
                if (preg_match('/^Wprev\/.+/', $comment->comment_agent) && $comment->comment_karma > 0) {
                    $sum += $comment->comment_karma;
                    $count += 1;
                }
            }
            ?>
            <span itemprop="name"><?php echo get_the_title(); ?></span>
            <?php if ($count > 0) { ?>
            <div itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
                <div>Rating:
                    <span itemprop="ratingValue"><?php echo round($sum / $count, 1); ?></span> out of
                    <span itemprop="bestRating"><?php $br = get_option('wprev_best_rating'); if ($br > 0) { echo $br; } else { ?>5<?php } ?></span> with
                    <span itemprop="reviewCount"><?php echo $count; ?></span> ratings
                </div>
            </div>
            <?php } ?>
            <ul id="wprev-reviews">
                <?php wp_list_comments(array('callback' => 'wprev_comment'), $comments); ?>
            </ul>
        </div>
        <?php } else { ?>
        <ul id="wprev-reviews">
            <?php wp_list_comments(array('callback' => 'wprev_comment')); ?>
        </ul>
        <?php } ?>

        <?php if (get_comment_pages_count() > 1 && get_option('page_comments')): ?>
        <div class="navigation">
            <div class="nav-previous">
                <span class="meta-nav">&larr;</span>
                &nbsp;<?php previous_comments_link(wprev_i('Older Reviews') ); ?>
            </div>
            <div class="nav-next">
                <?php next_comments_link(wprev_i('Newer Reviews') ); ?>
                &nbsp;<span class="meta-nav">&rarr;</span>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>
</div>

<?php
if (get_option('wprev_ext_js') == '1') {
    $widget_vars = array(
        'options' => array(
            'sync_off' => get_option('wprev_sync_off'),
        ),
        'host' => WPAC_EMBED_DOMAIN,
        'id' => get_option('wprev_site_id'),
        'chan' => $post->ID,
    );
    wp_register_script('wprev_widget_js', plugins_url('/static/js/wprev.js', __FILE__));
    wp_localize_script('wprev_widget_js', 'widgetVars', $widget_vars);
    wp_enqueue_script('wprev_widget_js', plugins_url('/static/js/wprev.js', __FILE__));
} else {
?>
<script type="text/javascript">
<?php if (get_option('wprev_sync_off') != 1): ?>
setTimeout(function() {
    var script = document.createElement('script');
    script.async = true;
    script.src = '?cf_action=wprev_sync&post_id=<?php echo esc_attr($post->ID); ?>&ver=' + new Date().getTime();
    var firstScript = document.getElementsByTagName('script')[0];
    firstScript.parentNode.insertBefore(script, firstScript);
}, 2000);
<?php endif; ?>

wpac_init = window.wpac_init || [];
wpac_init.push({widget: 'Review', id: <?php echo get_option('wprev_site_id'); ?>, chan: '<?php echo wprev_chan_id($post); ?>'});
(function() {
    if ('WIDGETPACK_LOADED' in window) return;
    WIDGETPACK_LOADED = true;
    var mc = document.createElement('script');
    mc.type = 'text/javascript';
    mc.async = true;
    mc.src = 'https://<?php echo WPAC_EMBED_DOMAIN; ?>/widget.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(mc, s.nextSibling);
})();
</script>
<?php
}
?>