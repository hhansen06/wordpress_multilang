/* global jQuery */
jQuery(function ($) {
    'use strict';

    // Make the language table rows sortable by the drag handle
    if ($('#multilang-sortable tbody').length) {
        $('#multilang-sortable tbody').sortable({
            handle: '.multilang-handle',
            axis: 'y',
            update: function () {
                // Re-index the hidden fields after reorder so the array order
                // submitted to PHP reflects the visual order.
                $('#multilang-sortable tbody tr').each(function (idx) {
                    $(this).find('[name]').each(function () {
                        var name = $(this).attr('name');
                        // Replace the numeric index in e.g. "languages[2][code]"
                        var newName = name.replace(/languages\[\d+\]/, 'languages[' + idx + ']');
                        $(this).attr('name', newName);
                    });
                });
            }
        });
    }
});
