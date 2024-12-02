<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
if (!empty(geekybot::$_data[0])) {
   $data = isset(geekybot::$_data[0]) ? geekybot::$_data[0] : null;
   $parameters = $data->parameters;
   $parameters = json_decode($parameters);
   $slotsnam = geekybot::$_data['slotList'];
} else {
    geekybot::$_data['slotList'] = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->getMultiSelectEdit();
    $slotsnam = geekybot::$_data['slotList'];
}
$paramlist = array(
    (object) array('id' => '', 'text' => __('Select Type', 'geeky-bot')),
    (object) array('id' => 'integer', 'text' => __('Integer', 'geeky-bot')),
    (object) array('id' => 'float', 'text' => __('Float', 'geeky-bot')),
    (object) array('id' => 'boolean', 'text' => __('Boolean', 'geeky-bot')),
    (object) array('id' => 'string', 'text' => __('String', 'geeky-bot'))
);
?>        
<div class="geekybot-form-autocomplete-main">
    <div class="geekybot-form-autocomplete">
        <table class="geekybot-auto-table">
            <tbody id="TextBoxContainer">
                <?php
                $key = 0;
                if (!empty(geekybot::$_data[0])) {
                    foreach ($parameters as $key => $params) {
                        $key += 2;
                        ?>
                        <tr id="row_<?php echo esc_attr($key) ?>" class="geekybot-auto-table-row">
                        <?php
                        foreach ($params as $key2 => $paramtype ) { ?>
                            <td class="geekybot-auto-table-row-cnt geekybot-address">
                                <input name = "paramname[]" type="text" value = "<?php echo esc_attr($key2) ?>" class="inputbox one geekybot-form-input-field" data-validation="required"  id="autocomplete<?php echo esc_attr($key) ?>" onkeypress="ShowList(<?php echo esc_js($key) ?>)" autocomplete="off" />
                            </td>
                            <td class="geekybot-auto-table-row-cnt geekybot-select" id="typ<?php echo esc_attr($key) ?>"><?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('paramtype[]', $paramlist, isset($paramtype) ? $paramtype : '', null, array('class' => 'inputbox geekybot-form-select-field', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?></td>
                            <td class="geekybot-auto-table-row-cnt geekybot-del-btn"><img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/delete.png" alt="<?php echo esc_attr(__('delete', 'geeky-bot')); ?>" onclick="deleteparam(<?php echo esc_js($key) ?>)"></td>
                           <?php
                        }
                    }
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2">
                        <?php
                        isset($key) ? esc_html($key) : '1';
                        echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('totrow', $key), GEEKYBOT_ALLOWED_TAGS);
                        ?>
                    </th>
                </tr>
             </tfoot>
         </table>
      </div>
</div>
<?php
if(isset(geekybot::$_data['slotList'])){
    $slotList = wp_kses(geekybot::$_data['slotList'], GEEKYBOT_ALLOWED_TAGS);
} else {
    $slotList = '';
}
$geekybot_js ="
    var paramid =  jQuery('#totrow').val();//1;
    if(paramid==0 || paramid==undefined) {
        var paramid = 1;
    }
    function addParameter() {
        var sel = jQuery('#typ1 select option:selected').val();
        var pname = jQuery('#autocomplete1').val();
        if(pname!='' && sel!='' )//&& jQuery('#autocomplete1').val()!=''
        {
            var div = jQuery('<tr class=\"geekybot-auto-table-row\" id=\"row_' + paramid + '\"/>');
            div.html(GetDynamicTextBox(pname,paramid,sel));
            jQuery('#TextBoxContainer').append(div);
            paramid += 1;
            Number(jQuery('#totrow').val(paramid));
            jQuery('#autocomplete1').val('');
            jQuery('#typ1 select option:selected').removeAttr('selected');
        } else {
            jQuery('#autocomplete1').focus();
        }
    }
    function GetDynamicTextBox(value,id,sel) {
        htmlStr  = '<td class=\"geekybot-auto-table-row-cnt geekybot-address\"><input name = \"paramname[]\" type=\"text\" value = \"'+value+'\" class=\"inputbox one geekybot-form-input-field\" data-validation=\"required\" id=\"autocomplete'+id+'\" onkeypress=\"ShowList(' + id + ')\" autocomplete=\"off\" /></td>';
        htmlStr += '<td class=\"geekybot-auto-table-row-cnt geekybot-select\"  id=\"typ'+id+'\"><select name=\"paramtype[]\" id=\"paramtype[]\" class=\"inputbox geekybot-form-select-field\" data-validation=\"required\"><option class=\"\" value=\"'+sel+'\">'+sel+'</option></select></td>';
        htmlStr += '<td class=\"geekybot-auto-table-row-cnt geekybot-del-btn\"><img src=\"".esc_url(GEEKYBOT_PLUGIN_URL)."includes/images/control_panel/delete.png\" alt=\"". esc_html(__('delete', 'geeky-bot')) ."\" onclick=\"deleteparam(' + id + ')\"></td>';
        return htmlStr;
    }

    function deleteparam(id) {
        jQuery('#row_'+ id).remove();
        var r = jQuery('#totrow').val() -1 ;
        jQuery('#totrow').val(r);
    }

    function ShowList(i) {
        var data = ".$slotList.";
        jQuery('#autocomplete'+i).autocomplete({
            source: data,
            autoFocus: true,
            classes: {
                'ui-autocomplete': 'geekybot-ui-autocomplete'
                },
                select: function(event, ui) {
                    // prevent autocomplete from updating the textbox
                    event.preventDefault();
                    // manually update the textbox and hidden field
                    jQuery(this).val(ui.item.value);
                    //alert(ui.item.type);
                    jQuery('#autocomplete'+i+'-value').val(ui.item.value);
                    jQuery('#typ'+i+' select').val(ui.item.type);
                    addParameter();
                }
        }).data('ui-autocomplete')._renderItem = function( ul, item ) {
            let txt = String(item.value).replace(new RegExp(this.term, 'i'),'<b>$&</b>');
            return jQuery('<li></li>')
            .data('ui-autocomplete-item', item)
            .append('<a>' + txt + '</a>')
            .appendTo(ul);
        };
    }
    
    function GetGEEKYBOT_select(val,id) {
        //  htmlStr = '<select name=\"paramtype[]\" id=\"paramtype[]\" class=\"inputbox geekybot-form-select-field\" data-validation=\"required\" ><option class=\"\" value=\"'+val+'\">'+val+'</option></select>';
        //jQuery(\"#typ\"+id).html(val);
        jQuery('#typ1 select').val(val);

    }
";
wp_add_inline_script('geekybot-main-js',$geekybot_js);