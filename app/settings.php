<?php

/**
 * Theme settings pages: Google Analytics and Site Settings.
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

/**
 * The social networks Appearance > Site Settings has a profile URL field
 * for, keyed by the option key, in the order the footer lists them.
 */
function social_networks(): array
{
    return [
        'facebook' => __('Facebook', 'sage'),
        'instagram' => __('Instagram', 'sage'),
        'linkedin' => __('LinkedIn', 'sage'),
        'x' => __('X', 'sage'),
        'youtube' => __('YouTube', 'sage'),
        'tiktok' => __('TikTok', 'sage'),
    ];
}

/**
 * Pull the token out of a Search Console verification value. Accepts the
 * token on its own or the whole <meta> tag Search Console hands out.
 */
function search_console_token(string $value): string
{
    if (preg_match('/content=["\']([^"\']*)["\']/', $value, $matches)) {
        $value = $matches[1];
    }

    return sanitize_text_field($value);
}

/**
 * Register the site settings. Registered on init so their defaults also
 * apply on the front end.
 *
 * @return void
 */
add_action('init', function () {
    register_setting('site_settings', 'site_social_links', [
        'type' => 'array',
        'default' => [],
        'sanitize_callback' => fn ($value) => array_filter(array_map(
            'esc_url_raw',
            array_intersect_key((array) $value, social_networks())
        )),
    ]);

    register_setting('site_settings', 'site_footer_text', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_setting('site_settings', 'site_search_console', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => fn ($value) => search_console_token((string) $value),
    ]);

    register_setting('site_settings', 'site_color_modes', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => fn ($value) => (bool) $value,
    ]);
});

/**
 * Add the Appearance > Site Settings page.
 *
 * @return void
 */
add_action('admin_menu', function () {
    add_theme_page(
        __('Site Settings', 'sage'),
        __('Site Settings', 'sage'),
        'manage_options',
        'site-settings',
        function () {
            echo '<div class="wrap"><h1>'.esc_html(get_admin_page_title()).'</h1>';

            settings_errors();

            echo '<form action="options.php" method="post">';
            settings_fields('site_settings');
            do_settings_sections('site-settings');
            submit_button();
            echo '</form></div>';
        }
    );
});

/**
 * Add the fields to the Appearance > Site Settings page.
 *
 * @return void
 */
add_action('admin_init', function () {
    add_settings_section('site_settings_footer', __('Footer', 'sage'), '', 'site-settings');
    add_settings_section('site_settings_social', __('Social profiles', 'sage'), function () {
        echo '<p>'.esc_html__('Profiles with a URL are linked in the footer.', 'sage').'</p>';
    }, 'site-settings');
    add_settings_section('site_settings_head', __('Site head', 'sage'), '', 'site-settings');

    add_settings_field('site_footer_text', __('Footer text', 'sage'), function () {
        printf(
            '<input name="site_footer_text" id="site_footer_text" type="text" class="large-text" value="%s" placeholder="%s">'
            .'<p class="description">%s</p>',
            esc_attr(get_option('site_footer_text')),
            esc_attr('© {year} '.get_bloginfo('name')),
            esc_html__('{year} becomes the current year. Leave it empty for the placeholder text.', 'sage')
        );
    }, 'site-settings', 'site_settings_footer', ['label_for' => 'site_footer_text']);

    $links = (array) get_option('site_social_links');

    foreach (social_networks() as $network => $label) {
        add_settings_field("site_social_links_{$network}", $label, function () use ($network, $links) {
            printf(
                '<input name="site_social_links[%1$s]" id="site_social_links_%1$s" type="url" class="regular-text code" value="%2$s" placeholder="https://">',
                esc_attr($network),
                esc_attr($links[$network] ?? '')
            );
        }, 'site-settings', 'site_settings_social', ['label_for' => "site_social_links_{$network}"]);
    }

    add_settings_field('site_search_console', __('Search Console', 'sage'), function () {
        printf(
            '<input name="site_search_console" id="site_search_console" type="text" class="regular-text code" value="%s">'
            .'<p class="description">%s</p>',
            esc_attr(get_option('site_search_console')),
            esc_html__('The HTML tag verification code. Pasting the whole <meta> tag works too.', 'sage')
        );
    }, 'site-settings', 'site_settings_head', ['label_for' => 'site_search_console']);

    add_settings_field('site_color_modes', __('Colour modes', 'sage'), function () {
        printf(
            '<label><input name="site_color_modes" id="site_color_modes" type="checkbox" value="1" %s> %s</label>',
            checked(get_option('site_color_modes'), true, false),
            esc_html__('Let visitors switch between light and dark mode', 'sage')
        );
    }, 'site-settings', 'site_settings_head');
});

/**
 * Print the Search Console verification tag.
 *
 * @link https://support.google.com/webmasters/answer/9008080
 *
 * @return void
 */
add_action('wp_head', function () {
    $token = get_option('site_search_console');

    if ($token) {
        printf('<meta name="google-site-verification" content="%s">'."\n", esc_attr($token));
    }
}, 1);

/**
 * Set Bootstrap's colour mode before the page paints, so a dark mode visitor
 * doesn't see a flash of the light theme. resources/js/color-mode.js handles
 * the switch and reads the same localStorage key.
 *
 * @link https://getbootstrap.com/docs/5.3/customize/color-modes/
 *
 * @return void
 */
add_action('wp_head', function () {
    if (! get_option('site_color_modes')) {
        return;
    }

    wp_print_inline_script_tag(<<<'JS'
    (() => {
      let mode = 'auto';
      try { mode = localStorage.getItem('color-mode') || 'auto'; } catch (e) {}
      const dark = mode === 'dark' || (mode === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
      document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    })();
    JS);
}, 1);
