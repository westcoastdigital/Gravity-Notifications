<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class GNT_SEND_EMAILS
{
    public function __construct()
    {
        add_action('init', array($this, 'setup_hooks'));
    }

    public function setup_hooks()
    {
        // Hook into all Gravity Forms submissions
        add_action('gform_after_submission', array($this, 'handle_form_submission'), 10, 2);
    }

    public function handle_form_submission($entry, $form)
    {
        $form_id = $form['id'];

        // Get all notification posts
        $args = array(
            'post_type' => 'gf-notifications',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );
        $notifications = get_posts($args);

        if (!$notifications) {
            return;
        }

        foreach ($notifications as $notification) {
            $id = $notification->ID;

            $all_forms = get_post_meta($id, '_gnt_use_all_forms', true);
            $assigned_forms = get_post_meta($id, '_gnt_assigned_forms', true);

            $should_trigger = false;

            if ($all_forms) {
                $should_trigger = true;
            } else {
                if (is_array($assigned_forms)) {
                    foreach ($assigned_forms as $assigned) {
                        if ((int) $assigned['form_id'] === (int) $form_id) {
                            $should_trigger = true;

                            // Optional: check conditional logic here
                            if (!empty($assigned['conditional_logic'])) {
                                $should_trigger = $this->evaluate_conditional_logic($entry, $form, $assigned['conditional_logic']);
                            }
                            break;
                        }
                    }
                }
            }

            if ($should_trigger) {
                $this->send_email($notification, $entry, $form);
            }
        }
    }

    public function send_email($notification, $entry, $form)
    {
        $id = $notification->ID;

        $type = get_post_meta($id, '_gnt_to_email_type', true) ?? 'enter_email';
        $email = '';
        if ($type === 'enter_email') {
            $email = get_post_meta($id, '_gnt_to_email', true);
        } else {
            // Get the field value (e.g., 'Email:2')
            $field = get_post_meta($id, '_gnt_to_email_field_id', true);

            // Ensure the field is not empty
            if (!empty($field)) {
                // Extract the numeric ID from 'Email:2' to get '2'
                $field_id = explode(":", $field)[1];
                // remove opening and closing curly braces if they exist
                $field_id = trim($field_id, '{}');

                // Use rgar() to get the value for the extracted field ID
                $email = rgar($entry, $field_id);
            }
        }

        // Continue with the rest of your code
        $from = get_post_meta($id, '_gnt_from_name', true) ?? get_bloginfo('name');
        $from_email = get_post_meta($id, '_gnt_from_email', true) ?? get_bloginfo('admin_email');
        $reply_to = get_post_meta($id, '_gnt_reply_to', true) ?? get_bloginfo('admin_email');
        $bcc = get_post_meta($id, '_gnt_bcc', true) ?? '';
        $subject = get_post_meta($id, '_gnt_subject', true) ?? 'New Form Submission';

        $global_header = get_post_meta($id, '_gnt_use_global_header', true);
        $global_footer = get_post_meta($id, '_gnt_use_global_footer', true);

        $max_width = get_option('gnt_global_header_max_width', 640);
        $set_width = get_option('gnt_global_header_set_width', false);

        $header = '';

        if($set_width) {
            $header .= '<table id="gnt-width-wraper" style="width: ' . esc_attr($max_width) . 'px; margin: 0 auto; border-collapse: collapse; border-spacing: 0; padding: 0;"><tbody><tr><td style="margin: 0; padding: 0; border: none; font-size: inherit; font-family: inherit; background: none;">';
        }

        if ($global_header) {
            $header .= get_option('gnt_global_header_content') ?? '';
        }

        if($header !== '') {
            $header = do_shortcode($header); // Convert shortcodes in the header
        }

        $footer = '';

        if ($global_footer) {
            $footer .= get_option('gnt_global_footer_content') ?? '';
        }
        if($footer !== '') {
            $footer = do_shortcode($footer); // Convert shortcodes in the footer
        }

        if($set_width) {
            $footer .= '</td></tr></tbody></table>';
        }

        $body = get_post_meta($id, '_gnt_message', true) ?? '';

        // Process WordPress shortcodes first (e.g. [parrys_logo])
        if ($body !== '') {
            $body = do_shortcode($body);
        }

        // Process ALL Gravity Forms merge tags in the body:
        //  - Standard field tags like {First Name:1.3}, {Email:2}
        //  - Any custom tags registered via gform_replace_merge_tags (e.g. {brochure_link})
        //  - GF's own built-in tags like {form_title}, {entry_id}, etc.
        // Note: merge tag processing is intentionally limited to the body only.
        // The global header/footer support shortcodes but not per-entry GF merge tags,
        // since they may be used across multiple forms with different field structures.
        if (class_exists('GFCommon')) {
            $body = GFCommon::replace_variables($body, $form, $entry, false, false, false, 'html');
        }

        $content = $header . $body . $footer;

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $headers[] = 'From: ' . $from . ' <' . $from_email . '>';
        $headers[] = 'Reply-To: ' . $reply_to;

        if (!empty($bcc)) {
            $headers[] = 'Bcc: ' . $bcc;
        }

        wp_mail($email, $subject, $content, $headers);
    }


    private function evaluate_conditional_logic($entry, $form, $logic)
    {
        if (empty($logic['conditions']) || !is_array($logic['conditions'])) {
            return true; // No conditions to evaluate = pass
        }

        $results = [];

        foreach ($logic['conditions'] as $condition) {
            $field_id = $condition['field_id'];
            $operator = $condition['operator'];
            $expected = $condition['value'];

            // Get the field object from the form
            $field = null;
            foreach ($form['fields'] as $form_field) {
                if ($form_field->id == $field_id) {
                    $field = $form_field;
                    break;
                }
            }

            // Get the actual value from the entry
            $actual = rgar($entry, $field_id);

            // Handle different field types and their value storage
            if ($field) {
                $actual = $this->normalize_field_value($actual, $field, $entry, $field_id);
            }

            $result = $this->compare_values($actual, $expected, $operator);
            $results[] = $result;
        }

        // Logic type determines overall outcome
        if ($logic['logic_type'] === 'any') {
            return in_array(true, $results, true);
        }

        // Default to 'all'
        return !in_array(false, $results, true);
    }

    /**
     * Normalize field values based on field type
     */
    private function normalize_field_value($actual, $field, $entry, $field_id)
    {
        switch ($field->type) {
            case 'checkbox':
                // Checkbox fields store multiple values as separate entries (field_id.1, field_id.2, etc.)
                $checkbox_values = [];
                foreach ($entry as $key => $value) {
                    if (strpos($key, $field_id . '.') === 0 && !empty($value)) {
                        $checkbox_values[] = $value;
                    }
                }
                return $checkbox_values;

            case 'multiselect':
                // Multi-select values are stored as JSON array or comma-separated
                if (is_string($actual) && (strpos($actual, '[') === 0 || strpos($actual, ',') !== false)) {
                    // Try to decode JSON first
                    $decoded = json_decode($actual, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $decoded;
                    }
                    // Fall back to comma-separated
                    return array_map('trim', explode(',', $actual));
                }
                return is_array($actual) ? $actual : [$actual];

            case 'select':
            case 'radio':
                // For select and radio, the stored value might be different from display text
                // Check if we need to map the value through choices
                if (isset($field->choices) && !empty($field->choices)) {
                    foreach ($field->choices as $choice) {
                        if ($choice['value'] === $actual) {
                            return $actual; // Value matches, return as is
                        }
                    }
                }
                return $actual;

            case 'name':
                // Name fields have sub-fields (first, last, etc.)
                if (strpos($field_id, '.') !== false) {
                    return rgar($entry, $field_id);
                }
                // If checking the whole name field, concatenate parts
                $name_parts = [];
                for ($i = 1; $i <= 6; $i++) { // Name field can have up to 6 parts
                    $part = rgar($entry, $field_id . '.' . $i);
                    if (!empty($part)) {
                        $name_parts[] = $part;
                    }
                }
                return implode(' ', $name_parts);

            case 'address':
                // Address fields have sub-fields
                if (strpos($field_id, '.') !== false) {
                    return rgar($entry, $field_id);
                }
                // If checking the whole address, concatenate parts
                $address_parts = [];
                $address_keys = ['.1', '.2', '.3', '.4', '.5', '.6']; // street, line2, city, state, zip, country
                foreach ($address_keys as $key) {
                    $part = rgar($entry, $field_id . $key);
                    if (!empty($part)) {
                        $address_parts[] = $part;
                    }
                }
                return implode(' ', $address_parts);

            case 'phone':
            case 'email':
            case 'website':
            case 'text':
            case 'textarea':
            case 'number':
            case 'hidden':
            default:
                return $actual;
        }
    }

    /**
     * Compare values based on operator
     */
    private function compare_values($actual, $expected, $operator)
    {
        // Handle array values (checkbox, multiselect)
        if (is_array($actual)) {
            switch ($operator) {
                case 'is':
                    return in_array($expected, $actual);
                case 'isnot':
                    return !in_array($expected, $actual);
                case 'contains':
                    foreach ($actual as $value) {
                        if (stripos($value, $expected) !== false) {
                            return true;
                        }
                    }
                    return false;
                case 'starts_with':
                    foreach ($actual as $value) {
                        if (stripos($value, $expected) === 0) {
                            return true;
                        }
                    }
                    return false;
                case 'ends_with':
                    foreach ($actual as $value) {
                        if (str_ends_with(strtolower($value), strtolower($expected))) {
                            return true;
                        }
                    }
                    return false;
                case 'greater_than':
                case 'less_than':
                    // For arrays, we'll check if any value meets the condition
                    foreach ($actual as $value) {
                        if ($operator === 'greater_than' && floatval($value) > floatval($expected)) {
                            return true;
                        }
                        if ($operator === 'less_than' && floatval($value) < floatval($expected)) {
                            return true;
                        }
                    }
                    return false;
            }
        }

        // Handle single values
        switch ($operator) {
            case 'is':
                return $actual == $expected;
            case 'isnot':
                return $actual != $expected;
            case 'greater_than':
                return floatval($actual) > floatval($expected);
            case 'less_than':
                return floatval($actual) < floatval($expected);
            case 'contains':
                return stripos($actual, $expected) !== false;
            case 'starts_with':
                return stripos($actual, $expected) === 0;
            case 'ends_with':
                return str_ends_with(strtolower($actual), strtolower($expected));
            default:
                return false;
        }
    }
}

new GNT_SEND_EMAILS();