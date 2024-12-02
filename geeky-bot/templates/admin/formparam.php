<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
if (!empty(geekybot::$_data[0])) {
   $data = isset(geekybot::$_data[0]) ? geekybot::$_data[0] : null;
   $parameter = $data->parameter;
   $parameter = json_decode($parameter);
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
<div class="geekybot-form-title">
   <?php echo esc_html(__('Parameter', 'geeky-bot')); ?>
   <span style="color: red;" >*</span>
</div>
<div class="geekybot-form-value">
    <div class="geekybot-form-button" style="border-top: none;text-align: left;margin: 0px 0px 0px;padding-top: 0px">
    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_button('Add', esc_attr(__('Add','geeky-bot')) .' '. esc_attr(__('Parameter', 'geeky-bot')), array('class' => 'button geekybot-form-add-btn', 'onclick' => 'addParameter()')), GEEKYBOT_ALLOWED_TAGS);?>
    </div>
    <div class="geekybot-form-value">
        <div class="geekybot-form-value">
            <div class="geekybot-form-autocomplete">
                <table class="geekybot-auto-table">
                    <tbody id="TextBoxContainer">
                        <?php
                        $key =0;
                        if (!empty(geekybot::$_data[0])) {
                            foreach ($parameter as $key => $params) {
                                $key += 1;?>
                                <tr id="row_<?php echo esc_attr($key) ?>" class="geekybot-auto-table-row">
                                    <?php
                                    foreach ($params as $key2 => $paramtype ) { ?>
                                        <td class="geekybot-auto-table-row-cnt geekybot-address">
                                            <input name = "paramname[]" type="text" value = "<?php echo esc_attr($key2) ?>" class="inputbox one geekybot-form-input-field" data-validation="required"  id="autocomplete<?php echo esc_attr($key) ?>" onkeypress="ShowList(<?php echo esc_js($key) ?>)" autocomplete="off" />
                                        </td>
                                        <td class="geekybot-auto-table-row-cnt geekybot-select" id="typ<?php echo esc_attr($key) ?>">
                                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('paramtype[]', $paramlist, isset($paramtype) ? $paramtype : '', null, array('class' => 'inputbox geekybot-form-select-field', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>                                     
                                        </td>
                                        <td class="geekybot-auto-table-row-cnt geekybot-del-btn">
                                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/delete.png" alt="<?php echo esc_attr(__('delete', 'geeky-bot')); ?>" onclick="deleteparam(<?php echo esc_js($key)?>)">
                                        </td>
                                        <?php
                                    } ?>
                                </tr>
                                <?php
                            }
                        }?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('totrow', $key), GEEKYBOT_ALLOWED_TAGS); ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
$geekybot_js ='
    var paramid =  jQuery("#totrow").val();//1;
    if(paramid==0 || paramid==undefined) {
        var paramid = 1;
    } else {
        var paramid =  Number(jQuery("#totrow").val()) + 1;
    }
    function addParameter() {
        var div = jQuery("<tr class=\"geekybot-auto-table-row\" id=\"row_' + paramid + '\"/>");
        div.html(GetDynamicTextBox("",paramid));
        jQuery("#TextBoxContainer").append(div);
       paramid += 1;
    }
    function GetDynamicTextBox(value,id) {
        htmlStr  = "<td class=\"geekybot-auto-table-row-cnt geekybot-address\"><input name = \"paramname[]\" type=\"text\" value = \"\" class=\"inputbox one geekybot-form-input-field\" data-validation=\"required\" id=\"autocomplete'+id+'\" onkeypress=\"ShowList(' + id + ')\" autocomplete=\"off\" /></td>";
        htmlStr += "<td class=\"geekybot-auto-table-row-cnt geekybot-select\"  id=\"typ'+id+'\">'. wp_kses(GEEKYBOTformfield::GEEKYBOT_select("paramtype[]", $paramlist, isset($parameter->paramtype) ? $parameter->paramtype : "", null, array("class" => "inputbox geekybot-form-select-field", "data-validation" => "required")), GEEKYBOT_ALLOWED_TAGS) .'</td>";
        htmlStr += "<td class=\"geekybot-auto-table-row-cnt geekybot-del-btn\"><img src=\"'. esc_url(GEEKYBOT_PLUGIN_URL) .'includes/images/control_panel/delete.png\" alt=\"'. esc_attr(__('delete', 'geeky-bot')) .'\" onclick=\"deleteparam(' + id + ')\"></td>";
        return htmlStr;
    }

    function deleteparam(id) {
        jQuery("#row_"+ id).remove();
    }

    function ShowList(i) {
        ';
        if(isset(geekybot::$_data['slotList'])){
            $slotList = geekybot::$_data['slotList'];
        } else {
            $slotList = '';
        }
        $geekybot_js .='
        var data = '. esc_attr($slotList) .';
        jQuery("#autocomplete"+i).autocomplete({
            source: data,
            autoFocus: true,
            classes: {
                "ui-autocomplete": "geekybot-ui-autocomplete"
                },
                select: function(event, ui) {
                    // prevent autocomplete from updating the textbox
                    event.preventDefault();
                    // manually update the textbox and hidden field
                    jQuery(this).val(ui.item.value);
                    //alert(ui.item.type);
                    jQuery("#autocomplete"+i+"-value").val(ui.item.value);
                    GetGEEKYBOT_select(ui.item.type,i);
                }
        }).data("ui-autocomplete")._renderItem = function( ul, item ) {
            let txt = String(item.value).replace(new RegExp(this.term, "i"),"<b>$&</b>");
            return jQuery("<li></li>")
                .data("ui-autocomplete-item", item)
                .append("<a>" + txt + "</a>")
                .appendTo(ul);
        };

    }
    function GetGEEKYBOT_select(val,id){
        htmlStr = "<select name=\"paramtype[]\" id=\"paramtype[]\" class=\"inputbox geekybot-form-select-field disabledauto\" data-validation=\"required\" ><option class=\"\" value=\"'+val+'\">'+val+'</option></select>";
        jQuery("#typ"+id).html(htmlStr);
    }
    ';
    wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>