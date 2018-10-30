<?php

if (!defined('ABSPATH')) exit;

define('DIEONDBERROR', true);
define('ECA_ANMELDUNG_TABLE', 'eca_anmeldung');

/**
 * Creates the registration table in the existing WP-database
 */
function eca_registration_setup_db()
{
    global $wpdb;

    $anmelde_table = $wpdb->prefix . ECA_ANMELDUNG_TABLE;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE " . $anmelde_table . " (
        anmelde_id mediumint(9) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        expire_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        event_id mediumint(9) DEFAULT -1 NOT NULL,
        data_as_json text DEFAULT '' NOT NULL,
        PRIMARY KEY  (anmelde_id)
    ) " . $charset_collate . ";";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

add_filter( 'cron_schedules', 'example_add_cron_interval' );

// function example_add_cron_interval( $schedules ) {
//     $schedules['five_seconds'] = array(
//         'interval' => 5,
//         'display'  => esc_html__( 'Every Five Seconds' ),
//     );

//     return $schedules;
// }

/**
 * Create a cron job (if doesn't exists) which calls every hour and action hook.
 * Returns true if a new cron job was created, false otherwise.
 */
function eca_registration_create_cron_job()
{
    if (!wp_next_scheduled('eca_delete_expired_registration')) {
        wp_schedule_event(time(), 'five_seconds', 'eca_delete_expired_registration');

        return true;
    }

    return false;
}


/**
 * Deletes all scheduled events for deleting expired registrations.
 */
function eca_registration_delete_cron_job()
{
    while ($timestamp = wp_next_scheduled('eca_delete_expired_registration')) {
        wp_unschedule_event($timestamp, 'eca_delete_expired_registration');
    }
}


/**
 * Adds a registration to database and schedule autodeletation when registraion is expired (after 24h)
 */
function eca_add_registration($data)
{
    global $wpdb;

    $wpdb->hide_errors();

    $anmelde_table = $wpdb->prefix . ECA_ANMELDUNG_TABLE;

    // Test
    $data['event_id'] = 5;

    // TODO: define the json object
    $json = $data['data'];

    $timestamp = date_create_immutable_from_format('Y-m-d H:i:s', current_time('mysql'));
    $expiration = $timestamp->add(new DateInterval('PT1M')); // 24h later as timestamp (current time)

    $wpdb->insert(
        $anmelde_table,
        array(
            'created_at' => $timestamp->format('Y-m-d H:i:s'),
            'expire_at' => $expiration->format('Y-m-d H:i:s'),
            'event_id' => $data['event_id'],
            'data_as_json' => json_encode($json)
        )
    );

    return $expiration;
}


/**
 * Deletes all expired registration in database when called
 */
function eca_delete_expired_registration()
{
    global $wpdb;

    $anmelde_table = $wpdb->prefix . ECA_ANMELDUNG_TABLE;

    $wpdb->query("DELETE FROM $anmelde_table WHERE expire_at <= CURRENT_TIME ;");
}