jQuery(document).ready(function($) {
    if (window.location.hash) {
        $('.nav-tabs li.active').removeClass('active');
        $('a[href="' + window.location.hash + '"]').parent().addClass('active');
        if (window.location.hash == '#wprev-plugin') {
            $('#wprev-main').hide();
            $('#wprev-plugin-pane').show();
        } else {
            $('#wprev-main').show();
            $('#wprev-plugin-pane').hide();
        }
    } else {
        window.location.hash = '#/site/' + adminVars.siteId + '/menu/review/submenu/moderation';
    }
    $('a.wprev-tab').unbind().click(function() {
        if ($(this).parent().hasClass('active')) {
            return false;
        }
        $('.nav-tabs li.active').removeClass('active');
        $(this).parent().addClass('active');
        $('.tab-content .tab-pane.active').removeClass('active');
        $('.tab-content #wprev-main').addClass('active');
        if ($(this).attr('href') == '#wprev-plugin') {
            $('#wprev-main').hide();
            $('#wprev-plugin-pane').show();
        } else {
            $('#wprev-main').show();
            $('#wprev-plugin-pane').hide();
        }
        return true;
    });
    wprev_fire_export();
    wprev_fire_import();
});

var wprev_fire_export = function() {
    jQuery(function($) {
        $('#wprev_export a.button').unbind().click(function() {
            $('#wprev_export .status').removeClass('wprev-export-fail').addClass('wprev-exporting').html('Processing...');
            wprev_export_comments();
            return false;
        });
    });
};

var wprev_export_comments = function() {
    jQuery(function($) {
        var status = $('#wprev_export .status');
        var nonce = $('#wprev-form_nonce_wprev_export').val();
        var export_info = (status.attr('rel') || '0|' + (new Date().getTime()/1000)).split('|');        
        $.get(
            adminVars.indexUrl,
            {
                cf_action: 'wprev_export',
                post_id: export_info[0],
                timestamp: export_info[1],
                _wprevexport_wpnonce: nonce
            },
            function(response) {
                var host = 'https://api.widgetpack.com',
                    url = host + '/1.0/review/import?site_id=' + response.site_id + '&signature=' + response.signature;

                var req = new XMLHttpRequest();
                req.open('POST', url, true);
                req.setRequestHeader('Content-Type', 'application/json');
                req.onreadystatechange = function(res) {
                    if (req.readyState === 4) {
                        if (req.status === 200) {
                            var result = JSON.parse(req.responseText), msg;
                            if (result.error) {
                                msg = 'Failed to import reviews to WidgetPack for post ID ' + response.post_id + ' please contact@widgetpack.com';
                            } else {
                                msg = 'Reviews have been successful imported to WidgetPack for post ID' + response.post_id;
                            }
                            status.html(msg).attr('rel', response.post_id + '|' + response.timestamp);
                            switch (response.status) {
                                case 'partial':
                                    wprev_export_comments();
                                break;
                                case 'complete':
                                    status.html('All commets have been successfully imported').removeClass('wprev-exporting').addClass('wprev-exported');
                                break;
                            }
                        }
                    }
                };
                req.send(response.json);
            },
            'json'
        );
    });
};

var wprev_fire_import = function() {
    jQuery(function($) {
        $('#wprev_import a.button, #wprev_import_retry').unbind().click(function() {
            var wipe = $('#wprev_import_wipe').is(':checked');
            $('#wprev_import .status').removeClass('wprev-import-fail').addClass('wprev-importing').html('Processing...');
            wprev_import_comments(wipe);
            return false;
        });
    });
};

var wprev_import_comments = function(wipe) {
    jQuery(function($) {
        var status = $('#wprev_import .status');
        var nonce = $('#wprev-form_nonce_wprev_import').val();
        var last_id = status.attr('rel') || '0';
        $.get(
            adminVars.indexUrl,
            {
                cf_action: 'wprev_import',
                last_id: last_id,
                wipe: (wipe ? 1 : 0),
                _wprevimport_wpnonce: nonce
            },
            function(response) {
                switch (response.result) {
                    case 'success':
                        status.html(response.msg).attr('rel', response.last_id);
                        switch (response.status) {
                            case 'partial':
                                wprev_import_comments(false);
                                break;
                            case 'complete':
                                status.removeClass('wprev-importing').addClass('wprev-imported');
                                break;
                        }
                    break;
                    case 'fail':
                        status.parent().html(response.msg);
                        wprev_fire_import();
                    break;
                }
            },
            'json'
        );
    });
};