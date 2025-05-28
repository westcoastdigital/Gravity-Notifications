<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class GNT_CPT
{
    public function __construct()
    {
        add_action('init', array($this, 'register_post_type'));
        add_action('admin_menu', array($this, 'move_submenu'), 100);
        add_filter('enter_title_here', array($this, 'cpt_title'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add custom columns
        add_filter('manage_edit-gf-notifications_columns', array($this, 'add_gf_notifications_columns'));
        add_action('manage_gf-notifications_posts_custom_column', array($this, 'custom_gf_notifications_columns_content'), 10, 2);

        // Make columns sortable (optional)
        add_filter('manage_edit-gf-notifications_sortable_columns', array($this, 'gf_notifications_column_sortable'));

        add_filter('disable_months_dropdown', [$this, 'remove_date_filter'], 10, 2);
        add_action('restrict_manage_posts', array($this, 'add_filters'));
        add_action('parse_query', array($this, 'filter_query'));
        add_action('admin_footer', array($this, 'add_back_button'));
    }

    public function register_post_type()
    {
        $labels = array(
            'name' => 'Notifications',
            'singular_name' => 'Notification',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Notification',
            'edit_item' => 'Edit Notification',
            'new_item' => 'New Notification',
            'view_item' => 'View Notification',
            'search_items' => 'Search Notifications',
            'not_found' => 'No notifications found',
            'not_found_in_trash' => 'No notifications found in Trash',
            'menu_name' => 'Notifications',
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => false, // Prevent default menu placement
            'capability_type' => 'post',
            'supports' => array('title'), // Disable all default features
            'has_archive' => false,
            'rewrite' => false,
            'publicly_queryable' => false,
            'show_in_rest' => false,
        );

        register_post_type('gf-notifications', $args);
    }

    public function move_submenu()
    {
        add_submenu_page(
            'gf_edit_forms', // Parent slug (Gravity Forms)
            'Notifications', // Page title
            'Notifications', // Menu title
            'manage_options', // Capability
            'edit.php?post_type=gf-notifications' // Menu slug
        );
    }

    public function cpt_title($title)
    {
        $screen = get_current_screen($title);
        if ($screen->post_type === 'gf-notifications') {
            $title = 'Notification Label';
        }
        return $title;
    }

    public function enqueue_scripts()
    {
        $screen = get_current_screen();
        if ($screen->id === 'gf-notifications' || $screen->id === 'forms_page_gnt_global_notifications') {
            wp_enqueue_style('gf-notifications', GNT_URL . 'assets/admin-css.css', array(), GNT_VERSION);
            wp_enqueue_script('gf-notifications-admin', GNT_URL . 'assets/admin-js.js', array('jquery'), GNT_VERSION, true);
        }
    }

    // Add custom columns
    public function add_gf_notifications_columns($columns)
    {
        // Change the title column to "Notification"
        if (isset($columns['title'])) {
            $columns['title'] = __('Notification', 'gnt');
        }

        // Remove the default "date" column
        if (isset($columns['date'])) {
            unset($columns['date']);
        }

        $columns['gf_notifications_description'] = __('Description');
        $columns['gf_notifications_forms'] = __('Forms');
        $columns['gf_notifications_global_header'] = __('Global Header');
        $columns['gf_notifications_global_footer'] = __('Global Footer');
        $columns['gf_notifications_active'] = __('Active');

        return $columns;
    }

    // Display custom column content
    public function custom_gf_notifications_columns_content($column, $post_id)
    {
        switch ($column) {

            case 'gf_notifications_description':
                $description = get_post_meta($post_id, 'gnt_description', true);
                if ($description) {
                    echo esc_html($description);
                } else {
                    echo '-';
                }
                break;

            case 'gf_notifications_forms':
                $all_forms = get_post_meta($post_id, '_gnt_use_all_forms', true);
                if ($all_forms) {
                    echo __('All Forms', 'gnt');
                } else {
                    $forms = get_post_meta($post_id, '_gnt_assigned_forms', true);
                    if ($forms) {
                        $link_array = [];
                        foreach ($forms as $form) {
                            $form_id = $form['form_id'];
                            $forminfo = RGFormsModel::get_form($form_id);
                            if ($forminfo) {
                                $form_title = esc_html($forminfo->title);
                                $edit_url = admin_url('admin.php?page=gf_edit_forms&id=' . $form_id);
                                $link_array[] = '<a href="' . esc_url($edit_url) . '">' . $form_title . '</a>';
                            }
                        }
                        if (!empty($link_array)) {
                            echo implode(', ', $link_array);
                        } else {
                            echo __('No forms assigned', 'gnt');
                        }
                    } else {
                        echo __('No forms assigned', 'gnt');
                    }
                }
                break;

            case 'gf_notifications_global_header':
                $header = get_post_meta($post_id, '_gnt_use_global_header', true);
                if ($header) {
                    echo '<span class="dashicons dashicons-yes"></span>';
                } else {
                    echo '<span class="dashicons dashicons-no"></span>';
                }
                break;

            case 'gf_notifications_global_footer':
                // Display the global footer (example: stored in post meta)
                $footer = get_post_meta($post_id, '_gnt_use_global_footer', true);
                if ($footer) {
                    echo '<span class="dashicons dashicons-yes"></span>';
                } else {
                    echo '<span class="dashicons dashicons-no"></span>';
                }
                break;

            case 'gf_notifications_active':
                // Check if published
                $status = get_post_status($post_id);
                if ($status === 'publish') {
                    echo '<span class="dashicons dashicons-yes"></span>';
                } else {
                    echo '<span class="dashicons dashicons-no"></span>';
                }
                break;
        }
    }

    // Make columns sortable (optional)
    public function gf_notifications_column_sortable($columns)
    {
        $columns['gf_notifications_title'] = 'title';
        $columns['gf_notifications_active'] = 'gf_notifications_active';
        return $columns;
    }

    public function remove_date_filter($disable, $post_type)
    {
        if ($post_type === 'gf-notifications') {
            return true; // disables the dropdown
        }
        return $disable;
    }

    public function add_filters()
    {
        global $typenow;

        if ($typenow === 'gf-notifications') {
            // Filter by Active Status
            $selected_status = isset($_GET['gf_notifications_active']) ? $_GET['gf_notifications_active'] : '';
?>
            <select name="gf_notifications_active">
                <option value=""><?php _e('All Statuses', 'gnt'); ?></option>
                <option value="active" <?php selected($selected_status, 'active'); ?>><?php _e('Active (Published)', 'gnt'); ?></option>
                <option value="inactive" <?php selected($selected_status, 'inactive'); ?>><?php _e('Inactive (Draft)', 'gnt'); ?></option>
            </select>
            <?php

            // Filter by Form
            $selected_form = isset($_GET['gf_notification_form']) ? $_GET['gf_notification_form'] : '';
            $forms = GFAPI::get_forms(); // Gravity Forms API
            ?>
            <select name="gf_notification_form">
                <option value=""><?php _e('All Forms', 'gnt'); ?></option>
                <?php foreach ($forms as $form): ?>
                    <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($selected_form, $form['id']); ?>>
                        <?php echo esc_html($form['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php
        }
    }

    public function filter_query($query)
    {
        global $pagenow;

        if (
            is_admin() &&
            $pagenow === 'edit.php' &&
            isset($_GET['post_type']) &&
            $_GET['post_type'] === 'gf-notifications' &&
            $query->is_main_query()
        ) {
            // Filter by Active status
            if (!empty($_GET['gf_notifications_active'])) {
                $status = $_GET['gf_notifications_active'] === 'active' ? 'publish' : 'draft';
                $query->set('post_status', $status);
            }

            // Filter by assigned form
            if (!empty($_GET['gf_notification_form'])) {
                $form_id_filter = (int) $_GET['gf_notification_form'];

                add_filter('the_posts', function ($posts) use ($form_id_filter) {
                    $filtered = [];

                    foreach ($posts as $post) {
                        $use_all_forms = get_post_meta($post->ID, '_gnt_use_all_forms', true);
                        $assigned = get_post_meta($post->ID, '_gnt_assigned_forms', true);

                        if ($use_all_forms) {
                            $filtered[] = $post;
                            continue;
                        }

                        if (is_array($assigned)) {
                            foreach ($assigned as $item) {
                                if (isset($item['form_id']) && (int)$item['form_id'] === $form_id_filter) {
                                    $filtered[] = $post;
                                    break;
                                }
                            }
                        }
                    }

                    return $filtered;
                });
            }
        }
    }

    public function add_back_button()
    {
        $screen = get_current_screen();

        // Only show on gf-notifications edit/add screens
        if (!$screen || $screen->post_type !== 'gf-notifications' || $screen->base !== 'post') {
            return;
        }

        $list_url = admin_url('edit.php?post_type=gf-notifications');
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var $wrap = $('.wrap').first();

                if ($wrap.length) {
                    // Create the back button container
                    var backButtonHtml = '<div class="back-to-notifications-container">' +
                        '<a href="<?php echo esc_js(esc_url($list_url)); ?>" class="back-button">' +
                        '<span class="dashicons dashicons-undo"></span>' +
                        'Back to Notifications</a>' +
                        '</div>';

                    // Prepend to the beginning of .wrap
                    $wrap.prepend(backButtonHtml);
                }
            });
        </script>
<?php
    }
}

new GNT_CPT();
