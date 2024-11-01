<?php
require_once(ABSPATH.WPINC.'/http.php');
require_once(dirname(__FILE__) . '/wprev-api.php');

class WPacReviewWordPressAPI {
    var $site_id;
    var $site_api_key;

    function WPacReviewWordPressAPI($site_id=null, $site_api_key=null) {
        $this->site_id = $site_id;
        $this->site_api_key = $site_api_key;
        $this->api = new WPacReviewAPI($site_id, $site_api_key, WPAC_API_URL);
    }

    function get_last_error() {
        return $this->api->get_last_error();
    }

    function review_list($offset_id=0) {
        $response = $this->api->review_list(array(
            'status' => 1,
            'offset_id' => $offset_id,
            'size' => WPAC_API_LIST_SIZE
        ));
        return $response;
    }

    function review_list_modif($modif=0, $offset_id=0) {
        $params = array(
            'modif' => $modif,
            'size' => WPAC_API_LIST_SIZE
        );
        if ($offset_id > 0) {
            $params['offset_id']  = $offset_id;
        }
        $response = $this->api->review_list($params);
        return $response;
    }
}

?>
