(function($){
    'use strict';
    $(document).ready(function(){
        var $all = $('#rsn-select-all, .rsn-check-all');
        $all.on('change', function(){ var c=$(this).is(':checked'); $('input[name="rsn_ids[]"]').prop('checked',c); $all.prop('checked',c); });
        $(document).on('change','input[name="rsn_ids[]"]',function(){ var t=$('input[name="rsn_ids[]"]').length, c=$('input[name="rsn_ids[]"]:checked').length; $all.prop('checked',t===c); });
    });
})(jQuery);
