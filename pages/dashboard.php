<?php
$wp_load = dirname(__FILE__, 7) . '/wp-load.php';

if (!file_exists($wp_load)) {
    die('wp-load.php not found: ' . htmlspecialchars($wp_load, ENT_QUOTES, 'UTF-8'));
}

require_once $wp_load;

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/classes/class-evtmgr-events.php';
require_once dirname(__DIR__) . '/classes/class-evtmgr-options.php';
require_once dirname(__DIR__) . '/classes/class-evtmgr-wordings.php';

$cookie_event_uid       = 'current_event_uid';
$cookie_event_languages = 'current_event_languages';

$current_event_uid = '';

if (!empty($_COOKIE[$cookie_event_uid])) {
    $current_event_uid = sanitize_text_field(wp_unslash($_COOKIE[$cookie_event_uid]));
}

if ($current_event_uid !== '') {
    (new Evtmgr_Options())->sync_default_options($current_event_uid);
    (new Evtmgr_Wordings())->sync_default_wordings($current_event_uid);
}

$current_event_languages = '';

if (!empty($_COOKIE[$cookie_event_languages])) {
    $current_event_languages = sanitize_text_field(wp_unslash($_COOKIE[$cookie_event_languages]));
}

$event_obj = new Evtmgr_Events();
$events       = $event_obj->get_events_all('de');

if (!is_array($events)) {
    $events = array();
}

?>
<div class="container py-4 py-lg-5 event-dashboard-page">

<?php if (empty($events)) : ?>

        <div class="alert alert-warning" role="alert">
            Es wurden keine Events gefunden.
        </div>

    <?php else : ?>

        <div class="row g-4">

            <?php foreach ($events as $event) : ?>

                <?php
                $event_uid       = isset($event['event_uid']) ? (string) $event['event_uid'] : '';
                $event_languages = isset($event['str_event_languages']) ? (string) $event['str_event_languages'] : '';
                $event_id        = isset($event['id']) ? (string) $event['id'] : '';
                $email_from      = isset($event['str_event_email_from']) ? (string) $event['str_event_email_from'] : '';

                $is_active = $event_uid !== '' && $event_uid === $current_event_uid;

                $title = isset($event['str_event_name']) && (string) $event['str_event_name'] !== ''
                    ? (string) $event['str_event_name']
                    : $event_uid;

                $subtitle = isset($event['str_event_subtitle'])
                    ? (string) $event['str_event_subtitle']
                    : '';

                $card_classes = 'card h-100 shadow-sm';

                if ($is_active) {
                    $card_classes .= ' evtmgr-card-active';
                }
                ?>

                <div class="col-12 col-md-6 col-xl-4">
                    <div class="<?php echo esc_attr($card_classes); ?>">
                        <div class="card-body d-flex flex-column">

                            <?php if ($is_active) : ?>
                                <div class="mb-3">
                                    <span class="badge rounded-pill text-bg-primary">
                                        Aktuell ausgewählt
                                    </span>
                                </div>
                            <?php endif; ?>

                            <h2 class="h5 card-title fw-bold mb-3">
                                <?php echo esc_html($title); ?>
                            </h2>

                            <?php if (!empty($subtitle)) : ?>
                                <p class="card-subtitle mb-3">
                                    <?php echo esc_html($subtitle); ?>
                                </p>
                            <?php endif; ?>

                            <dl class="row small mb-0">
                                <dt class="col-5">Event UID</dt>
                                <dd class="col-7 mb-1"><?php echo esc_html($event_uid); ?></dd>

                                <?php if (!empty($event_id)) : ?>
                                    <dt class="col-5">Event ID</dt>
                                    <dd class="col-7 mb-1"><?php echo esc_html($event_id); ?></dd>
                                <?php endif; ?>

                                <?php if (!empty($event_languages)) : ?>
                                    <dt class="col-5">Sprachen</dt>
                                    <dd class="col-7 mb-1"><?php echo esc_html($event_languages); ?></dd>
                                <?php endif; ?>

                                <?php if (!empty($email_from)) : ?>
                                    <dt class="col-5">Absender-E-Mail</dt>
                                    <dd class="col-7 mb-1 text-break"><?php echo esc_html($email_from); ?></dd>
                                <?php endif; ?>
                            </dl>

                            <div class="mt-auto">
                                <button type="button"
                                        class="btn btn-primary js-open-event rounded-pill"
                                        data-event-uid="<?php echo esc_attr($event_uid); ?>"
                                        data-event-languages="<?php echo esc_attr($event_languages); ?>">
                                    Event aktivieren
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<script>
    (function () {
        function set_cookie(name, value, days) {
            const expires = new Date();

            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));

            document.cookie =
                encodeURIComponent(name) + '=' + encodeURIComponent(value) +
                '; expires=' + expires.toUTCString() +
                '; path=/; SameSite=Lax';
        }

        document.querySelectorAll('.js-open-event').forEach(function (button) {
            button.addEventListener('click', function () {
                const event_uid = button.getAttribute('data-event-uid') || '';
                const event_languages = button.getAttribute('data-event-languages') || '';

                if (!event_uid) {
                    return;
                }

                set_cookie('current_event_uid', event_uid, 365);
                set_cookie('current_event_languages', event_languages, 365);

                window.location.reload();
            });
        });
    })();
</script>
