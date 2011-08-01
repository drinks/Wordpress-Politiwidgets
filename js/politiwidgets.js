;(function($){

    POLITIWIDGETS = window.POLITIWIDGETS || {
        init : function(){
            // initializes vars
            this.urls = {
                'widget': 'http://politiwidgets.com/embed?',
                'plugin': '/wordpress/wp-content/plugins/wordpress-politiwidgets',
                'sunlight': 'http://services.sunlightlabs.com/api/legislators.get.json?'
            };
            this.selectors = {
                'root': '#sunlight_politiwidgets',
                'suggestions': '#sunlight_politiwidgets_suggested_widgets',
                'active': '#sunlight_politiwidgets_active_widgets'

            };
            this.meta_key = '_sunlight_politiwidgets_widgets';
            this.urls['ajax'] = this.urls['plugin'] + '/ajax.php?';
            return this;
        },
        get_suggestions_for_post: function(){
            // loads the appropriate selection of widget suggestions into the post meta box
            $(POLITIWIDGETS.selectors.root).find(POLITIWIDGETS.selectors.suggestions + '.query').each(function(){
                var post_id = $('#post_ID').val();
                $(this).html('<img src="' + POLITIWIDGETS.urls.plugin + '/img/ajax-loader.gif" alt="loading" />')
                    .load(POLITIWIDGETS.urls['ajax'] + 'action=suggested-widgets&post_id=' + post_id + ' ul.suggestions');
            });
        },
        add_widget: function(evt){
            // appends a widget element to the admin meta box for saving
            evt.preventDefault();
            var widget, resolver;

            if(evt.target.tagName.toLowerCase() == 'input'){
                // clicked the 'add' button
                resolver = $(this).prev('.widget-url').val();
            }else if(evt.target.tagName.toLowerCase() == 'a'){
                // clicked a suggestion
                resolver = $.trim($(this).html());
            }

            if (widget = POLITIWIDGETS._resolve_widget(resolver)){
                $(this).prev('.widget-url').val('');
                widget = $.extend(widget, {
                    unique:'sun-' + new Date().valueOf(),
                    data: JSON.stringify($.extend({}, widget, {type:widget['type']}))
                });
                $.tmpl($('#widget-line-item'), widget)
                    .insertBefore($(POLITIWIDGETS.selectors.active + ' li:last'));
                $(POLITIWIDGETS.selectors.suggestions)
                    .find('.suggestion-'+widget['name'].toLowerCase().replace(' ', '-'))
                        .addClass('added');

            }else{POLITIWIDGETS.flash_error('Could not find a widget for this name');}
        },
        delete_widget: function(evt){
            evt.preventDefault();
            var line_item, post_id, value_to_delete, success;
            line_item = $(evt.target).parents('li.active-widget').eq(0);
            // post_id = $('#post_ID').val();
            // value_to_delete = line_item.find('input.value').val();
            // success = $.ajax(POLITIWIDGETS.urls['ajax'] + 'action=delete-widget'
            //                                             + '&post_id=' + post_id
            //                                             + '&old_value=' + encodeURIComponent(value_to_delete),
            //                  { datatype:'json', async:false, timeout:5000 }).responseText;
            // if(success){
                line_item.remove();
                $(POLITIWIDGETS.selectors.suggestions).find('.suggestion-' + success).removeClass('added');
            // }else{
                // POLITIWIDGETS.flash_error('There was an error removing this widget.');
            // }

        },
        change_widget_type: function(evt){
            var container, widget, newtype;
            container = $(evt.target).parents('li.active-widget').eq(0).find('input.value')
            widget = $.parseJSON(container.val());
            newtype = $(evt.target).val();
            widget.url = widget.url.replace(/w\=[^&]+/, 'w=' + newtype);
            widget['type'] = newtype;
            container.val(JSON.stringify(widget));
        },
        flash_error: function(message){
            // generic error handler for the write page meta box
            alert(message);
        },
        flash_message: function(message){
            // generic notification handler for the write page meta box
            alert(message);
        },
        _resolve_widget: function(text){
            var post_id = $('#post_ID').val();
            // converts a name into a widget hash object
            var widget_json = $.ajax(POLITIWIDGETS.urls['ajax'] + 'action=add-widget&post_id=' + post_id
                                                                + '&q=' + encodeURIComponent(text),
                                 { datatype:'json', async:false, timeout:5000 }).responseText;

            if (widget_json){
                return $.parseJSON(widget_json);
            }else{
                return false;
            }

        },
        _get_querystring: function(url){
            // splits a url and returns the querystring
            var segments, querystring, params={};
            return (segments = url.split('?')) && segments[1] || '';
        },
        _to_params: function(querystring){
            // converts a querystring to a hash of parameters
            var params={}, params_a=[]
            params_a = querystring && querystring.split(/[&\=]/)
            $.each(params_a, function(idx, val){
                if(idx % 2){
                    params[params_a[idx-1]] = val;
                }
            });
            return params;
        }


    }.init();

    // bootstrap on domready
    $(function(){
        // get suggested tags
        POLITIWIDGETS.get_suggestions_for_post();

        // bind add button,
        $(POLITIWIDGETS.selectors.root).find('.button.tagadd').click(POLITIWIDGETS.add_widget);
        // and suggestion tags
        $(POLITIWIDGETS.selectors.suggestions + ' ul.suggestions>li>a.widget').live('click', POLITIWIDGETS.add_widget);

        // bind remove button
        $(POLITIWIDGETS.selectors.active).find('li.active-widget a.remove').live('click', POLITIWIDGETS.delete_widget);

        // bind type change
        $(POLITIWIDGETS.selectors.active).find('li.active-widget select').live('change', POLITIWIDGETS.change_widget_type);

        // bind search suggest
        $(POLITIWIDGETS.selectors.root).find('.form-input-tip')
            .suggest(POLITIWIDGETS.urls['ajax'] + 'action=widget-search', {minchars:3, delay:250})
            .keydown(function(evt){
                if(evt.keyCode == 13){
                    evt.preventDefault();
                    evt.stopPropagation()
                    $(this).next('.button').click();
                }
            });

        $('#sunlight_politiwidgets_color').after('<div id="colorpicker"></div>').next('#colorpicker')
            .farbtastic('#sunlight_politiwidgets_color');
    });

})(jQuery);