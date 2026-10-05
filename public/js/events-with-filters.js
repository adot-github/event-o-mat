/**
 * events_with_filters – filter bar behaviour.
 * Adapted from db-custom/mks/public/assets/main.js (init_form_events).
 */
jQuery(function ($) {
    var $form = $('.events-with-filters-form');

    if (!$form.length) {
        return;
    }

    function check_for_updates(el, parent_selector) {
        var value = $(el).val();
        var $cnt = $(el).closest(parent_selector);

        if (value && value.length) {
            $cnt.addClass('has-values');
        } else {
            $cnt.removeClass('has-values');
        }
    }

    $('.select-container .clean-filter', $form).on('click touchstart', function () {
        var $cnt = $(this).closest('.select-container');
        $('select', $cnt).val(null).trigger('change');
        $cnt.removeClass('has-values');
    });

    $('.input-container .clean-filter', $form).on('click touchstart', function () {
        var $cnt = $(this).closest('.input-container');
        $('input', $cnt).val(null).trigger('keyup');
        $cnt.removeClass('has-values');
    });

    $('.input-container input', $form).on('keyup', function () {
        check_for_updates(this, '.input-container');
    }).trigger('keyup');

    var $selects = $('.select-container select.form-control', $form);

    // The theme may already have initialised Select2 on every <select>
    // (option theme_uses_select2) — re-initialising would drop its styling,
    // and its own clear button replaces our .clean-filter.
    $selects.each(function () {
        if ($(this).data('select2')) {
            $(this).closest('.select-container').addClass('theme-select2');
        } else {
            $(this).select2({});
        }
    });

    $selects.on('change', function () {
        check_for_updates(this, '.select-container');
    }).trigger('change');
});
