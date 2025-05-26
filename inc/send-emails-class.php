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
                // var_dump($field_id); // This should output '2'

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

        $header = '';
        if ($global_header) {
            $header = get_option('gnt_global_header_content') ?? '';
        }

        $footer = '';
        if ($global_footer) {
            $footer = get_option('gnt_global_footer_content') ?? '';
        }

        $body = get_post_meta($id, '_gnt_message', true) ?? '';
        if($body !== '') {
            $body = do_shortcode($body); // Convert shortcodes in the body
        }

        // Replace merge tags like {Label:1.3} or {Email:2}
        preg_match_all('/{([^:}]+):([\d.]+)}/', $body, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $full_tag = $match[0];       // e.g., {First Name:1.3}
            $label = $match[1];          // e.g., First Name (not used here)
            $field_id = $match[2];       // e.g., 1.3 or 2

            $replacement = rgar($entry, $field_id) ?? '';  // Use rgar to get nested keys safely
            $body = str_replace($full_tag, $replacement, $body);
        }

        $content = $header . $body . $footer;

        // Replace merge tags like {Name:1} with actual field values
        foreach ($entry as $key => $value) {
            if (is_numeric($key)) {
                $field_label = rgar($form['fields'][$key] ?? [], 'label') ?: $key;
                $content = str_replace('{' . $field_label . ':' . $key . '}', $value, $content);
            }
        }

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
