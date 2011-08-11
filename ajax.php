<?php
try{
    require_once(dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])))) . '/wp-config.php');
}catch(Exception $e){
    require_once(dirname(dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME']))))) . '/wp-config.php');
}
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : false;
$post_id = (isset($_REQUEST['post_id']) && ctype_digit($_REQUEST['post_id'])) ? $_REQUEST['post_id'] : false;
$search_text = isset($_REQUEST['q']) ? wp_kses($_REQUEST['q'], array()) : false;
$old_value = isset($_REQUEST['old_value']) ? wp_kses($_REQUEST['old_value'], array()) : 'noop';
$key = isset($_REQUEST['key']) ? wp_kses($REQUEST['key'], array()) : 'noop';
$html = 'an error occurred.';
if(isset($Sunlight_Politiwidgets)){

    switch($action):

        case 'suggested-widgets':
            if (!isset($post_id) or !ctype_digit($post_id)):
                echo 'Please specify a valid post_id';
                exit;
            endif;
                $html = $Sunlight_Politiwidgets->ajax_meta_box($post_id);
        break;

        case 'widget-search':
            $html = $Sunlight_Politiwidgets->ajax_search_suggest($search_text);
        break;

        case 'add-widget':
            $html = $Sunlight_Politiwidgets->ajax_add_widget($post_id, $search_text);
        break;

        case 'delete-widget':
            $html = $Sunlight_Politiwidgets->ajax_delete_widget($post_id, $old_value);
        default:
        break;
    endswitch;

}

echo $html;

?>