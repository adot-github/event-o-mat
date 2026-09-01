<?php
/**
 * Gemeinsamer Titel-Block für die Dashboard-Seiten.
 *
 * Zeigt den Namen des aktuell aktiven Events (ermittelt über die event_uid aus
 * dem Cookie) als H1. Der Block wird pro Request nur einmal ausgegeben, auch
 * wenn diese Datei mehrfach eingebunden wird.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!empty($GLOBALS['dashboard_active_event_title_rendered'])) {
    return;
}

$GLOBALS['dashboard_active_event_title_rendered'] = true;

if (!isset($active_event_name) || $active_event_name === '') {
    global $wpdb;

    $active_event_uid = '';

    if (!empty($_COOKIE['current_event_uid'])) {
        $active_event_uid = sanitize_text_field(wp_unslash($_COOKIE['current_event_uid']));
    }

    $active_event_name = '';

    if ($active_event_uid !== '' && isset($wpdb)) {
        $events_table = $wpdb->prefix . 'evtmgr_events';

        $active_event_name = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT str_event_name_de FROM {$events_table} WHERE event_uid = %s LIMIT 1",
                $active_event_uid
            )
        );
    }
}
?>

<?php if ($active_event_name !== '') : ?>
    <section class="m-0 mb-4">
        <h1 class="m-0"><?php echo esc_html($active_event_name); ?></h1>
    </section>
<?php endif; ?>
