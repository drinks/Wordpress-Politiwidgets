<?php function array_key_matches($needle, $haystack){
    foreach ($haystack as $widget):
        if (sanitize_title_with_dashes($widget->name) == $needle):
            return true;
        endif;
    endforeach;
    return false;
}?>
<?php
// Use nonce for verification
wp_nonce_field(plugin_basename(dirname(dirname(__FILE__))), $namespace . '_nonce' );
?>

<!-- jQuery Templates for ajax UI elements -->
<script id="widget-line-item" type="text/x-jquery-tmpl">
    <li class="active-widget">
        <span><a class="remove ntdelbutton" href="#">remove</a></span>
        <a href="${url}">${name} (${party})</a>
        <select>
            <?php $types = $incumbent_widgets;
                  foreach ($types as $type): ?>
                      <option value="<?php echo $type ?>"><?php echo $widget_types[$type]; ?></option>
                  <?php endforeach; ?>
        </select>
        <input type="hidden" name="<?php echo $widget_meta_key;?>[]" class="value" value="${data}" />
    </li>
</script>

<!-- Below is rendered by the server -->
<div id="<?php echo $namespace;?>_suggested_widgets" class="clearfix <?php if ($get_suggestions) echo 'query'; ?>">
    <p class="heading"><strong>Suggestions for this post:</strong> (click to add)</p>
    <ul class="suggestions">
    <?php if ($suggested_widgets && is_array($suggested_widgets)) : ?>
        <?php foreach ($suggested_widgets as $slug => $widget) : ?>
            <li>
            <a href="#<?php echo $slug ?>"
               class="tag widget suggestion-<?php echo $slug;?> <?php if (array_key_matches($slug, $active_widgets)) echo 'added'; ?>">
                <?php echo $widget->entity_data->name; ?>
            </a>
            </li>
        <?php endforeach;?>
    <?php else: ?>
        <li class="empty"><p><?php echo $suggested_widgets; ?></p></li>
    <?php endif; ?>
    </ul>
</div>
<div id="<?php echo $namespace; ?>_active_widgets" class="clearfix">
    <p class="heading"><strong>Widgets attached to this post:</strong></p>
    <ul class="tagchecklist">
        <?php if ($active_widgets) : foreach ($active_widgets as $widget) : ?>
            <li class="active-widget">
                <span><a class="remove ntdelbutton" href="#">remove</a></span>
                <a href="<?php echo $widget->url; ?>"><?php echo "{$widget->name} ({$widget->party})"; ?></a>
                <select>
                    <?php if (preg_match('/vst\=[\d]+/', $widget->url)):
                              $types = $candidate_widgets;
                          else:
                              $types = $incumbent_widgets;
                          endif;
                          foreach ($types as $type): ?>
                              <option value="<?php echo $type ?>" <?php selected($widget->type, $type) ?>><?php echo $widget_types[$type]; ?></option>
                          <?php endforeach; ?>
                </select>
                <input type="hidden" name="<?php echo $widget_meta_key;?>[]" class="value" value="<?php echo htmlentities(json_encode($widget)); ?>" />
            </li>
        <?php endforeach; endif; ?>
    </ul>
    <p class="new">
        <label for="<?php echo $namespace, '_new_widget' ?>">Search for a widget:</label>
        <input type="text" class="widget-url form-input-tip" autocomplete="off" id="<?php echo $namespace, '_new_widget'; ?>" value="" />
        <input type="button" class="button tagadd" value="Add" />
    </p>

</div>
<div class="buttons clearfix">
    <input name="save" type="submit" class="button-primary" value="Update Post" />
</div>
