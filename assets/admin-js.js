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

    // Validates a single email or a comma-separated list of emails
    function isValidEmailList(value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return value.split(',').map(e => e.trim()).filter(Boolean).every(e => emailRegex.test(e));
    }

    function updateToEmailVisibility() {
        const selectedType = document.querySelector('input[name="gnt_to_email_type"]:checked');
        
        if (selectedType && toEmailEnterDiv && toEmailFieldDiv) {
            if (selectedType.value === 'enter_email') {
                toEmailEnterDiv.style.display = 'block';
                toEmailFieldDiv.style.display = 'none';
            } else if (selectedType.value === 'field_id') {
                const emailInput = toEmailEnterDiv.querySelector('input[name="gnt_to_email"]');
                if (emailInput && emailInput.value.trim() !== '' && !isValidEmailList(emailInput.value)) {
                    emailInput.value = '';
                }
                toEmailEnterDiv.style.display = 'none';
                toEmailFieldDiv.style.display = 'block';
            }
        }
    }

    if (allFormsToggle) {
        allFormsToggle.addEventListener('change', function() {
            updateVisibility();
            refreshMergeTags();
        });
        updateVisibility();
        refreshMergeTags(); // Initial render on page load
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
                
                let html = clone.innerHTML.replace(/TEMPLATE_INDEX/g, rowIndex);
                clone.innerHTML = html;
                clone.setAttribute('data-index', rowIndex);
                clone.style.display = '';
                
                wrapper.insertBefore(clone, addBtn);
                rowIndex++;
            }
        });
    }

    // Use event delegation for all dynamic buttons and form changes
    document.body.addEventListener('click', function (e) {
        if (e.target.matches('.gnt-remove-row')) {
            e.preventDefault();
            const row = e.target.closest('.gnt-repeater-row');
            if (row && !row.style.display.includes('none')) {
                row.remove();
                refreshMergeTags(); // Update when a row is removed
            }
        }
        
        if (e.target.matches('.gnt-add-condition')) {
            e.preventDefault();
            const conditionsContainer = e.target.parentElement;
            const template = conditionsContainer.querySelector('.gnt-condition-row[style*="display:none"]');
            
            if (template) {
                const newCondition = template.cloneNode(true);
                const formIndex = e.target.closest('.gnt-repeater-row').getAttribute('data-index');
                const conditionIndex = Date.now();
                
                let html = newCondition.innerHTML.replace(/CONDITION_TEMPLATE/g, conditionIndex);
                newCondition.innerHTML = html;
                newCondition.setAttribute('data-condition-index', conditionIndex);
                newCondition.style.display = '';
                
                conditionsContainer.insertBefore(newCondition, template);
                initializeDynamicValueFieldsForRow(newCondition);
            }
        }
        
        if (e.target.matches('.gnt-remove-condition')) {
            e.preventDefault();
            const conditionRow = e.target.closest('.gnt-condition-row');
            if (conditionRow && !conditionRow.style.display.includes('none')) {
                conditionRow.remove();
            }
        }

        if (e.target.matches('.gnt-manage-gf-notifications')) {
            e.preventDefault();
            openGfNotificationsModal(e.target);
        }

        if (e.target.matches('.gnt-modal-close') || e.target.matches('.gnt-modal-done')) {
            closeGfNotificationsModal();
        }
    });

    // Use event delegation for form select changes
    document.body.addEventListener('change', function (e) {
        if (e.target.matches('.gnt-form-select')) {
            const formId = e.target.value;
            const row = e.target.closest('.gnt-repeater-row');
            const fieldSelects = row.querySelectorAll('.gnt-field-select');

            const manageBtn = row.querySelector('.gnt-manage-gf-notifications');
            if (manageBtn) {
                if (formId) {
                    manageBtn.setAttribute('data-form-id', formId);
                    manageBtn.style.display = '';
                } else {
                    manageBtn.removeAttribute('data-form-id');
                    manageBtn.style.display = 'none';
                }
            }
            
            if (!formId) {
                fieldSelects.forEach(select => {
                    select.innerHTML = '<option value="">Select a field</option>';
                });
                refreshMergeTags(); // Update when a form is deselected
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'gnt_get_form_fields');
            formData.append('form_id', formId);
            
            const nonceField = document.getElementById('gf_notifications_meta_box_nonce');
            if (nonceField) {
                formData.append('nonce', nonceField.value);
            }

            // Kick off the field-population fetch
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
                        const currentValue = select.value;
                        select.innerHTML = options;
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

            // Refresh merge tags — debounced so rapid multi-row changes
            // collapse into a single request.
            refreshMergeTags();
        }
        
        if (e.target.matches('.gnt-field-select')) {
            handleFieldSelectionChange(e.target);
        }
    });

    // Initialize dynamic value fields on page load
    initializeDynamicValueFields();

    // === DYNAMIC VALUE FIELD FUNCTIONS ===

    function handleFieldSelectionChange(fieldSelect) {
        const conditionRow = fieldSelect.closest('.gnt-condition-row');
        const valueContainer = conditionRow.querySelector('.gnt-condition-value');
        const textInput = valueContainer.querySelector('.gnt-condition-text-value');
        const selectInput = valueContainer.querySelector('.gnt-condition-select-value');
        
        const selectedOption = fieldSelect.options[fieldSelect.selectedIndex];
        const hasChoices = selectedOption.getAttribute('data-has-choices') === '1';
        
        if (hasChoices && fieldSelect.value) {
            const formRow = fieldSelect.closest('.gnt-repeater-row');
            const formSelect = formRow.querySelector('.gnt-form-select');
            const formId = formSelect.value;
            const fieldId = fieldSelect.value;
            
            if (formId && fieldId) {
                fetchFieldChoices(formId, fieldId, selectInput, textInput);
            } else {
                showTextInput(textInput, selectInput);
            }
        } else {
            showTextInput(textInput, selectInput);
        }
    }

    function fetchFieldChoices(formId, fieldId, selectInput, textInput) {
        selectInput.innerHTML = '<option value="">Loading...</option>';
        selectInput.style.display = 'block';
        textInput.style.display = 'none';
        
        const formData = new FormData();
        formData.append('action', 'gnt_get_field_choices');
        formData.append('form_id', formId);
        formData.append('field_id', fieldId);
        
        const nonceField = document.getElementById('gf_notifications_meta_box_nonce');
        if (nonceField) {
            formData.append('nonce', nonceField.value);
        }
        
        fetch(ajaxurl, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.has_choices) {
                let options = '<option value="">Select a value</option>';
                data.data.choices.forEach(function(choice) {
                    options += `<option value="${escapeHtml(choice.value)}">${escapeHtml(choice.text)}</option>`;
                });
                selectInput.innerHTML = options;
                
                const currentTextValue = textInput.value;
                if (currentTextValue) {
                    selectInput.value = currentTextValue;
                }
                
                showSelectInput(selectInput, textInput);
            } else {
                showTextInput(textInput, selectInput);
            }
        })
        .catch(error => {
            console.error('Error fetching field choices:', error);
            showTextInput(textInput, selectInput);
        });
    }

    function showTextInput(textInput, selectInput) {
        const selectValue = selectInput.value;
        if (selectValue && !textInput.value) {
            textInput.value = selectValue;
        }
        textInput.style.display = 'block';
        selectInput.style.display = 'none';
        updateValueInputNames(textInput, selectInput, 'text');
    }

    function showSelectInput(selectInput, textInput) {
        const textValue = textInput.value;
        if (textValue) {
            selectInput.value = textValue;
        }
        selectInput.style.display = 'block';
        textInput.style.display = 'none';
        updateValueInputNames(textInput, selectInput, 'select');
    }

    function updateValueInputNames(textInput, selectInput, activeType) {
        if (activeType === 'text') {
            const originalName = textInput.getAttribute('name') || selectInput.getAttribute('name').replace('_select', '');
            textInput.setAttribute('name', originalName);
            selectInput.setAttribute('name', originalName + '_select_disabled');
        } else {
            const originalName = selectInput.getAttribute('name').replace('_select', '');
            selectInput.setAttribute('name', originalName);
            textInput.setAttribute('name', originalName + '_text_disabled');
        }
    }

    function initializeDynamicValueFields() {
        const visibleConditionRows = document.querySelectorAll('.gnt-condition-row:not([style*="display:none"])');
        visibleConditionRows.forEach(function(conditionRow) {
            const fieldSelect = conditionRow.querySelector('.gnt-field-select');
            if (fieldSelect && fieldSelect.value) {
                handleFieldSelectionChange(fieldSelect);
            }
        });
    }

    function initializeDynamicValueFieldsForRow(conditionRow) {
        const valueContainer = conditionRow.querySelector('.gnt-condition-value');
        const textInput = valueContainer.querySelector('.gnt-condition-text-value');
        const selectInput = valueContainer.querySelector('.gnt-condition-select-value');
        showTextInput(textInput, selectInput);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize merge tag clicks on page load
    initializeMergeTagClicks();
    
    // Clone the existing Publish/Update button
    const originalButton = document.querySelector('#publish');

    if (originalButton) {
        const clone = originalButton.cloneNode(true);
        clone.id = 'publish-bottom';
        clone.innerText = originalButton.innerText;

        const target = document.querySelector('#gf_notification_settings');
        if (target) {
            const wrapper = document.createElement('div');
            wrapper.id = 'gnt-publish-button-wrapper';
            wrapper.appendChild(clone);
            target.parentNode.insertBefore(wrapper, target.nextSibling);

            clone.addEventListener('click', function () {
                originalButton.click();
            });
        }
    }

    toggleDisplayHeaderPreview();
    toggleDisplayFooterPreview();
    clickOnEmailTag();

    // Close modal when clicking the overlay background
    document.body.addEventListener('click', function (e) {
        if (e.target.matches('.gnt-modal-overlay')) {
            closeGfNotificationsModal();
        }
    });

    // === GF NOTIFICATIONS MODAL ===

    function openGfNotificationsModal(btn) {
        const formId = btn.getAttribute('data-form-id');
        if (!formId) return;

        const modal       = document.getElementById('gnt-gf-notifications-modal');
        const listEl      = document.getElementById('gnt-gf-notifications-list');
        const formNameEl  = modal.querySelector('.gnt-modal-form-name');

        const row = btn.closest('.gnt-repeater-row');
        const select = row ? row.querySelector('.gnt-form-select') : null;
        formNameEl.textContent = select ? select.options[select.selectedIndex].text : '';

        listEl.innerHTML = '<span class="gnt-modal-loading"><span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span> Loading notifications…</span>';
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        const formData = new FormData();
        formData.append('action', 'gnt_get_gf_notifications');
        formData.append('form_id', formId);

        const nonceField = document.getElementById('gf_notifications_meta_box_nonce');
        if (nonceField) formData.append('nonce', nonceField.value);

        fetch(ajaxurl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderNotificationsList(listEl, formId, data.data.notifications);
                } else {
                    listEl.innerHTML = '<p class="gnt-modal-error">Error: ' + escapeHtml(data.data) + '</p>';
                }
            })
            .catch(() => {
                listEl.innerHTML = '<p class="gnt-modal-error">Failed to load notifications. Please try again.</p>';
            });
    }

    function closeGfNotificationsModal() {
        const modal = document.getElementById('gnt-gf-notifications-modal');
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    function renderNotificationsList(container, formId, notifications) {
        if (!notifications || notifications.length === 0) {
            container.innerHTML = '<p class="gnt-modal-empty">No default notifications found for this form.</p>';
            return;
        }

        let html = '<ul class="gnt-notifications-list">';
        notifications.forEach(notif => {
            const checkedAttr = notif.isActive ? 'checked' : '';
            const statusLabel = notif.isActive ? 'Active' : 'Inactive';
            const statusClass = notif.isActive ? 'gnt-notif-active' : 'gnt-notif-inactive';
            html += `
                <li class="gnt-notification-item" data-notification-id="${escapeHtml(String(notif.id))}" data-form-id="${escapeHtml(String(formId))}">
                    <label class="gnt-notif-label">
                        <span class="gnt-toggle gnt-notif-toggle">
                            <input type="checkbox" class="gnt-gf-notif-toggle" ${checkedAttr}
                                data-notification-id="${escapeHtml(String(notif.id))}"
                                data-form-id="${escapeHtml(String(formId))}">
                            <span class="gnt-slider"></span>
                        </span>
                        <span class="gnt-notif-name">${escapeHtml(notif.name)}</span>
                    </label>
                    <span class="gnt-notif-status ${statusClass}">${statusLabel}</span>
                    <span class="gnt-notif-saving" style="display:none;">
                        <span class="spinner is-active" style="float:none;width:16px;height:16px;margin:0 4px 0 0;"></span>Saving…
                    </span>
                </li>`;
        });
        html += '</ul>';
        container.innerHTML = html;

        container.querySelectorAll('.gnt-gf-notif-toggle').forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                handleNotificationToggle(this);
            });
        });
    }

    function handleNotificationToggle(checkbox) {
        const notificationId = checkbox.getAttribute('data-notification-id');
        const formId         = checkbox.getAttribute('data-form-id');
        const isActive       = checkbox.checked;
        const listItem       = checkbox.closest('.gnt-notification-item');
        const statusEl       = listItem.querySelector('.gnt-notif-status');
        const savingEl       = listItem.querySelector('.gnt-notif-saving');

        checkbox.disabled = true;
        savingEl.style.display = 'inline-flex';
        statusEl.style.display = 'none';

        const formData = new FormData();
        formData.append('action', 'gnt_toggle_gf_notification');
        formData.append('form_id', formId);
        formData.append('notification_id', notificationId);
        formData.append('is_active', isActive ? '1' : '0');

        const nonceField = document.getElementById('gf_notifications_meta_box_nonce');
        if (nonceField) formData.append('nonce', nonceField.value);

        fetch(ajaxurl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const active = data.data.is_active;
                    statusEl.textContent = active ? 'Active' : 'Inactive';
                    statusEl.className   = 'gnt-notif-status ' + (active ? 'gnt-notif-active' : 'gnt-notif-inactive');
                    checkbox.checked = active;
                } else {
                    checkbox.checked = !isActive;
                    alert('Could not save: ' + (data.data || 'Unknown error'));
                }
            })
            .catch(() => {
                checkbox.checked = !isActive;
                alert('Network error. Please try again.');
            })
            .finally(() => {
                checkbox.disabled = false;
                savingEl.style.display = 'none';
                statusEl.style.display = '';
            });
    }
});


// =============================================================================
// MERGE TAG REFRESH
// Debounced so rapid successive calls (multiple rows changing at once, page
// init, etc.) collapse into a single trailing fetch.
// An incrementing request-ID guard ensures stale in-flight responses never
// overwrite a newer result.
// =============================================================================
let _mergeTagDebounceTimer = null;
let _mergeTagRequestId     = 0;

function refreshMergeTags() {
    clearTimeout(_mergeTagDebounceTimer);
    _mergeTagDebounceTimer = setTimeout(_doRefreshMergeTags, 150);
}

function _doRefreshMergeTags() {
    const container = document.getElementById('gnt-merge-tags-container');
    if (!container) return;

    const nonce          = document.getElementById('gf_notifications_meta_box_nonce');
    const allFormsToggle = document.querySelector('input[name="gnt_use_all_forms"]');
    const useAllForms    = allFormsToggle && allFormsToggle.checked;

    // Collect unique non-empty form IDs from all visible repeater rows
    const formIds = [];
    if (!useAllForms) {
        document.querySelectorAll('.gnt-repeater-row').forEach(row => {
            if (row.style.display === 'none') return; // skip hidden template
            const select = row.querySelector('.gnt-form-select');
            if (select && select.value) {
                formIds.push(select.value);
            }
        });
    }

    // No forms selected — show placeholder immediately, no server round-trip
    if (!useAllForms && formIds.length === 0) {
        container.innerHTML = '<p><em>No forms assigned. Assign forms to see available merge tags.</em></p>';
        return;
    }

    container.innerHTML = '<p>Loading merge tags…</p>';

    const thisRequestId = ++_mergeTagRequestId;

    const formData = new FormData();
    formData.append('action', 'gnt_refresh_merge_tags');
    formData.append('use_all_forms', useAllForms ? '1' : '0');
    formIds.forEach(id => formData.append('form_ids[]', id));
    if (nonce) formData.append('nonce', nonce.value);

    fetch(ajaxurl, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (thisRequestId !== _mergeTagRequestId) return; // stale — discard

            if (data.success) {
                container.innerHTML = data.data.html;
                initializeMergeTagClicks();
            } else {
                container.innerHTML = '<p>Error loading merge tags.</p>';
            }
        })
        .catch(error => {
            if (thisRequestId !== _mergeTagRequestId) return;
            console.error('Error refreshing merge tags:', error);
            container.innerHTML = '<p>Error loading merge tags.</p>';
        });
}

// Helper function to get post ID
function getPostId() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('post') || document.getElementById('post_ID')?.value;
}

// Make merge tags clickable — inserts into TinyMCE or textarea
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
    
    fetch(ajaxurl, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            emailFieldSelect.innerHTML = data.data.html;
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
        toggleButton.addEventListener('change', function() {
            headerPreview.style.display = this.checked ? 'block' : 'none';
        });
    }
}

function toggleDisplayFooterPreview() {
    const footerPreview = document.querySelector('.gnt-footer-output');
    const toggleButton = document.querySelector('input[name="gnt_use_global_footer"]');
    if (toggleButton && footerPreview) {
        toggleButton.addEventListener('change', function() {
            footerPreview.style.display = this.checked ? 'block' : 'none';
        });
    }
}

function clickOnEmailTag() {
    const emailTags = document.querySelectorAll('.gnt-email-tag');
    if (!emailTags.length) return;
    const emailFieldSelect = document.querySelector('input[name="gnt_to_email_field_id"]');
    if (!emailFieldSelect) return;
    emailTags.forEach(tag => {
        tag.addEventListener('click', function() {
            emailFieldSelect.value = this.textContent.trim();
        });
    });
}