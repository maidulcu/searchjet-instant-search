<?php
namespace SearchJet\Admin;

class SearchUISettingsPage {
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function render() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Search Result UI Settings', 'searchjet-instant-search'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('searchjet_ui_options');
                do_settings_sections('searchjet-ui-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function register_settings() {
        register_setting('searchjet_ui_options', 'searchjet_ui_options', [
            'sanitize_callback' => [self::class, 'sanitize_all_options']
        ]);

        add_settings_section(
            'searchjet_ui_main',
            'Search Display Elements',
            function() {
                echo '<p>' . esc_html__('Choose which elements to show in live search results.', 'searchjet-instant-search') . '</p>';
            },
            'searchjet-ui-settings'
        );

        $fields = [
            'show_thumbnail' => 'Show product thumbnail',
            'show_price' => 'Show product price',
            'show_rating' => 'Show star rating and reviews',
            'show_stock' => 'Show stock status',
            'show_category' => 'Show product category or brand',
            'highlight_term' => 'Highlight search keywords',
            'view_all_link' => 'Show “View All Results” link'
        ];

        foreach ($fields as $key => $label) {
            add_settings_field(
                $key,
                $label,
                function() use ($key) {
                    $options = get_option('searchjet_ui_options', []);
                    echo '<input type="checkbox" name="searchjet_ui_options[' . esc_attr($key) . ']" value="1" ' . checked(1, $options[$key] ?? true, false) . ' />';
                },
                'searchjet-ui-settings',
                'searchjet_ui_main'
            );
        }

        add_settings_section(
            'searchjet_ui_styles',
            'Search Result Styles',
            function() {
                echo '<p>' . esc_html__('Customize fonts, colors, and layout of the instant search results.', 'searchjet-instant-search') . '</p>';
            },
            'searchjet-ui-settings'
        );

        $style_fields = [
            'font_size' => 'Font size (px)',
            'title_color' => 'Title text color',
            'highlight_color' => 'Highlighted term color',
            'background_color' => 'Background color',
            'border_radius' => 'Border radius (px)',
            'image_size' => 'Thumbnail image size (px)'
        ];

        foreach ($style_fields as $key => $label) {
            add_settings_field(
                $key,
                $label,
                function() use ($key) {
                    $options = get_option('searchjet_ui_options', []);
                    $value = esc_attr($options[$key] ?? '');
                    $type = (strpos($key, 'color') !== false) ? 'color' : 'text';
                    echo '<input type="' . $type . '" name="searchjet_ui_options[' . esc_attr($key) . ']" value="' . $value . '" class="regular-text" />';
                },
                'searchjet-ui-settings',
                'searchjet_ui_styles'
            );
        }
    }

    public static function sanitize_all_options($input) {
        $bool_fields = [
            'show_thumbnail',
            'show_price',
            'show_rating',
            'show_stock',
            'show_category',
            'highlight_term',
            'view_all_link'
        ];

        $style_fields = [
            'font_size',
            'title_color',
            'highlight_color',
            'background_color',
            'border_radius',
            'image_size'
        ];

        $sanitized = [];

        foreach ($bool_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? 1 : 0;
        }

        foreach ($style_fields as $field) {
            $sanitized[$field] = sanitize_text_field($input[$field] ?? '');
        }

        return $sanitized;
    }
}