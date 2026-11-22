(function($) {
    'use strict';

    $(document).ready(function() {
        $('input[name="searchjet_webhook_provider"]').on('change', function() {
            var val = $(this).val();
            $('.sj-provider').hide();
            $('.sj-provider-' + val).show();
        }).filter(':checked').trigger('change');
    });
})(jQuery);
