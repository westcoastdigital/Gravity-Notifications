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
                        options += `<option value="${field.id}">${field.label}</option>`;
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
        }
    });
});