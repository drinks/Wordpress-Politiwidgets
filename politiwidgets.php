<?php
ini_set('display_errors', 1);
if (!function_exists('http_build_query')):
    function http_build_query($query_data, $numeric_prefix='', $arg_separator='&'){
       $arr = array();
       foreach ($query_data as $key=>$val)
         $arr[] = urlencode($numeric_prefix.$key) . '=' . urlencode($val);
       return implode($arr, $arg_separator);
    }
endif;
if (!class_exists('WP_PluginBase')) require_once('lib/wp-plugin-base/wp-plugin-base.php');
if (!class_exists('InfluenceExplorer')) require_once('lib/class.influenceexplorer.php');
if (!class_exists('Snoopy')) include_once('lib/Snoopy-1.2.4/Snoopy.class.php');
if (!class_exists('Politiwidgets')){

    class Politiwidgets extends WP_PluginBase{

        var $connection,
            $influenceexplorer,
            $namespace = 'sunlight_politiwidgets',
            $title = 'Politiwidgets',
            $capability = 'manage_options',
            $settings = array(
                'sunlight_api_key'=>
                    array('name'=>'Sunlight API key',
                          'type'=>'text',
                          'value'=>''),
                'suggest'=>
                    array('name'=>'Suggest widgets',
                          'type'=>'checkbox',
                          'value'=>true),
                'always_on'=>
                    array('name'=>'Include suggested widgets by default',
                          'type'=>'checkbox',
                          'value'=>false),
                'color'=>
                    array('name'=>'Base widget color',
                          'type'=>'text',
                          'value'=>'1F83B5'),
            ),
            $api_base_uri = 'http://localhost:8000/api/v1',
            $search_base_uri = 'http://localhost:3000/search.json?',
            $widget_base_uri = 'http://localhost:3000/embed?',
            $api_default_params = array( 'format' => 'json', ),
            $widget_meta_key,
            $widget_types = array(
                'bio' => 'Business Card',
                'bill' => 'Vote Report',
                'contractors' => 'Top Contractors',
                'sponsorships' => 'Sponsorships',
                'contributions' => 'Campaign Contributions',
                'top_contributions' => 'Top Contributors',
                'earmarks' => 'Earmarks',
                'ratings' => 'Interest Group Ratings',
                'district' => 'District Map',
                'parties' => 'Party Time',
            ),
            $candidate_widgets = array('bio', 'district', 'parties', 'contributions', 'top_contributors',),
            $incumbent_widgets = array('bio', 'bill', 'contractors', 'sponsorships', 'contributions',
                                       'top_contributions', 'earmarks', 'ratings', 'district', 'parties',),
            $entity_cache_meta_key;

        function Politiwidgets(){
            /* call the php5 constructor */
            $this->__construct();
        }

        function __construct(){
            /* set some options */
            $this->connection = new Snoopy;
            $this->widget_meta_key = '_' . $this->namespace . '_widgets';
            $this->entity_cache_meta_key = '_' . $this->namespace . '_entity_cache';

            /* register hooks */
            add_action('admin_enqueue_scripts', array(&$this, 'enqueue_scripts'));
            add_action('admin_init', array(&$this, 'add_meta_box'));
            add_action('admin_notices', array(&$this, 'api_key_notice'));
            add_action('save_post', array(&$this, 'save_post_handler'));
            add_filter('the_content', array(&$this, 'the_content_handler'));
            add_shortcode('politiwidget', array(&$this, 'shortcode_handler'));

            /* super */
            parent::__construct();

            /* after settings are loaded */
            $this->influenceexplorer = new InfluenceExplorer($this->setting('sunlight_api_key'));
        }

        function enqueue_scripts($hook){
            // only need extra css/js for post pages
            if (!in_array($hook, array('edit.php', 'post.php', 'settings_page_sunlight_politiwidgets_settings_page'))) return;

            // css
            wp_register_style('farbtastic', WP_PLUGIN_URL . '/' . basename(dirname(__FILE)) . '/css/farbtastic.css');
            wp_enqueue_style('farbtastic');

            wp_register_style('politiwidgets', WP_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/css/politiwidgets.css');
            wp_enqueue_style('politiwidgets');

            // js
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('suggest');

            // json for everybody!
            wp_register_script('json', WP_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/js/json.js');
            wp_enqueue_script('json');

            wp_register_script('jquery-template', WP_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/js/jquery.tmpl.min.js');
            wp_enqueue_script('jquery-template');

            wp_register_script('farbtastic', WP_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/js/farbtastic.js');
            wp_enqueue_script('farbtastic');

            wp_register_script('politiwidgets', WP_PLUGIN_URL . '/' . basename(dirname(__FILE__)) . '/js/politiwidgets.js');
            wp_enqueue_script('politiwidgets');
        }

        function api_key_notice(){
            if (!$this->setting('sunlight_api_key'))
                echo '<div class="error"><p>You need to set a <a href="' .
                      admin_url() . 'options-general.php?page=' .
                      $this->namespace .
                      '_settings_page">Sunlight API Key</a> before Politiwidgets will work.</p></div>';
        }

        function add_meta_box(){
            add_meta_box($this->namespace,
                         __($this->title, $this->namespace . '_textdomain'),
                         array(&$this, 'meta_box'),
                         'post');
        }

        function meta_box($post){
            $active_widgets = get_post_meta($post->ID, $this->widget_meta_key);
            $active_widgets = array_map(array(&$this, '_decode_widget'), $active_widgets);
            $context = array('active_widgets'=>$active_widgets,
                             'suggested_widgets'=>$suggested_widgets,
                             'namespace'=>$this->namespace,
                             'get_suggestions'=>$this->setting('suggest'),
                             'widget_meta_key'=>$this->widget_meta_key,
                             'widget_types'=>$this->widget_types,
                             'candidate_widgets'=>$this->candidate_widgets,
                             'incumbent_widgets'=>$this->incumbent_widgets,);
            uTemplate::render(dirname(__FILE__) . '/templates/meta_box.php', $context);
        }

        function the_content_handler($content){
            global $post;
            $widgets = get_post_meta($post->ID, $this->widget_meta_key);
            foreach($widgets as $widget):
                $_widget = $widget;
                $widget = $this->_decode_widget($widget);

                // make sure we have the latest color
                if(!preg_match('/'.$this->_get_color().'/', $widget->url)):
                    $widget->url = preg_replace('/color\=[0-9a-fA-F]{6}/', 'color=' . $this->_get_color(), $widget->url);
                    update_post_meta($post->ID, $this->widget_meta_key, $_widget);
                endif;

                $content .= "\n" .
                            '<script type="text/javascript" src="' .
                            $widget->url .
                            '"></script>';
            endforeach;
            return $content;
        }

        function save_post_handler($post_id){
            if (!wp_verify_nonce($_POST[$this->namespace . '_nonce'], plugin_basename(dirname(__FILE__)))):
                return false;
            endif;
            if (!current_user_can('edit_post', $post_id)) return false;

            $post = get_post($post_id);

            // Run this block only when we save explicitly
            if (!wp_is_post_revision($post)):

                // empty the entity cache if suggestions are on so it will be
                // reloaded on the next request.
                if ($this->setting('suggest')):
                    update_post_meta($post->ID, $this->entity_cache_meta_key, '');
                endif;

                // get all attached widgets
                $new_widgets = array_map(array(&$this, '_decode_widget'), $_REQUEST[$this->widget_meta_key]);

                // delete old widgets for this post
                delete_post_meta($post->ID, $this->widget_meta_key);

                // add the submitted ones
                $widgets = array_map('json_encode', $new_widgets);
                foreach ($widgets as $widget):
                    add_post_meta($post->ID, $this->widget_meta_key, $widget);
                endforeach;

            endif;
        }

        function shortcode_handler($atts){
            extract(shortcode_atts(array(
                'widget' => 'bio',
                'bioguide_id' => '',
                'votesmart_id' => '',
                'size' => 'lg',
                'color' => $this->_get_color(),
                'geolocate' => false,
            ), $atts));
            if (!$bioguide_id && !$votesmart_id) return '';
            $params = array(
                'w' => $widget,
                'bgd' => $bioguide_id,
                'vst' => $votesmart_id,
                's' => $size,
                'color' => $color,
                'geolocate' => $geolocate,
            );
            $qs = http_build_query($params);
            return "<script type='text/javascript' src='{$this->widget_base_uri}{$qs}' ></script>";
        }

        function ajax_meta_box($post_id){
            // suggestions
            if ($this->setting('suggest')):
                $post = get_post($post_id);
                $entities = get_post_meta($post->ID, $this->entity_cache_meta_key, true);
                if ($entities):
                    $suggested_widgets = $entities;
                else:
                    $suggested_widgets = array();
                    if ($post->post_content)
                        $suggested_widgets = $this->_get_suggested_widgets($post);

                    // filter suggested widgets to only contain widgetable entities
                    foreach ($suggested_widgets as $i => $widget):
                        if (!$this->_search($widget->entity_data->name))
                            unset($suggested_widgets[$i]);
                    endforeach;

                    update_post_meta($post->ID, $this->entity_cache_meta_key, $suggested_widgets);
                endif;

                if (!$suggested_widgets):
                    $suggested_widgets = 'No suggestions were found';
                endif;

            else:
                $suggested_widgets = 'Enable <kbd>suggest widgets automatically</kbd> to see suggestions here.';
            endif;

            // active widgets
            $active_widgets = get_post_meta($post->ID, $this->widget_meta_key);
            $active_widgets = array_map('json_decode', $active_widgets);

            $context = array('active_widgets'=>$active_widgets,
                             'suggested_widgets'=>$suggested_widgets,
                             'namespace', $this->namespace,);

            $html = uTemplate::parse(dirname(__FILE__) . '/templates/meta_box.php', $context);

            return $html;
        }

        function ajax_search_suggest($text){
            $results = $this->_search($text);
            $suggestions = array();
            if ($results):
                foreach ($results as $result):
                    $result_string = "{$result->firstname} {$result->lastname} ({$result->party})";
                    array_push($suggestions, $result_string);
                endforeach;
            endif;

            return implode("\n", $suggestions);
        }

        function ajax_add_widget($post_id, $text){
            $results = $this->_search($text);
            if(!$results or count($results) > 1) return 'false';
            $result = $results[0];
            $obj = new stdClass();
            $obj->name = "{$result->firstname} {$result->lastname}";
            $obj->party = $result->party;
            $obj->url = $this->_build_widget_url($result);
            $obj->type = 'bio';

            $obj = json_encode($obj);

            // add_post_meta($post_id, $this->widget_meta_key, $obj);

            return $obj;
        }

        function ajax_delete_widget($post_id, $old_value){
            try{
                $old_widget = $this->_decode_widget($old_value);
            }catch(Exception $e){
                return json_encode(false);
            }
            if(delete_post_meta($post_id, $this->widget_meta_key, json_encode($old_widget))):
                return sanitize_title_with_dashes($old_widget->name);
            else:
                return json_encode(false);
            endif;
        }

        function _get_suggested_widgets($post_or_string_or_id){
            if(is_object($post_or_string_or_id)):
                $text = $post_or_string_or_id->post_content;
            elseif (ctype_digit($post_or_string_or_id)):
                $post = get_post($post_or_string_or_id);
                $text = $post ? $post->post_content : '';
            else:
                $text = $post_or_string_or_id;
            endif;
            $widgets = array();
            $entities = $this->_entities_via_influenceexplorer($text);
            foreach ($entities as $entity):
                $widgets[sanitize_title_with_dashes($entity->entity_data->name)] = $entity;
            endforeach;

            return $widgets;
        }

        function _entities_via_influenceexplorer($content){
            $response = is_object($this->influenceexplorer) ?
                $this->influenceexplorer->contextualize($content) :
                false;
            if (!$response):
                // add admin message to check api key
                return array();
            endif;

            return $response->entities;
        }

        function _search($text){
            $retrieved = $this->connection->fetchtext($this->search_base_uri . 'q=' . urlencode($text));
            if($retrieved):
                $results = json_decode($this->connection->results);
                if(is_object($results)) $results = array($results);
                return $results;
            endif;
                return false;
        }

        function _build_widget_url($obj){
            if(!isset($obj->bioguide_id) && !isset($obj->votesmart_id)) return '#';

            $url = $this->widget_base_uri . 'w=bio&';
            if(isset($obj->bioguide_id)):
                $url .= 'bgd=' . $obj->bioguide_id . '&';
            elseif(isset($obj->votesmart_id)):
                $url .= 'vst=' . $obj->votesmart_id . '&';
            endif;
            $url .= 'color=' . $this->setting('color');

            return $url;
        }

        function _decode_widget($widget){
            if(!is_object($widget))
                $widget = json_decode(html_entity_decode(stripslashes(stripslashes($widget))));
            return $widget;
        }

        function _get_color(){
            return str_replace('#', '', $this->setting('color'));
        }

    }

}

if (!class_exists('Politiwidgets_Widget')):

    class Politiwidgets_Widget extends WP_Widget{

    }

endif;