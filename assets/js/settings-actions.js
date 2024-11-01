jQuery(document).ready(function(){
    jQuery('#wscs-sale-countdown-panel-background').iris({change: function( event, ui ) {
        jQuery('.description .flip-clock-wrapper .inn').css( 'background', '#' + (ui.color._color).toString(16) );
        jQuery(this).parent().find('.colorpickpreview').css('background', '#' + (ui.color._color).toString(16) );
    } })


    jQuery('#wscs-sale-countdown-number-color').iris({change: function( event, ui ) {
        jQuery('.description .flip-clock-wrapper .inn').css( 'color', '#' + (ui.color._color).toString(16) );
        jQuery(this).parent().find('.colorpickpreview').css('background', '#' + (ui.color._color).toString(16) );

    } })

    jQuery('#wscs-sale-countdown-stock-progress-color').iris({change: function( event, ui ) {
        jQuery('.product-stock-wrapper .percent').css( 'background', '#' + (ui.color._color).toString(16) );
        jQuery(this).parent().find('.colorpickpreview').css('background', '#' + (ui.color._color).toString(16) );

    } })
})
