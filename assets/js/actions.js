

(function($) {
    $(document).on('ready', function(){

        if ( WCSC_ajax_data.stillValid && null !== WCSC_ajax_data.endDate ) {
            $('.wcsc-product-countdown-timer').FlipClock( ( WCSC_ajax_data.endDate - WCSC_ajax_data.currentDate ), {
                clockFace: 'DailyCounter',
                countdown: true,
                callbacks: {
                    stop: function() {
                        document.location.reload(true)
                    }
                }
            });
        }
    })

})(jQuery);
