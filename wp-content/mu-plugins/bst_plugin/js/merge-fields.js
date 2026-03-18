/**
 * BST Email Merge Fields JavaScript
 * Provides interactive merge field insertion for email templates
 */

function bstInsertAtCursor(textareaElement, text) {
    const tagName = textareaElement.tagName ? textareaElement.tagName.toLowerCase() : '';
    const inputType = textareaElement.type ? textareaElement.type.toLowerCase() : '';

    // Inputs like type="email" do not support selection; append to end.
    if (tagName === 'input' && inputType === 'email') {
        textareaElement.value = (textareaElement.value || '') + text;
        textareaElement.focus();
        return true;
    }

    try {
        const startPos = textareaElement.selectionStart;
        const endPos = textareaElement.selectionEnd;
        const beforeText = textareaElement.value.substring(0, startPos);
        const afterText = textareaElement.value.substring(endPos);
        
        textareaElement.value = beforeText + text + afterText;
        if (typeof textareaElement.setSelectionRange === 'function') {
            textareaElement.setSelectionRange(startPos + text.length, startPos + text.length);
        }
        textareaElement.focus();
        return true;
    } catch (e) {
        textareaElement.value = (textareaElement.value || '') + text;
        textareaElement.focus();
        return true;
    }
}

var bstLastFocusedField = null;

function bstDetermineTemplateTarget() {
    // Check last focused field FIRST (most reliable, explicitly tracked)
    if (bstLastFocusedField && bstLastFocusedField.tagName) {
        const tagName = bstLastFocusedField.tagName.toLowerCase();
        if (tagName === 'input' || tagName === 'textarea') {
            return bstLastFocusedField;
        }
    }
    
    // Then check current activeElement
    const focusedElement = document.activeElement;
    if (focusedElement && focusedElement.tagName) {
        const tagName = focusedElement.tagName.toLowerCase();
        const id = focusedElement.id;
        if ((tagName === 'input' || tagName === 'textarea') && id) {
            return focusedElement;
        }
    }

    // Then check TinyMCE (only if no input/textarea was focused)
    if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
        if (!tinymce.activeEditor.isHidden() && tinymce.activeEditor.hasFocus()) {
            return 'tinymce';
        }
    }
    
    // Fallback to subject field
    const subjectField = document.getElementById('title');
    if (subjectField) {
        return subjectField;
    }
    
    // Final fallback to content
    const contentField = document.getElementById('content');
    if (contentField) {
        return contentField;
    }
    
    return null;
}

function bstCopyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
    } else {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
        } catch (err) {
            console.error('Unable to copy to clipboard', err);
        }
        document.body.removeChild(textArea);
    }
}

function bstShowNotification(message) {
    const $notification = jQuery('<div class="bst-notification">' + message + '</div>');
    $notification.css({
        position: 'fixed',
        top: '32px',
        right: '20px',
        background: '#46b450',
        color: 'white',
        padding: '10px 15px',
        borderRadius: '3px',
        zIndex: 100000,
        fontSize: '12px',
        boxShadow: '0 2px 5px rgba(0,0,0,0.2)'
    });
    
    jQuery('body').append($notification);
    
    setTimeout(function() {
        $notification.fadeOut(300, function() {
            jQuery(this).remove();
        });
    }, 2000);
}

function bstInsertMergeField(fieldText) {
    const target = bstDetermineTemplateTarget();

    if (target === 'tinymce') {
        const editor = tinymce.activeEditor;
        const beforeContent = editor.getContent();
        editor.insertContent(fieldText);
        const afterContent = editor.getContent();

        if (afterContent !== beforeContent && afterContent.indexOf(fieldText) !== -1) {
            bstShowNotification('Inserted: ' + fieldText);
            return;
        }

        bstShowNotification('Could not insert into visual editor. Try Text mode or paste manually.');
        return;
    }
    
    if (target && target.tagName) {
        const inserted = bstInsertAtCursor(target, fieldText);
        if (inserted) {
            bstShowNotification('Inserted: ' + fieldText);
            return;
        }
    }
    
    bstCopyToClipboard(fieldText);
    bstShowNotification('Merge field copied to clipboard: ' + fieldText);
}

jQuery(document).ready(function($) {
    if (window.bstMergeFieldsInitialized) {
        return;
    }
    window.bstMergeFieldsInitialized = true;

    $(document).on('focusin', 'input, textarea', function() {
        bstLastFocusedField = this;
    });

    document.addEventListener('mousedown', function(e) {
        const item = e.target.closest && e.target.closest('.bst-merge-field-item');
        if (!item) {
            return;
        }

        if (window.bstLastMergeEventTs === e.timeStamp) {
            return;
        }
        window.bstLastMergeEventTs = e.timeStamp;

        e.preventDefault();
        e.stopPropagation();

        const fieldName = item.getAttribute('data-field');
        bstInsertMergeField('{' + fieldName + '}');
    }, true);

    // Initialize merge field picker
    initializeMergeFieldPicker();
    
    function initializeMergeFieldPicker() {
        // Handle category expand/collapse
        $('.bst-category-title').on('click', function() {
            const $category = $(this).closest('.bst-merge-category');
            const $fields = $category.find('.bst-category-fields');
            const $icon = $(this).find('.dashicons');
            
            $fields.toggleClass('collapsed');
            $icon.toggleClass('collapsed');
        });
        
        // Handle search
        $('#merge-field-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            filterMergeFields(searchTerm);
        });
        
        // Collapse all categories by default except first one
        $('.bst-merge-category').not(':first').find('.bst-category-fields').addClass('collapsed');
        $('.bst-merge-category').not(':first').find('.dashicons').addClass('collapsed');
    }
    
    function filterMergeFields(searchTerm) {
        $('.bst-merge-field-item').each(function() {
            const $item = $(this);
            const fieldName = $item.data('field').toLowerCase();
            const description = $item.data('description').toLowerCase();
            
            if (fieldName.includes(searchTerm) || description.includes(searchTerm)) {
                $item.removeClass('hidden');
            } else {
                $item.addClass('hidden');
            }
        });
        
        // Show/hide categories based on whether they have visible items
        $('.bst-merge-category').each(function() {
            const $category = $(this);
            const visibleItems = $category.find('.bst-merge-field-item:not(.hidden)').length;
            
            if (visibleItems > 0) {
                $category.show();
                // Expand category if search is active
                if (searchTerm) {
                    $category.find('.bst-category-fields').removeClass('collapsed');
                    $category.find('.dashicons').removeClass('collapsed');
                }
            } else {
                $category.hide();
            }
        });
    }
});

// Global function for conditional block insertion
function bstInsertConditional() {
    const fieldName = prompt('Enter the field name for the conditional block (e.g., guest2_first_name):');
    if (fieldName) {
        const conditionalBlock = '{#' + fieldName + '}\nContent to show when ' + fieldName + ' has a value\n{/' + fieldName + '}';
        
        const target = bstDetermineTemplateTarget();
        if (target === 'tinymce') {
            tinymce.activeEditor.insertContent('<p>' + conditionalBlock.replace(/\n/g, '<br>') + '</p>');
            return;
        }
        
        if (target && target.tagName) {
            bstInsertAtCursor(target, conditionalBlock);
            return;
        }
    }
}
