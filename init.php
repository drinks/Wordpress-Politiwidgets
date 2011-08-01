<?php
/*
Plugin Name: Politiwidgets
Plugin URI: http://politiwidgets.com
Description: Embed helper for integrating with PolitiWidgets from the Sunlight Foundation.
Version: 0.1
Author: The Sunlight Foundation
Author URI: http://sunlightlabs.com
License: MIT
*/
include_once('politiwidgets.php');
$Sunlight_Politiwidgets = new Politiwidgets();
register_activation_hook(WP_PLUGIN_DIR . '/wordpress-politiwidgets/init.php', 'install_politiwidgets');
function install_politiwidgets(){
    $plugin = new Politiwidgets();
    $plugin->activate();
}
add_action('widgets_init', create_function('', 'return register_widget("PolitiwidgetsWidget");'));