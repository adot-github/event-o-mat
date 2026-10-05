/**
 * "Merkliste" like button (public/registration/_workshop.php).
 * Toggles a workshop like via AJAX; the visitor is identified by a cookie,
 * no login required. See classes/class-evtmgr-workshop-likes.php.
 */
jQuery(function ($) {
    if (typeof evtmgrLikes === 'undefined') {
        return;
    }

    // Small pop-up after an offer was added to the Merkliste (not on removal).
    var toastTimer = null;

    // Place the pop-up right of the icon; if that does not fit the viewport,
    // below it (kept inside the viewport). Absolute = scrolls with the page.
    function positionToast($toast, button) {
        var gap      = 12;
        var margin   = 16;
        var rect     = button.getBoundingClientRect();
        var viewW    = document.documentElement.clientWidth;
        var scrollX  = window.pageXOffset;
        var scrollY  = window.pageYOffset;
        var width    = Math.min(360, viewW - 2 * margin);

        $toast.css({ width: width + 'px' });

        var height = $toast.outerHeight();

        if (rect.right + gap + width + margin <= viewW) {
            $toast.removeClass('is-below').addClass('is-right').css({
                left: (rect.right + gap + scrollX) + 'px',
                top:  (rect.top + rect.height / 2 - 24 + scrollY) + 'px'
            });
            $toast.css('--evtmgr-toast-arrow', '24px');
            return height;
        }

        var left = Math.max(margin, Math.min(rect.left + rect.width / 2 - 24, viewW - width - margin));

        $toast.removeClass('is-right').addClass('is-below').css({
            left: (left + scrollX) + 'px',
            top:  (rect.bottom + gap + scrollY) + 'px'
        });
        // Arrow points at the icon's centre.
        $toast.css('--evtmgr-toast-arrow', Math.max(12, rect.left + rect.width / 2 - left) + 'px');

        return height;
    }

    // [liked_events]: an offer removed from the Merkliste disappears from the
    // list; empty time blocks / days go too, then the empty notice shows.
    function removeFromLikedList($button) {
        var $list = $button.closest('.liked-events');

        if (!$list.length) {
            return;
        }

        var $item = $button.closest('.events-with-filters-accordion-item, .col');

        $item.fadeOut(200, function () {
            $item.remove();

            $list.find('.events-by-slot__block').each(function () {
                if (!$(this).find('.js-workshop-like-button').length) {
                    $(this).remove();
                }
            });
            $list.find('.events-by-slot__day-group').each(function () {
                if (!$(this).find('.events-by-slot__block').length) {
                    $(this).remove();
                }
            });

            if (!$list.find('.js-workshop-like-button').length) {
                $list.find('.liked-events__empty').removeClass('d-none');
            }
        });
    }

    function showLikedToast(button) {
        if (!evtmgrLikes.likedTitle) {
            return;
        }

        var $toast = $('#evtmgr-like-toast');

        if (!$toast.length) {
            $toast = $(
                '<div id="evtmgr-like-toast" class="evtmgr-like-toast" role="status" aria-live="polite">' +
                    '<button type="button" class="evtmgr-like-toast__close"></button>' +
                    '<p class="evtmgr-like-toast__title"></p>' +
                    '<p class="evtmgr-like-toast__text"></p>' +
                '</div>'
            ).appendTo('body');

            $toast.find('.evtmgr-like-toast__close')
                .attr('aria-label', evtmgrLikes.closeLabel || 'Schliessen')
                .html('&times;')
                .on('click', function () {
                    clearTimeout(toastTimer);
                    $toast.removeClass('is-visible');
                });
        }

        $toast.find('.evtmgr-like-toast__title').text(evtmgrLikes.likedTitle);
        $toast.find('.evtmgr-like-toast__text').text(evtmgrLikes.likedText || '');
        positionToast($toast, button);
        $toast.addClass('is-visible');

        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            $toast.removeClass('is-visible');
        }, 8000);
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

                if (liked) {
                    showLikedToast($button[0]);
                } else {
                    removeFromLikedList($button);
                }
            }
        }).always(function () {
            $button.removeClass('is-loading');
        });
    });
});
