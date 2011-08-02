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
            $(POLITIWIDGETS.selectors.root).find(POLITIWIDGETS.selectors.suggestions + '.query ul').each(function(){
                var post_id = $('#post_ID').val();
                $(this).html('<img src="' + POLITIWIDGETS.urls.plugin + '/img/ajax-loader.gif" alt="loading" />')
                    .load(POLITIWIDGETS.urls['ajax'] + 'action=suggested-widgets&post_id=' + post_id + ' ul.suggestions li');
            });
        },
        add_widget: function(evt){
            // appends a widget element to the admin meta box for saving
            var tgt, widget, resolver, preventdefault=true;

            tgt = $(evt.target);
            if(tgt.is('input.button.tagadd')){
                // clicked the 'add' button
                resolver = tgt.prev('.widget-url').val();
            }else if(tgt.is('input.button-primary')){
                // clicked the update button
                preventdefault = false;
                resolver = $(POLITIWIDGETS.selectors.active + 'p.new input[type=text]').eq(0).val()
            }else if(tgt.is('a')){
                // clicked a suggestion
                resolver = $.trim($(this).html());
            }

            if(resolver){
                if (widget = POLITIWIDGETS._resolve_widget(resolver)){
                    $(this).prev('.widget-url').val('');
                    widget = $.extend(widget, {
                        unique:'sun-' + new Date().valueOf(),
                        data: JSON.stringify($.extend({}, widget, {type:widget['type']}))
                    });
                    $.tmpl($('#widget-line-item'), widget)
                        .appendTo($(POLITIWIDGETS.selectors.active + ' ul.tagchecklist'));
                    $(POLITIWIDGETS.selectors.suggestions)
                        .find(POLITIWIDGETS.slugify('.suggestion-'+widget['name']))
                            .addClass('added');

                }else{POLITIWIDGETS.flash_error('Could not find a widget for this name');}
            }

            if(preventdefault) evt.preventDefault();
        },
        delete_widget: function(evt){
            evt.preventDefault();
            var line_item, line_item_slug, same_siblings;

            line_item = $(evt.target).parents('li.active-widget').eq(0);
            line_item_slug = POLITIWIDGETS.slugify(JSON.parse(line_item.find('input.value')
                                                                       .eq(0)
                                                                       .val()).name);
            same_siblings = line_item.siblings().filter(function(){
                return $(this).find('a').eq(1).html() == line_item.find('a').eq(1).html();
            });
            line_item.remove();
            console.log(line_item_slug, same_siblings);

            if(!same_siblings.length)
                $(POLITIWIDGETS.selectors.suggestions).find('.suggestion-' + line_item_slug).removeClass('added');

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
        slugify: function(str){
            return str.toLowerCase().replace(' ', '-');
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

    // domready bootstrap
    $(function(){
        // get suggested tags
        POLITIWIDGETS.get_suggestions_for_post();

        // bind add/update buttons,
        $(POLITIWIDGETS.selectors.root).find('input.button.tagadd, input.button-primary').click(POLITIWIDGETS.add_widget);

        // and suggestion tags
        $(POLITIWIDGETS.selectors.suggestions + ' ul.suggestions>li>a.widget').live('click', POLITIWIDGETS.add_widget);

        // bind remove button
        $(POLITIWIDGETS.selectors.active).find('li.active-widget a.remove').live('click', POLITIWIDGETS.delete_widget);

        // bind type change
        $(POLITIWIDGETS.selectors.active).find('li.active-widget select').live('change', POLITIWIDGETS.change_widget_type);

        // make the active list sortable
        $(POLITIWIDGETS.selectors.active).find('ul').sortable({handle: 'a:eq(1)', axis: 'y'});

        // bind search suggest
        $(POLITIWIDGETS.selectors.root).find('.form-input-tip')
            .suggest(POLITIWIDGETS.urls['ajax'] + 'action=widget-search', {minchars:2, delay:250})
            .keydown(function(evt){
                if(evt.keyCode == 13){
                    evt.preventDefault();
                    evt.stopPropagation()
                    $(this).next('.button').click();
                }
            });

        $('#sunlight_politiwidgets_color').each(function(){
            $(this).after('<div id="colorpicker"></div>').next('#colorpicker')
                .farbtastic('#sunlight_politiwidgets_color');
        });

    });

})(jQuery);
