<?php

/**
 * Google Analytics settings page.
 */

namespace App;

/**
 * Get the saved Google Analytics settings, filled in with defaults.
 */
function google_analytics_settings(): array
{
    return wp_parse_args(get_option('theme_google_analytics', []), [
        'measurement_id' => '',
        'skip_staff' => false,
        'environments' => ['production'],
    ]);
}

/**
 * Get the WordPress environment types GA can load on, with their labels.
 */
function google_analytics_environments(): array
{
    return [
        'local' => __('Local', 'sage'),
        'development' => __('Development', 'sage'),
        'staging' => __('Staging', 'sage'),
        'production' => __('Production', 'sage'),
    ];
}

/**
 * Clean the Google Analytics settings before they're saved.
 *
 * An invalid measurement ID keeps the saved one and shows an error, so a typo
 * can't switch tracking off. Unknown environments are dropped, and ticking none
 * turns tracking off everywhere.
 *
 * @param  mixed  $input
 */
function sanitize_google_analytics_settings($input): array
{
    $input = (array) $input;
    $id = strtoupper(trim((string) ($input['measurement_id'] ?? '')));

    if ($id !== '' && ! preg_match('/^G-[A-Z0-9]+$/', $id)) {
        add_settings_error(
            'theme_google_analytics',
            'invalid_measurement_id',
            __('Enter a GA4 measurement ID, such as G-XXXXXXXXXX.', 'sage')
        );

        $id = google_analytics_settings()['measurement_id'];
    }

    $environments = array_intersect(
        array_keys(google_analytics_environments()),
        array_map('strval', (array) ($input['environments'] ?? []))
    );

    return [
        'measurement_id' => $id,
        'skip_staff' => ! empty($input['skip_staff']),
        'environments' => array_values($environments),
    ];
}

/**
 * Decide whether the current request should load Google Analytics.
 *
 * WordPress treats a site with no WP_ENVIRONMENT_TYPE as production.
 */
function should_load_google_analytics(array $settings): bool
{
    if ($settings['measurement_id'] === '') {
        return false;
    }

    if (! in_array(wp_get_environment_type(), $settings['environments'], true)) {
        return false;
    }

    return ! ($settings['skip_staff'] && current_user_can('edit_posts'));
}

/**
 * Add the Google Analytics page under Appearance.
 *
 * @return void
 */
add_action('admin_menu', function () {
    add_theme_page(
        __('Google Analytics', 'sage'),
        __('Google Analytics', 'sage'),
        'manage_options',
        'google-analytics',
        function () {
            echo '<div class="wrap"><h1>'.esc_html(get_admin_page_title()).'</h1>';

            // Only pages under Settings print save notices on their own.
            settings_errors();

            echo '<form action="options.php" method="post">';
            settings_fields('google-analytics');
            do_settings_sections('google-analytics');
            submit_button();
            echo '</form></div>';
        }
    );
});

/**
 * Register the Google Analytics settings and their fields.
 *
 * @return void
 */
add_action('admin_init', function () {
    register_setting('google-analytics', 'theme_google_analytics', [
        'type' => 'array',
        'sanitize_callback' => __NAMESPACE__.'\\sanitize_google_analytics_settings',
    ]);

    add_settings_section('google_analytics', '', function () {
        echo '<p>'.esc_html__('The GA4 tag loads on every front-end page once a measurement ID is set. Clear the ID to turn it off.', 'sage').'</p>';
    }, 'google-analytics');

    add_settings_field('ga_measurement_id', __('Measurement ID', 'sage'), function () {
        printf(
            '<input type="text" id="ga_measurement_id" name="theme_google_analytics[measurement_id]" value="%s" class="regular-text" placeholder="G-XXXXXXXXXX">',
            esc_attr(google_analytics_settings()['measurement_id'])
        );
    }, 'google-analytics', 'google_analytics', ['label_for' => 'ga_measurement_id']);

    add_settings_field('ga_skip_staff', __('Logged-in staff', 'sage'), function () {
        printf(
            '<label><input type="checkbox" id="ga_skip_staff" name="theme_google_analytics[skip_staff]" value="1"%s> %s</label>',
            checked(google_analytics_settings()['skip_staff'], true, false),
            esc_html__("Don't track users who can edit posts (authors, editors and administrators)", 'sage')
        );
    }, 'google-analytics', 'google_analytics');

    add_settings_field('ga_environments', __('Environments', 'sage'), function () {
        $saved = google_analytics_settings()['environments'];

        printf('<fieldset><legend class="screen-reader-text">%s</legend>', esc_html__('Environments', 'sage'));

        foreach (google_analytics_environments() as $environment => $label) {
            printf(
                '<label><input type="checkbox" name="theme_google_analytics[environments][]" value="%s"%s> %s</label><br>',
                esc_attr($environment),
                checked(in_array($environment, $saved, true), true, false),
                esc_html($label)
            );
        }

        printf(
            '<p class="description">%s</p></fieldset>',
            esc_html(sprintf(
                /* translators: %s is the environment type, such as staging */
                __('GA only loads on the environments ticked here. This site is %s.', 'sage'),
                wp_get_environment_type()
            ))
        );
    }, 'google-analytics', 'google_analytics');
});

/**
 * Load Google Analytics on the front end.
 *
 * @return void
 */
add_action('wp_enqueue_scripts', function () {
    $settings = google_analytics_settings();

    if (! should_load_google_analytics($settings)) {
        return;
    }

    $id = $settings['measurement_id'];

    wp_enqueue_script('google-analytics', "https://www.googletagmanager.com/gtag/js?id={$id}", [], null, [
        'strategy' => 'async',
    ]);

    wp_add_inline_script('google-analytics', sprintf(
        "window.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\ngtag('js', new Date());\ngtag('config', %s);",
        wp_json_encode($id)
    ), 'before');
});
