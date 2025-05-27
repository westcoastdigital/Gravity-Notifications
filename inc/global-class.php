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
        add_action('admin_head', array($this, 'add_custom_button_next_to_add_new'));
    }

    public function create_settings()
    {
        $page_title = __('Notification Settings', 'gnt');
        $menu_title = __('Notification Settings', 'gnt');
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
            <h1>
                <?= __('Global Notifications', 'gnt') ?>
                <a href="<?= admin_url('edit.php?post_type=gf-notifications') ?>" class="page-title-action" style="margin-left: 10px;">
                    <?= __('Notifications', 'gnt') ?>
                </a>
            </h1>
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
                'label' => __('Set Max Width?', 'gnt'),
                'id' => 'gnt_global_header_set_width',
                'type' => 'toggle',
                'section' => 'gnt_global_notifications_section',
            ),
            array(
				'label' => __('Width', 'gnt'),
				'id' => 'gnt_global_header_max_width',
				'type' => 'suffix',
                'suffix' => 'px',
                'default' => 640,
				'section' => 'gnt_global_notifications_section',
			),
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
            case 'checkbox':
                printf('<input %s id="%s" name="%s" type="checkbox" value="1">',
                    $value === '1' ? 'checked' : '',
                    $field['id'],
                    $field['id']
                );
                break;
            case 'toggle':
                printf('<p class="gnt-toggle-wrapper"><label>%s<br><label class="gnt-toggle"><input %s id="%s" name="%s" type="checkbox" value="1"><span class="gnt-slider"></span></label></label></p>',
                    $field['label'],
                    $value === '1' ? 'checked' : '',
                    $field['id'],
                    $field['id']
                );
                break;
            case 'suffix':
                $suffix = isset($field['suffix']) ? $field['suffix'] : '';
                $value = $value ? $value : $field['default'];
                printf(
                    '<div class="input-group suffix"><input name="%s" id="%s" type="number" placeholder="%s" value="%s" /><span class="input-group-addon ">%s</span></div>',
                    $field['id'],
                    $field['id'],
                    $placeholder,
                    $value,
                    esc_html($suffix),
                );
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

    public function add_custom_button_next_to_add_new() {
        $screen = get_current_screen();

        if ($screen->post_type !== 'gf-notifications' || $screen->base !== 'edit') {
            return;
        }

        $btn_text = __('Global Settings', 'gnt'); // Button Text
        $btn_link = admin_url('admin.php?page=gnt_global_notifications'); // Button Link
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                const addNewButton = document.querySelector('.page-title-action');

                if (addNewButton) {
                    const customButton = document.createElement('a');
                    customButton.href = "<?php echo esc_url($btn_link); ?>";;
                    customButton.className = 'page-title-action';
                    customButton.textContent = "<?php echo esc_js($btn_text); ?>";

                    addNewButton.after(customButton); // Inserts after the "Add New" button
                }
            });
        </script>
        <?php
    }
}

new GNT_GLOBAL_SETTINGS();
