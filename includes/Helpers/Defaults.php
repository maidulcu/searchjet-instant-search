<?php
namespace SearchJet\Helpers;

class Defaults {

    public static function options(): array {
        return [
            'searchjet_api_key' => '',
            'searchjet_project_id' => '',
            'searchjet_hosted_api_key' => '',
            'searchjet_search_mode' => 'js',
            'searchjet_disable_search_mode' => 0,
            'searchjet_delete_data_on_uninstall' => 0,
            'searchjet_automations_enabled' => 1,
            'searchjet_zr_threshold' => 5,
            'searchjet_zr_window' => 600,
            'searchjet_require_event_nonce' => 1,
            'searchjet_ui_options' => [
                'show_thumbnail' => 1,
                'show_price' => 1,
                'show_rating' => 1,
                'show_stock' => 1,
                'show_category' => 1,
                'show_excerpts' => 1,
                'highlight_term' => 1,
                'view_all_link' => 1,
            ],
            'searchjet_exclude_post_types' => '',
            'searchjet_include_status' => 'publish',
            'searchjet_exclude_sticky' => 0,
            'searchjet_exclude_protected' => 0,
            'searchjet_exclude_mime_types' => '',
            'searchjet_attributes' => '',
        ];
    }
}
