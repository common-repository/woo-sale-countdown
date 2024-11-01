(function($){

    var update_dates = false;

    $(document).ready(function(){
        $('#_sale_price_dates_from').datepicker('destroy').prop( 'type', 'datetime-local' );
        $('#_sale_price_dates_to').datepicker('destroy').prop( 'type', 'datetime-local' );


        if ( WCSC_ajax_data.startDate !== null ) {
            $('#_sale_price_dates_from').val( ( WCSC_ajax_data.startDate ).replace(' ', 'T' ) );
        }

        if ( WCSC_ajax_data.endDate !== null ) {
            $('#_sale_price_dates_to').val( ( WCSC_ajax_data.endDate ).replace(' ', 'T' ) );
        }
    });

})(jQuery);