/**
 * "Merkliste" like button (public/registration/_workshop.php).
 * Toggles a workshop like via AJAX; the visitor is identified by a cookie,
 * no login required. See classes/class-evtmgr-workshop-likes.php.
 */
jQuery(function ($) {
    if (typeof evtmgrLikes === 'undefined') {
        return;
    }

    $(document).on('click', '.js-workshop-like-button', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $button     = $(this);
        var workshopId   = $button.data('workshop-id');
        var eventUid     = $button.data('event-uid');

        if ($button.hasClass('is-loading') || !workshopId || !eventUid) {
            return;
        }

        $button.addClass('is-loading');

        $.post(evtmgrLikes.ajaxUrl, {
            action: 'evtmgr_toggle_like',
            nonce: evtmgrLikes.nonce,
            workshop_id: workshopId,
            event_uid: eventUid
        }).done(function (response) {
            if (response && response.success) {
                var liked = !!response.data.liked;
                $button.toggleClass('is-liked', liked);
                $button.attr('aria-pressed', liked ? 'true' : 'false');
            }
        }).always(function () {
            $button.removeClass('is-loading');
        });
    });
});
