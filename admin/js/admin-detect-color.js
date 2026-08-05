/* Detect WP admin color scheme by reading a known admin element's
   computed background and set the --evtmgr-bg CSS variable so the
   admin theme adapts to the user's chosen scheme. */
(function () {
    function setEvtmgrBg() {
        var el = document.querySelector('#adminmenu') || document.querySelector('#wpadminbar') || document.body;
        var style = window.getComputedStyle(el || document.body);
        var color = style && style.backgroundColor ? style.backgroundColor : '';
        if (!color || color === 'rgba(0, 0, 0, 0)') {
            color = window.getComputedStyle(document.body).backgroundColor || '#59524c';
        }
        try {
            document.documentElement.style.setProperty('--evtmgr-bg', color);
            document.body.style.background = 'var(--evtmgr-bg)';
        } catch (e) {
            // silent fail for older browsers
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setEvtmgrBg);
    } else {
        setEvtmgrBg();
    }
})();
