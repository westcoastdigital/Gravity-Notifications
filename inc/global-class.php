<?php

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Settings Page: Global Notifications
class GNT_GLOBAL_SETTINGS
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'create_settings'), 100);
        add_action('admin_init', array($this, 'setup_sections'));
        add_action('admin_init', array($this, 'setup_fields'));
    }

    public function create_settings()
    {
        $page_title = __('Global Notifications', 'gnt');
        $menu_title = __('Global Notifications', 'gnt');
        $capability = 'manage_options';
        $slug = 'gnt_global_notifications';
        $callback = array($this, 'settings_content');

        // Adding the settings page as a submenu under 'gf_edit_forms'
        add_submenu_page(
            'gf_edit_forms',          // Parent menu slug
            $page_title,              // Page title
            $menu_title,              // Menu title
            $capability,              // Capability required to access the page
            $slug,                    // Unique slug for the page
            $callback                 // Callback function to display the page content
        );
    }

    public function settings_content()
    { ?>
        <div class="wrap">
            <h1><?= __('Global Notifications', 'gnt') ?></h1>
            <?php settings_errors(); ?>
            <section class="gnt-meta-box">
            <?= gnt_shortcodes_notice() ?>
            </section>
            <form method="POST" action="options.php">
                <?php
                settings_fields('gnt_global_notifications');
                do_settings_sections('gnt_global_notifications');
                submit_button();
                ?>
            </form>
        </div> <?php
    }

    public function setup_sections()
    {
        add_settings_section('gnt_global_notifications_section', '', array(), 'gnt_global_notifications');
    }

    public function setup_fields()
    {
        $fields = array(
            array(
                'label' => __('Global Header', 'gnt'),
                'id' => 'gnt_global_header_content',
                'type' => 'wysiwyg',
                'section' => 'gnt_global_notifications_section',
            ),
            array(
                'label' => __('Global Footer', 'gnt'),
                'id' => 'gnt_global_footer_content',
                'type' => 'wysiwyg',
                'section' => 'gnt_global_notifications_section',
            ),
        );
        foreach ($fields as $field) {
            add_settings_field($field['id'], $field['label'], array($this, 'field_callback'), 'gnt_global_notifications', $field['section'], $field);
            register_setting('gnt_global_notifications', $field['id']);
        }
    }

    public function field_callback($field)
    {
        $value = get_option($field['id']);
        $placeholder = '';
        if (isset($field['placeholder'])) {
            $placeholder = $field['placeholder'];
        }
        switch ($field['type']) {
            case 'wysiwyg':
                wp_editor($value, $field['id'], array('textarea_name' => $field['id']));
                break;
            default:
                printf(
                    '<input name="%1$s" id="%1$s" type="%2$s" placeholder="%3$s" value="%4$s" />',
                    $field['id'],
                    $field['type'],
                    $placeholder,
                    $value
                );
        }
        if (isset($field['desc'])) {
            if ($desc = $field['desc']) {
                printf('<p class="description">%s </p>', $desc);
            }
        }
    }
}

new GNT_GLOBAL_SETTINGS();
