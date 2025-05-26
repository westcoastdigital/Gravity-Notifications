<?php

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class GNT_CPT_FIELDS
{
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('wp_ajax_gnt_refresh_merge_tags', [$this, 'ajax_refresh_merge_tags']);
        add_action('edit_form_after_title', [$this, 'description_field']);
        add_action('save_post', [$this, 'save_meta_box']);
        add_action('wp_ajax_gnt_get_form_fields', [$this, 'ajax_get_form_fields']);
        add_action('wp_ajax_gnt_get_field_choices', [$this, 'ajax_get_field_choices']);
        add_action('admin_notices', [$this, 'maybe_show_errors']);
        add_filter('redirect_post_location', [$this, 'redirect_with_errors'], 10, 2);
    }

    public function register_meta_box()
    {
        add_meta_box(
            'gf_notification_settings',
            'Notification Settings',
            [$this, 'render_meta_box'], // Make sure this has the $post parameter
            'gf-notifications',         // <- must match your CPT name
            'normal',
            'default'
        );
    }

    public function render_meta_box($post)
    {
        if (! $post instanceof WP_Post) {
            return; // Guard clause just in case
        }

        wp_nonce_field('gf_notifications_meta_box', 'gf_notifications_meta_box_nonce');

        $fields = [
            'use_all_forms'     => get_post_meta($post->ID, '_gnt_use_all_forms', true),
            'to_email_type'     => get_post_meta($post->ID, '_gnt_to_email_type', true) ?: 'enter_email',
            'to_email'          => get_post_meta($post->ID, '_gnt_to_email', true),
            'to_email_field_id' => get_post_meta($post->ID, '_gnt_to_email_field_id', true),
            'from_name' => get_post_meta($post->ID, '_gnt_from_name', true) ?: get_bloginfo('name'),
            'from_email' => get_post_meta($post->ID, '_gnt_from_email', true) ?: get_option('admin_email'),
            'reply_to'          => get_post_meta($post->ID, '_gnt_reply_to', true),
            'bcc'               => get_post_meta($post->ID, '_gnt_bcc', true),
            'subject' => get_post_meta($post->ID, '_gnt_subject', true) ?: 'Email from ' . get_bloginfo('name'),
            'use_global_header' => get_post_meta($post->ID, '_gnt_use_global_header', true),
            'use_global_footer' => get_post_meta($post->ID, '_gnt_use_global_footer', true),
            'message'           => get_post_meta($post->ID, '_gnt_message', true),
        ];

        echo '<p class="gnt-toggle-wrapper"><label>Use on all forms?<br>
            <label class="gnt-toggle">
                <input type="checkbox" name="gnt_use_all_forms" value="1" ' . checked($fields['use_all_forms'], 1, false) . '>
                <span class="gnt-slider"></span>
            </label>
        </label></p>';

        // Get list of Gravity Forms
        if (class_exists('GFAPI')) {
            $forms = GFAPI::get_forms();
        } else {
            $forms = [];
        }

        if (empty($forms)) {
            echo '<p>No Gravity Forms found. Please install or activate Gravity Forms.</p>';
        } else {
            $assigned_forms = get_post_meta($post->ID, '_gnt_assigned_forms', true);
            if (!is_array($assigned_forms)) $assigned_forms = [];

            echo '<div class="gnt-form-assignment">';
            echo '<h4>Assign to Forms</h4>';
            echo '<div class="gnt-repeater">';

            if (!empty($assigned_forms)) {
                foreach ($assigned_forms as $index => $form_data) {
                    // Handle both old format (just form_id) and new format (array with conditions)
                    if (is_numeric($form_data)) {
                        $form_data = ['form_id' => $form_data];
                    }
                    echo $this->render_repeater_row($form_data, $forms, false, $index);
                }
            }

            // Empty template row (hidden)
            echo $this->render_repeater_row([], $forms, true, 'TEMPLATE_INDEX');

            echo '<button type="button" class="button gnt-add-row">Add Form</button>';
            echo '</div></div>';
        }

        // To Email Type Field (Radio)
        echo '<div class="gnt-to-email-wrapper">';
        echo '<h4>To Email Configuration</h4>';
        echo '<p><label>How would you like to set the recipient email?</label></p>';
        echo '<div class="gnt-to-email-type">';
        echo '<label><input type="radio" name="gnt_to_email_type" value="enter_email" ' . checked($fields['to_email_type'], 'enter_email', false) . '> Enter Email Address</label><br>';
        echo '<label><input type="radio" name="gnt_to_email_type" value="field_id" ' . checked($fields['to_email_type'], 'field_id', false) . '> Use Form Field ID</label>';
        echo '</div>';

        // To Email Field (conditional)
        echo '<div class="gnt-to-email-enter" style="display: ' . ($fields['to_email_type'] === 'enter_email' ? 'block' : 'none') . ';">';
        echo '<p><label>To Email:<br><input type="email" name="gnt_to_email" value="' . esc_attr($fields['to_email']) . '" class="widefat"></label></p>';
        echo '</div>';

        // To Email Field ID (conditional)
        echo '<div class="gnt-to-email-field" style="display: ' . ($fields['to_email_type'] === 'field_id' ? 'block' : 'none') . ';">';
        echo '<p><label>Email Field ID:<br><input type="text" name="gnt_to_email_field_id" value="' . esc_attr($fields['to_email_field_id']) . '" class="widefat" placeholder="e.g., Email:2"></label></p>';
        echo '<small>' . __('Enter the Field ID from your Gravity Form that contains the email address. EG: Email:2 to use email field', 'gnt') . '</small>';
        echo '<div class="email-wrap">';
            echo $this->render_merge_tags($post->ID, 'gnt-email-tag', true);
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // From Name Field
        echo '<p><label>From Name:<br><input type="text" name="gnt_from_name" value="' . esc_attr($fields['from_name']) . '" class="widefat"></label></p>';

        // From Email Field
        echo '<p><label>From Email:<br><input type="email" name="gnt_from_email" value="' . esc_attr($fields['from_email']) . '" class="widefat"></label></p>';

        // Reply To Field
        echo '<p><label>Reply To:<br><input type="email" name="gnt_reply_to" value="' . esc_attr($fields['reply_to']) . '" class="widefat"></label></p>';

        // BCC Field
        echo '<p><label>BCC:<br><input type="email" name="gnt_bcc" value="' . esc_attr($fields['bcc']) . '" class="widefat"></label></p>';

        // Subject Field
        echo '<p><label>Subject:<br><input type="text" name="gnt_subject" value="' . esc_attr($fields['subject']) . '" class="widefat"></label></p>';

        // Use Global Header Field
        echo '<p class="gnt-toggle-wrapper"><label>Use Global Header?<br>
            <label class="gnt-toggle">
                <input type="checkbox" name="gnt_use_global_header" value="1" ' . checked($fields['use_global_header'], 1, false) . '>
                <span class="gnt-slider"></span>
            </label>
        </label>
        ' . sprintf(
            __('This will use the global header set in the <a href="%s" target="_blank" rel="noopener noreferrer">settings page</a>, if not set it just sends the body below without the global header.', 'gnt'),
            esc_url(admin_url('admin.php?page=gnt_global_notifications'))
        ) . '
        </p>';

        // Message Field (wp_editor)
        echo '<div class="gnt-message-wrapper">';
        echo '<div class="left">';
        echo gnt_shortcodes_notice();
        echo '</div>';
        echo '<div class="right">';
        echo '<h2 style="padding: 0;">' . __('Available Merge Tags', 'gnt') . '</h2>';
        echo '<small>' . __('You can use a merge tag to to dynamically populate from form inputs. eg: {Name:1.3} would return the first name of a Name field', 'gnt') . '</small>';
        echo $this->render_merge_tags($post->ID);
        echo '</div>';
        echo '</div>';
        echo '<p><label>Message:<br>';
        wp_editor($fields['message'], 'gnt_message_' . $post->ID, ['textarea_name' => 'gnt_message', 'textarea_rows' => 10]);
        echo '</label></p>';

        // Use Global Footer Field
        echo '<p class="gnt-toggle-wrapper"><label>Use Global Footer?<br>
            <label class="gnt-toggle">
                <input type="checkbox" name="gnt_use_global_footer" value="1" ' . checked($fields['use_global_footer'], 1, false) . '>
                <span class="gnt-slider"></span>
            </label>
        </label>
        ' . sprintf(
            __('This will use the global footer set in the <a href="%s" target="_blank" rel="noopener noreferrer">settings page</a>, if not set it just sends the body above without the global footer.', 'gnt'),
            esc_url(admin_url('admin.php?page=gnt_global_notifications'))
        ) . '</p>';
    }

    private function render_merge_tags($post_id, $class = 'gnt-merge-tag', $email_only = false)
    {
        $assigned_forms = get_post_meta($post_id, '_gnt_assigned_forms', true);
        $use_all_forms = get_post_meta($post_id, '_gnt_use_all_forms', true);

        if (!class_exists('GFAPI')) {
            return '<p><em>Gravity Forms not available.</em></p>';
        }

        $forms_to_process = [];

        if ($use_all_forms) {
            $forms_to_process = GFAPI::get_forms();
        } elseif (is_array($assigned_forms) && !empty($assigned_forms)) {
            foreach ($assigned_forms as $form_data) {
                $form_id = is_array($form_data) ? $form_data['form_id'] : $form_data;
                if ($form_id) {
                    $form = GFAPI::get_form($form_id);
                    if ($form) {
                        $forms_to_process[] = $form;
                    }
                }
            }
        }

        if (empty($forms_to_process)) {
            return '<p><em>No forms assigned. Assign forms to see available merge tags.</em></p>';
        }

        $output = '<div class="gnt-merge-tags">';

        foreach ($forms_to_process as $form) {
            $output .= '<h4>Form: ' . esc_html($form['title']) . '</h4>';
            $output .= '<div class="gnt-merge-tag-list">';

            if (!empty($form['fields'])) {
                foreach ($form['fields'] as $field) {
                    if ($email_only && $field->type !== 'email') {
                        continue;
                    }

                    $merge_tag = '{' . $field->label . ':' . $field->id . '}';
                    $output .= '<span class="' . $class . '">' . esc_html($merge_tag) . '</span>';

                    // Add sub-fields (like name fields) only if not email_only
                    if (!$email_only && $field->type === 'name' && !empty($field->inputs)) {
                        foreach ($field->inputs as $input) {
                            if (!isset($input['isHidden']) || !$input['isHidden']) {
                                $sub_merge_tag = '{' . $field->label . ':' . $input['id'] . '}';
                                $output .= '<span class="gnt-merge-tag">' . esc_html($sub_merge_tag) . '</span>';
                            }
                        }
                    }
                }
            }

            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }


    public function ajax_refresh_merge_tags()
    {
        check_ajax_referer('gf_notifications_meta_box', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }

        $post_id = absint($_POST['post_id']);

        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        $html = $this->render_merge_tags($post_id);
        wp_send_json_success(['html' => $html]);
    }

    public function description_field($post)
    {
        if ($post->post_type == 'gf-notifications') {
            // Get the current value of the gnt_description field
            $gnt_description = esc_textarea(get_post_meta($post->ID, 'gnt_description', true));

            // Output the textarea for the gnt_description field
            echo '<label for="gnt_description"><h2 style="padding: 0;margin:5px 0;">' . __('Description', 'gnt') . ':</h2></label>';
            echo '<textarea name="gnt_description" id="gnt_description" placeholder="Description" rows="3" style="width: 100%;">' . $gnt_description . '</textarea>';
        }
    }

    private function render_repeater_row($data = [], $forms = [], $is_template = false, $index = 0)
    {
        $display = $is_template ? 'style="display:none;"' : '';
        $selected_form_id = isset($data['form_id']) ? $data['form_id'] : '';
        $conditional_logic = isset($data['conditional_logic']) ? $data['conditional_logic'] : [];
        $logic_type = isset($conditional_logic['logic_type']) ? $conditional_logic['logic_type'] : 'all';
        $conditions = isset($conditional_logic['conditions']) ? $conditional_logic['conditions'] : [];

        $row = "<div class='gnt-repeater-row' data-index='$index' $display>";

        // Form selection
        $row .= '<div class="gnt-form-select-wrapper">';
        $row .= '<label>Select Form:</label>';
        $row .= "<select name='gnt_assigned_forms[$index][form_id]' class='gnt-form-select'>";
        $row .= '<option value="">Select a form</option>';

        foreach ($forms as $form) {
            $selected = selected($selected_form_id, $form['id'], false);
            $row .= "<option value='{$form['id']}' $selected>{$form['title']}</option>";
        }

        $row .= '</select>';
        $row .= '<button type="button" class="button-link gnt-remove-row">Remove</button>';
        $row .= '</div>';

        // Conditional Logic Section
        $row .= '<div class="gnt-conditional-logic-wrapper">';
        $row .= '<h4>Conditional Logic</h4>';

        // Logic Type Selection
        $row .= '<div class="gnt-logic-type">';
        $row .= '<label>Send notification if:</label>';
        $row .= "<select name='gnt_assigned_forms[$index][conditional_logic][logic_type]'>";
        $row .= '<option value="all"' . selected($logic_type, 'all', false) . '>All Conditions Match</option>';
        $row .= '<option value="any"' . selected($logic_type, 'any', false) . '>Any Conditions Match</option>';
        $row .= '</select>';
        $row .= '</div>';

        // Conditions Repeater
        $row .= '<div class="gnt-conditions-repeater">';

        if (!empty($conditions)) {
            foreach ($conditions as $condition_index => $condition) {
                $row .= $this->render_condition_row($condition, $index, $condition_index, $selected_form_id);
            }
        }

        // Template condition row
        $row .= $this->render_condition_row([], $index, 'CONDITION_TEMPLATE', $selected_form_id, true);

        $row .= '<button type="button" class="button gnt-add-condition">Add Condition</button>';
        $row .= '</div>'; // Close conditions repeater
        $row .= '</div>'; // Close conditional logic wrapper

        $row .= '</div>'; // Close repeater row

        return $row;
    }

    private function render_condition_row($condition = [], $form_index = 0, $condition_index = 0, $form_id = '', $is_template = false)
    {
        $display = $is_template ? 'style="display:none;"' : '';
        $field_id = isset($condition['field_id']) ? $condition['field_id'] : '';
        $operator = isset($condition['operator']) ? $condition['operator'] : '';
        $value = isset($condition['value']) ? $condition['value'] : '';

        $row = "<div class='gnt-condition-row' data-condition-index='$condition_index' $display>";

        // Field selection (will be populated via AJAX when form is selected)
        $row .= '<div class="gnt-condition-field">';
        $row .= '<label>Field:</label>';
        $row .= "<select name='gnt_assigned_forms[$form_index][conditional_logic][conditions][$condition_index][field_id]' class='gnt-field-select'>";
        $row .= '<option value="">Select a field</option>';

        // Store selected field data for JS
        $selected_field_data = null;

        // If we have a form_id, populate the fields
        if ($form_id && class_exists('GFAPI')) {
            $form = GFAPI::get_form($form_id);
            if ($form && isset($form['fields'])) {
                foreach ($form['fields'] as $field) {
                    $selected = selected($field_id, $field->id, false);
                    $row .= "<option value='{$field->id}' $selected data-field-type='{$field->type}' data-has-choices='" . (isset($field->choices) && !empty($field->choices) ? '1' : '0') . "'>{$field->label}</option>";

                    // Store data for currently selected field
                    if ($field_id == $field->id) {
                        $selected_field_data = $field;
                    }
                }
            }
        }

        $row .= '</select>';
        $row .= '</div>';

        // Operator selection
        $row .= '<div class="gnt-condition-operator">';
        $row .= '<label>Operator:</label>';
        $row .= "<select name='gnt_assigned_forms[$form_index][conditional_logic][conditions][$condition_index][operator]'>";
        $operators = [
            'is' => 'is',
            'isnot' => 'is not',
            'greater_than' => 'greater than',
            'less_than' => 'less than',
            'contains' => 'contains',
            'starts_with' => 'starts with',
            'ends_with' => 'ends with'
        ];

        foreach ($operators as $op_value => $op_label) {
            $selected = selected($operator, $op_value, false);
            $row .= "<option value='$op_value' $selected>$op_label</option>";
        }
        $row .= '</select>';
        $row .= '</div>';

        // Value input/select (dynamic based on field type)
        $row .= '<div class="gnt-condition-value">';
        $row .= '<label>Value:</label>';

        // Determine if we should show select or text input
        $show_select = false;
        $field_choices = [];

        if ($selected_field_data && isset($selected_field_data->choices) && !empty($selected_field_data->choices)) {
            $show_select = true;
            $field_choices = $selected_field_data->choices;
        }

        // Text input (default)
        $text_display = $show_select ? 'style="display:none;"' : '';
        $row .= "<input type='text' name='gnt_assigned_forms[$form_index][conditional_logic][conditions][$condition_index][value]' value='" . esc_attr($value) . "' class='widefat gnt-condition-text-value' $text_display>";

        // Select input (for fields with choices)
        $select_display = $show_select ? '' : 'style="display:none;"';
        $row .= "<select name='gnt_assigned_forms[$form_index][conditional_logic][conditions][$condition_index][value_select]' class='widefat gnt-condition-select-value' $select_display>";
        $row .= '<option value="">Select a value</option>';

        if ($show_select) {
            foreach ($field_choices as $choice) {
                $choice_value = isset($choice['value']) ? $choice['value'] : $choice['text'];
                $choice_text = $choice['text'];
                $selected = selected($value, $choice_value, false);
                $row .= "<option value='" . esc_attr($choice_value) . "' $selected>" . esc_html($choice_text) . "</option>";
            }
        }

        $row .= '</select>';
        $row .= '</div>';

        $row .= '<button type="button" class="button-link gnt-remove-condition">Remove</button>';
        $row .= '</div>';

        return $row;
    }


    public function save_meta_box($post_id)
    {
        if (
            !isset($_POST['gf_notifications_meta_box_nonce']) ||
            !wp_verify_nonce($_POST['gf_notifications_meta_box_nonce'], 'gf_notifications_meta_box')
        ) {
            return;
        }

        $errors = [];

        // If not "Use on all forms", ensure at least one form is selected
        if (empty($_POST['gnt_use_all_forms']) && empty($_POST['gnt_assigned_forms'])) {
            $errors[] = 'Please assign at least one form if not using on all forms.';
        }

        // Ensure "To Email" or "Email Field ID" is set based on selection
        $to_type = $_POST['gnt_to_email_type'] ?? 'enter_email';
        if ($to_type === 'enter_email' && empty($_POST['gnt_to_email'])) {
            $errors[] = 'Please enter a recipient email address.';
        } elseif ($to_type === 'field_id' && empty($_POST['gnt_to_email_field_id'])) {
            $errors[] = 'Please provide a field ID for the recipient email.';
        }

        // Check required email fields
        if (empty($_POST['gnt_from_name'])) {
            $errors[] = 'From Name is required.';
        }
        if (empty($_POST['gnt_from_email'])) {
            $errors[] = 'From Email is required.';
        }
        if (empty($_POST['gnt_subject'])) {
            $errors[] = 'Subject is required.';
        }

        if (!empty($errors)) {
            set_transient("gnt_notification_errors_{$post_id}", $errors, 30);

            // Force post back to draft
            remove_action('save_post', [$this, 'save_meta_box']); // prevent loop
            wp_update_post([
                'ID' => $post_id,
                'post_status' => 'draft'
            ]);
            add_action('save_post', [$this, 'save_meta_box']); // re-add

            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = [
            '_gnt_use_all_forms'     => isset($_POST['gnt_use_all_forms']) ? 1 : 0,
            '_gnt_to_email_type'     => sanitize_text_field($_POST['gnt_to_email_type'] ?? 'enter_email'),
            '_gnt_to_email'          => sanitize_email($_POST['gnt_to_email'] ?? ''),
            '_gnt_to_email_field_id' => sanitize_text_field($_POST['gnt_to_email_field_id'] ?? ''),
            '_gnt_from_name'         => sanitize_text_field($_POST['gnt_from_name'] ?? ''),
            '_gnt_from_email'        => sanitize_email($_POST['gnt_from_email'] ?? ''),
            '_gnt_reply_to'          => sanitize_email($_POST['gnt_reply_to'] ?? ''),
            '_gnt_bcc'               => sanitize_email($_POST['gnt_bcc'] ?? ''),
            '_gnt_subject'           => sanitize_text_field($_POST['gnt_subject'] ?? ''),
            '_gnt_use_global_header' => isset($_POST['gnt_use_global_header']) ? 1 : 0,
            '_gnt_use_global_footer' => isset($_POST['gnt_use_global_footer']) ? 1 : 0,
            '_gnt_message'           => wp_kses_post($_POST['gnt_message'] ?? ''),
        ];

        foreach ($fields as $meta_key => $value) {
            update_post_meta($post_id, $meta_key, $value);
        }

        // Save assigned forms with conditional logic
        if (isset($_POST['gnt_assigned_forms']) && is_array($_POST['gnt_assigned_forms'])) {
            $cleaned_forms = [];

            foreach ($_POST['gnt_assigned_forms'] as $form_data) {
                if (empty($form_data['form_id'])) continue;

                $cleaned_form = [
                    'form_id' => absint($form_data['form_id'])
                ];

                // Process conditional logic if present
                if (isset($form_data['conditional_logic'])) {
                    $conditional_logic = $form_data['conditional_logic'];

                    $cleaned_form['conditional_logic'] = [
                        'logic_type' => sanitize_text_field($conditional_logic['logic_type'] ?? 'all')
                    ];

                    // Process conditions
                    if (isset($conditional_logic['conditions']) && is_array($conditional_logic['conditions'])) {
                        $cleaned_conditions = [];

                        foreach ($conditional_logic['conditions'] as $condition) {
                            if (empty($condition['field_id']) || empty($condition['operator'])) continue;

                            $cleaned_conditions[] = [
                                'field_id' => sanitize_text_field($condition['field_id']),
                                'operator' => sanitize_text_field($condition['operator']),
                                'value' => sanitize_text_field($condition['value'] ?? '')
                            ];
                        }

                        $cleaned_form['conditional_logic']['conditions'] = $cleaned_conditions;
                    }
                }

                $cleaned_forms[] = $cleaned_form;
            }

            update_post_meta($post_id, '_gnt_assigned_forms', $cleaned_forms);
        } else {
            delete_post_meta($post_id, '_gnt_assigned_forms');
        }

        // Check if description field is set and save it
        if (isset($_POST['gnt_description'])) {
            update_post_meta($post_id, 'gnt_description', sanitize_text_field($_POST['gnt_description']));
        }
    }

    // AJAX handler to get form fields
    public function ajax_get_form_fields()
    {
        check_ajax_referer('gf_notifications_meta_box', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }

        $form_id = absint($_POST['form_id']);

        if (!$form_id || !class_exists('GFAPI')) {
            wp_send_json_error('Invalid form ID or Gravity Forms not available');
        }

        $form = GFAPI::get_form($form_id);

        if (!$form || !isset($form['fields'])) {
            wp_send_json_error('Form not found');
        }

        $fields = [];
        foreach ($form['fields'] as $field) {
            $field_data = [
                'id' => $field->id,
                'label' => $field->label,
                'type' => $field->type,
                'has_choices' => isset($field->choices) && !empty($field->choices),
                'choices' => []
            ];

            // Add choices if they exist
            if (isset($field->choices) && !empty($field->choices)) {
                foreach ($field->choices as $choice) {
                    $field_data['choices'][] = [
                        'value' => isset($choice['value']) ? $choice['value'] : $choice['text'],
                        'text' => $choice['text']
                    ];
                }
            }

            $fields[] = $field_data;
        }

        wp_send_json_success($fields);
    }

    // New AJAX handler to get field choices
    public function ajax_get_field_choices()
    {
        check_ajax_referer('gf_notifications_meta_box', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }

        $form_id = absint($_POST['form_id']);
        $field_id = sanitize_text_field($_POST['field_id']);

        if (!$form_id || !$field_id || !class_exists('GFAPI')) {
            wp_send_json_error('Invalid parameters');
        }

        $form = GFAPI::get_form($form_id);

        if (!$form || !isset($form['fields'])) {
            wp_send_json_error('Form not found');
        }

        // Find the specific field
        $target_field = null;
        foreach ($form['fields'] as $field) {
            if ($field->id == $field_id) {
                $target_field = $field;
                break;
            }
        }

        if (!$target_field) {
            wp_send_json_error('Field not found');
        }

        $response = [
            'has_choices' => isset($target_field->choices) && !empty($target_field->choices),
            'choices' => []
        ];

        if ($response['has_choices']) {
            foreach ($target_field->choices as $choice) {
                $response['choices'][] = [
                    'value' => isset($choice['value']) ? $choice['value'] : $choice['text'],
                    'text' => $choice['text']
                ];
            }
        }

        wp_send_json_success($response);
    }


    public function redirect_with_errors($location, $post_id)
    {
        if (get_transient("gnt_notification_errors_{$post_id}")) {
            $location = add_query_arg('gnt_errors', 1, $location);
        }
        return $location;
    }

    public function maybe_show_errors()
    {
        global $post;

        if (!is_admin() || !isset($_GET['gnt_errors']) || empty($post->ID)) {
            return;
        }

        $errors = get_transient("gnt_notification_errors_{$post->ID}");
        if ($errors) {
            echo '<div class="notice notice-error"><ul>';
            foreach ($errors as $error) {
                echo "<li>$error</li>";
            }
            echo '</ul></div>';
            delete_transient("gnt_notification_errors_{$post->ID}");
        }
    }
}

new GNT_CPT_FIELDS();
