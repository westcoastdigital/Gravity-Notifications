document.addEventListener('DOMContentLoaded', () => {
    // Fixed selector to match the actual input name
    const allFormsToggle = document.querySelector('input[name="gnt_use_all_forms"]');
    const assignmentWrapper = document.querySelector('.gnt-form-assignment');
    const addBtn = document.querySelector('.gnt-add-row');
    
    // To Email Type radio buttons
    const toEmailRadios = document.querySelectorAll('input[name="gnt_to_email_type"]');
    const toEmailEnterDiv = document.querySelector('.gnt-to-email-enter');
    const toEmailFieldDiv = document.querySelector('.gnt-to-email-field');
    
    let rowIndex = document.querySelectorAll('.gnt-repeater-row').length;

    function updateVisibility() {
        if (allFormsToggle && assignmentWrapper) {
            assignmentWrapper.style.display = allFormsToggle.checked ? 'none' : '';
        }
    }

    function updateToEmailVisibility() {
        const selectedType = document.querySelector('input[name="gnt_to_email_type"]:checked');
        
        if (selectedType && toEmailEnterDiv && toEmailFieldDiv) {
            if (selectedType.value === 'enter_email') {
                toEmailEnterDiv.style.display = 'block';
                toEmailFieldDiv.style.display = 'none';
            } else if (selectedType.value === 'field_id') {
                toEmailEnterDiv.style.display = 'none';
                toEmailFieldDiv.style.display = 'block';
            }
        }
    }

    if (allFormsToggle) {
        allFormsToggle.addEventListener('change', updateVisibility);
        updateVisibility(); // Initialize visibility on page load
        setTimeout(refreshMergeTags, 100);
    }

    // Initialize to email visibility
    updateToEmailVisibility();

    // Add event listeners to to email radio buttons
    toEmailRadios.forEach(radio => {
        radio.addEventListener('change', updateToEmailVisibility);
    });

    // Add new form row
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            const wrapper = this.closest('.gnt-repeater');
            const template = wrapper.querySelector('.gnt-repeater-row[style*="display:none"]');

            if (template) {
                const clone = template.cloneNode(true);
                
                // Update indices in the cloned template
                let html = clone.innerHTML.replace(/TEMPLATE_INDEX/g, rowIndex);
                clone.innerHTML = html;
                clone.setAttribute('data-index', rowIndex);
                clone.style.display = '';
                
                // Insert before the add button
                wrapper.insertBefore(clone, addBtn);
                rowIndex++;
            }
        });
    }

    // Use event delegation for all dynamic buttons and form changes
    document.body.addEventListener('click', function (e) {
        // Remove form row
        if (e.target.matches('.gnt-remove-row')) {
            e.preventDefault();
            const row = e.target.closest('.gnt-repeater-row');
            if (row && !row.style.display.includes('none')) { // Don't remove the template
                row.remove();
            }
        }
        
        // Add new condition
        if (e.target.matches('.gnt-add-condition')) {
            e.preventDefault();
            const conditionsContainer = e.target.parentElement;
            const template = conditionsContainer.querySelector('.gnt-condition-row[style*="display:none"]');
            
            if (template) {
                const newCondition = template.cloneNode(true);
                const formIndex = e.target.closest('.gnt-repeater-row').getAttribute('data-index');
                const conditionIndex = Date.now(); // Use timestamp for unique index
                
                // Update indices in the condition row
                let html = newCondition.innerHTML.replace(/CONDITION_TEMPLATE/g, conditionIndex);
                newCondition.innerHTML = html;
                newCondition.setAttribute('data-condition-index', conditionIndex);
                newCondition.style.display = '';
                
                // Insert before the template
                conditionsContainer.insertBefore(newCondition, template);
                
                // Initialize dynamic value field for the new condition row
                initializeDynamicValueFieldsForRow(newCondition);
            }
        }
        
        // Remove condition
        if (e.target.matches('.gnt-remove-condition')) {
            e.preventDefault();
            const conditionRow = e.target.closest('.gnt-condition-row');
            if (conditionRow && !conditionRow.style.display.includes('none')) { // Don't remove the template
                conditionRow.remove();
            }
        }
    });

    // Use event delegation for form select changes
    document.body.addEventListener('change', function (e) {
        if (e.target.matches('.gnt-form-select')) {
            const formId = e.target.value;
            const row = e.target.closest('.gnt-repeater-row');
            const fieldSelects = row.querySelectorAll('.gnt-field-select');
            
            if (!formId) {
                fieldSelects.forEach(select => {
                    select.innerHTML = '<option value="">Select a field</option>';
                });
                return;
            }
            
            // Create FormData object for the AJAX request
            const formData = new FormData();
            formData.append('action', 'gnt_get_form_fields');
            formData.append('form_id', formId);
            
            // Get nonce value
            const nonceField = document.getElementById('gf_notifications_meta_box_nonce');
            if (nonceField) {
                formData.append('nonce', nonceField.value);
            }
            
            // Make AJAX request using fetch
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let options = '<option value="">Select a field</option>';
                    data.data.forEach(field => {
                        const hasChoices = field.has_choices ? '1' : '0';
                        options += `<option value="${field.id}" data-field-type="${field.type}" data-has-choices="${hasChoices}">${escapeHtml(field.label)}</option>`;
                    });
                    fieldSelects.forEach(select => {
                        // Store current value to restore if it exists in new options
                        const currentValue = select.value;
                        select.innerHTML = options;
                        
                        // Try to restore previous selection if it still exists
                        if (currentValue) {
                            const optionExists = select.querySelector(`option[value="${currentValue}"]`);
                            if (optionExists) {
                                select.value = currentValue;
                            }
                        }
                    });
                } else {
                    console.error('Error loading form fields:', data.data);
                    fieldSelects.forEach(select => {
                        select.innerHTML = '<option value="">Error loading fields</option>';
                    });
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
                fieldSelects.forEach(select => {
                    select.innerHTML = '<option value="">Error loading fields</option>';
                });
            });

            // update merge tags after form change
            setTimeout(refreshMergeTags, 100);
        }
        
        // Handle field selection changes for dynamic value fields
        if (e.target.matches('.gnt-field-select')) {
            handleFieldSelectionChange(e.target);
        }
    });

    // Initialize dynamic value fields on page load
    initializeDynamicValueFields();

    // === DYNAMIC VALUE FIELD FUNCTIONS ===

    // Function to handle field selection changes
    function handleFieldSelectionChange(fieldSelect) {
        const conditionRow = fieldSelect.closest('.gnt-condition-row');
        const valueContainer = conditionRow.querySelector('.gnt-condition-value');
        const textInput = valueContainer.querySelector('.gnt-condition-text-value');
        const selectInput = valueContainer.querySelector('.gnt-condition-select-value');
        
        const selectedOption = fieldSelect.options[fieldSelect.selectedIndex];
        const hasChoices = selectedOption.getAttribute('data-has-choices') === '1';
        const fieldType = selectedOption.getAttribute('data-field-type');
        
        if (hasChoices && fieldSelect.value) {
            // Get form ID from the form select in the same repeater row
            const formRow = fieldSelect.closest('.gnt-repeater-row');
            const formSelect = formRow.querySelector('.gnt-form-select');
            const formId = formSelect.value;
            const fieldId = fieldSelect.value;
            
            if (formId && fieldId) {
                // Fetch field choices via AJAX
                fetchFieldChoices(formId, fieldId, selectInput, textInput);
            } else {
                // Show text input if no form/field selected
                showTextInput(textInput, selectInput);
            }
        } else {
            // Show text input for fields without choices
            showTextInput(textInput, selectInput);
        }
    }

    // Function to fetch field choices via AJAX
    function fetchFieldChoices(formId, fieldId, selectInput, textInput) {
        // Show loading state
        selectInput.innerHTML = '<option value="">Loading...</option>';
        selectInput.style.display = 'block';
        textInput.style.display = 'none';
        
        const formData = new FormData();
        formData.append('action', 'gnt_get_field_choices');
        formData.append('form_id', formId);
        formData.append('field_id', fieldId);
        
        // Get nonce value
        const nonceField = document.getElementById('gf_notifications_meta_box_nonce');
        if (nonceField) {
            formData.append('nonce', nonceField.value);
        }
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.has_choices) {
                // Populate select with choices
                let options = '<option value="">Select a value</option>';
                data.data.choices.forEach(function(choice) {
                    options += `<option value="${escapeHtml(choice.value)}">${escapeHtml(choice.text)}</option>`;
                });
                selectInput.innerHTML = options;
                
                // Copy any existing text value to select if it matches
                const currentTextValue = textInput.value;
                if (currentTextValue) {
                    selectInput.value = currentTextValue;
                }
                
                showSelectInput(selectInput, textInput);
            } else {
                // Field doesn't have choices, show text input
                showTextInput(textInput, selectInput);
            }
        })
        .catch(error => {
            console.error('Error fetching field choices:', error);
            // On error, fall back to text input
            showTextInput(textInput, selectInput);
        });
    }

    // Function to show text input and hide select
    function showTextInput(textInput, selectInput) {
        // Copy select value to text input if exists
        const selectValue = selectInput.value;
        if (selectValue && !textInput.value) {
            textInput.value = selectValue;
        }
        
        textInput.style.display = 'block';
        selectInput.style.display = 'none';
        
        // Update the name attribute to use the text input
        updateValueInputNames(textInput, selectInput, 'text');
    }

    // Function to show select input and hide text
    function showSelectInput(selectInput, textInput) {
        // Copy text value to select if it matches an option
        const textValue = textInput.value;
        if (textValue) {
            selectInput.value = textValue;
        }
        
        selectInput.style.display = 'block';
        textInput.style.display = 'none';
        
        // Update the name attribute to use the select input
        updateValueInputNames(textInput, selectInput, 'select');
    }

    // Function to update the name attributes so only the active input is submitted
    function updateValueInputNames(textInput, selectInput, activeType) {
        if (activeType === 'text') {
            // Text input should be submitted
            const originalName = textInput.getAttribute('name') || selectInput.getAttribute('name').replace('_select', '');
            textInput.setAttribute('name', originalName);
            selectInput.setAttribute('name', originalName + '_select_disabled');
        } else {
            // Select input should be submitted
            const originalName = selectInput.getAttribute('name').replace('_select', '');
            selectInput.setAttribute('name', originalName);
            textInput.setAttribute('name', originalName + '_text_disabled');
        }
    }

    // Function to initialize dynamic value fields on page load
    function initializeDynamicValueFields() {
        // Check existing condition rows on page load
        const visibleConditionRows = document.querySelectorAll('.gnt-condition-row:not([style*="display:none"])');
        visibleConditionRows.forEach(function(conditionRow) {
            const fieldSelect = conditionRow.querySelector('.gnt-field-select');
            
            // Trigger change event to set up the value field correctly
            if (fieldSelect && fieldSelect.value) {
                handleFieldSelectionChange(fieldSelect);
            }
        });
    }

    // Function to initialize dynamic value fields for a specific row (used when adding new conditions)
    function initializeDynamicValueFieldsForRow(conditionRow) {
        const valueContainer = conditionRow.querySelector('.gnt-condition-value');
        const textInput = valueContainer.querySelector('.gnt-condition-text-value');
        const selectInput = valueContainer.querySelector('.gnt-condition-select-value');
        
        // Ensure text input is shown by default for new rows
        showTextInput(textInput, selectInput);
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize merge tag clicks
    initializeMergeTagClicks();
    
    // Clone the existing Publish/Update button
    const originalButton = document.querySelector('#publish');

    if (originalButton) {
        const clone = originalButton.cloneNode(true);
        clone.id = 'publish-bottom';
        clone.innerText = originalButton.innerText;

        // Insert it below the custom fields box
        const target = document.querySelector('#gf_notification_settings'); // Replace with your actual custom fields container ID
        if (target) {
            const wrapper = document.createElement('div');
            wrapper.id = 'gnt-publish-button-wrapper';
            wrapper.appendChild(clone);
            target.parentNode.insertBefore(wrapper, target.nextSibling);

            // Ensure both buttons submit the form
            clone.addEventListener('click', function () {
                originalButton.click();
            });
        }
    }

    toggleDisplayHeaderPreview();
    toggleDisplayFooterPreview();
});


// Function to refresh merge tags when forms change
function refreshMergeTags() {
    const container = document.getElementById('gnt-merge-tags-container');
    if (!container) return;
    
    // Get current post ID from URL or hidden field
    const postId = getPostId();
    if (!postId) return;
    
    const formData = new FormData();
    formData.append('action', 'gnt_refresh_merge_tags');
    formData.append('post_id', postId);
    
    const nonceField = document.getElementById('gf_notifications_meta_box_nonce');
    if (nonceField) {
        formData.append('nonce', nonceField.value);
    }
    
    container.innerHTML = '<p>Loading merge tags...</p>';
    
    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            container.innerHTML = data.data.html;
            initializeMergeTagClicks();
        } else {
            container.innerHTML = '<p>Error loading merge tags.</p>';
        }
    })
    .catch(error => {
        console.error('Error refreshing merge tags:', error);
        container.innerHTML = '<p>Error loading merge tags.</p>';
    });
}

// Helper function to get post ID
function getPostId() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('post') || document.getElementById('post_ID')?.value;
}

// Function to make merge tags clickable
function initializeMergeTagClicks() {
    document.querySelectorAll('.gnt-merge-tag').forEach(tag => {
        tag.style.cursor = 'pointer';
        tag.style.padding = '2px 6px';
        tag.style.margin = '2px';
        tag.style.backgroundColor = '#f0f0f1';
        tag.style.border = '1px solid #c3c4c7';
        tag.style.borderRadius = '3px';
        tag.style.display = 'inline-block';
        
        tag.addEventListener('click', function() {
            const mergeTag = this.getAttribute('data-tag');
            const editor = tinymce.get('gnt_message_' + getPostId());
            
            if (editor && !editor.isHidden()) {
                editor.execCommand('mceInsertContent', false, mergeTag);
            } else {
                // Fallback to textarea
                const textarea = document.querySelector('textarea[name="gnt_message"]');
                if (textarea) {
                    const cursorPos = textarea.selectionStart;
                    const textBefore = textarea.value.substring(0, cursorPos);
                    const textAfter = textarea.value.substring(cursorPos);
                    textarea.value = textBefore + mergeTag + textAfter;
                    textarea.focus();
                    textarea.setSelectionRange(cursorPos + mergeTag.length, cursorPos + mergeTag.length);
                }
            }
        });
    });
}

// Function to refresh email field dropdown
function refreshEmailFieldDropdown() {
    const emailFieldSelect = document.querySelector('.gnt-email-field-select');
    if (!emailFieldSelect) return;
    
    const postId = getPostId();
    if (!postId) return;
    
    const formData = new FormData();
    formData.append('action', 'gnt_get_email_fields');
    formData.append('post_id', postId);
    
    const nonceField = document.getElementById('gf_notifications_meta_box_nonce');
    if (nonceField) {
        formData.append('nonce', nonceField.value);
    }
    
    const currentValue = emailFieldSelect.value;
    
    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            emailFieldSelect.innerHTML = data.data.html;
            // Try to restore previous selection
            if (currentValue) {
                emailFieldSelect.value = currentValue;
            }
        }
    })
    .catch(error => {
        console.error('Error refreshing email fields:', error);
    });
}

function toggleDisplayHeaderPreview() {
    const headerPreview = document.querySelector('.gnt-header-output');
    const toggleButton = document.querySelector('input[name="gnt_use_global_header"]');
    if (toggleButton && headerPreview) {
        // listen for changes to the toggle button
        toggleButton.addEventListener('change', function() {
            headerPreview.style.display = this.checked ? 'block' : 'none';
        });
    }
}

function toggleDisplayFooterPreview() {
    const footerPreview = document.querySelector('.gnt-footer-output');
    const toggleButton = document.querySelector('input[name="gnt_use_global_footer"]');
    if (toggleButton && footerPreview) {
        // listen for changes to the toggle button
        toggleButton.addEventListener('change', function() {
            footerPreview.style.display = this.checked ? 'block' : 'none';
        });
    }
}

