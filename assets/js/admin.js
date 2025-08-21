/**
 * OVM Admin JavaScript
 */

(function($) {
    'use strict';

    let saveTimeouts = {};
    let currentTab = '';

    $(document).ready(function() {
        initializeEventHandlers();
        currentTab = $('.ovm-tab-content').data('tab');
    });

    /**
     * Initialize all event handlers
     */
    function initializeEventHandlers() {
        // Auto-save admin response with debouncing
        $(document).on('input', '.ovm-admin-response', function() {
            const $textarea = $(this);
            const commentId = $textarea.data('comment-id');
            
            // Clear existing timeout
            if (saveTimeouts[commentId]) {
                clearTimeout(saveTimeouts[commentId]);
            }
            
            // Show saving indicator
            const $indicator = $textarea.siblings('.ovm-save-indicator');
            $indicator.removeClass('saved error').addClass('saving');
            $textarea.addClass('saving');
            
            // Set new timeout for auto-save (1 second delay)
            saveTimeouts[commentId] = setTimeout(function() {
                saveAdminResponse($textarea, commentId);
            }, 1000);
        });

        // Toggle full content
        $(document).on('click', '.ovm-toggle-content', function(e) {
            e.preventDefault();
            const $this = $(this);
            const $content = $this.closest('.ovm-comment-content');
            const $truncated = $content.find('.ovm-content-truncated');
            const $full = $content.find('.ovm-content-full');
            
            if ($full.is(':visible')) {
                $full.slideUp();
                $truncated.slideDown();
                $this.text(ovm_ajax.strings.more || 'Meer');
            } else {
                $truncated.slideUp();
                $full.slideDown();
                $this.text('Minder');
            }
        });

        // Individual action buttons
        $(document).on('click', '.ovm-action-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const action = $btn.data('action');
            const commentId = $btn.data('comment-id');
            
            // Check if response is required for move to export
            if (action === 'move_to_export') {
                const $response = $btn.closest('tr').find('.ovm-admin-response');
                if (!$response.val().trim()) {
                    alert(ovm_ajax.strings.no_response_required);
                    $response.focus();
                    return;
                }
            }
            
            changeCommentStatus(commentId, action, $btn);
        });

        // Delete button
        $(document).on('click', '.ovm-delete-btn', function(e) {
            e.preventDefault();
            if (!confirm(ovm_ajax.strings.confirm_delete)) {
                return;
            }
            
            const $btn = $(this);
            const commentId = $btn.data('comment-id');
            deleteComment(commentId, $btn);
        });

        // Bulk actions
        $('#ovm-bulk-form').on('submit', function(e) {
            e.preventDefault();
            
            const action = $('#bulk-action-selector').val();
            if (!action) {
                alert('Selecteer een actie');
                return;
            }
            
            const checkedBoxes = $('input[name="comment_ids[]"]:checked');
            if (checkedBoxes.length === 0) {
                alert('Selecteer minimaal één opmerking');
                return;
            }
            
            if (action === 'delete' && !confirm(ovm_ajax.strings.confirm_bulk_delete)) {
                return;
            }
            
            // Check responses for move to export
            if (action === 'move_to_export') {
                let missingResponse = false;
                checkedBoxes.each(function() {
                    const $row = $(this).closest('tr');
                    const $response = $row.find('.ovm-admin-response');
                    if (!$response.val().trim()) {
                        missingResponse = true;
                        $response.addClass('error');
                        return false;
                    }
                });
                
                if (missingResponse) {
                    alert(ovm_ajax.strings.no_response_required);
                    return;
                }
            }
            
            handleBulkAction(action, checkedBoxes);
        });

        // Select all checkboxes
        $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('input[name="comment_ids[]"]').prop('checked', isChecked);
            $('#cb-select-all-1, #cb-select-all-2').prop('checked', isChecked);
        });

        // Page filter
        $('#ovm-page-filter').on('change', function() {
            const pageId = $(this).val();
            filterByPage(pageId);
        });

        // Comment editing
        $(document).on('click', '.ovm-edit-comment', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $row = $btn.closest('tr');
            const $display = $row.find('.ovm-content-display');
            const $edit = $row.find('.ovm-content-edit');
            const $controls = $row.find('.ovm-edit-controls');
            
            $display.hide();
            $controls.hide();
            $edit.show();
            $edit.find('.ovm-comment-edit').focus();
        });

        // Cancel comment editing
        $(document).on('click', '.ovm-cancel-edit', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $row = $btn.closest('tr');
            const $display = $row.find('.ovm-content-display');
            const $edit = $row.find('.ovm-content-edit');
            const $controls = $row.find('.ovm-edit-controls');
            
            $edit.hide();
            $display.show();
            $controls.show();
        });

        // Save comment content
        $(document).on('click', '.ovm-save-comment', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $row = $btn.closest('tr');
            const $textarea = $row.find('.ovm-comment-edit');
            const commentId = $textarea.data('comment-id');
            const content = $textarea.val();
            
            saveCommentContent(commentId, content, $btn);
        });

        // Import comments
        $('#ovm-import-comments').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('Weet je zeker dat je alle goedgekeurde comments wilt importeren? Dit kan even duren.')) {
                startImportProcess();
            }
        });
    }

    /**
     * Save admin response via AJAX
     */
    function saveAdminResponse($textarea, commentId) {
        const response = $textarea.val();
        const $indicator = $textarea.siblings('.ovm-save-indicator');
        
        $.ajax({
            url: ovm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ovm_save_response',
                nonce: ovm_ajax.nonce,
                comment_id: commentId,
                response: response
            },
            success: function(result) {
                $textarea.removeClass('saving');
                $indicator.removeClass('saving');
                
                if (result.success) {
                    $indicator.removeClass('error').addClass('saved');
                    setTimeout(function() {
                        $indicator.removeClass('saved');
                    }, 2000);
                } else {
                    $indicator.removeClass('saved').addClass('error');
                    console.error('Save error:', result.data.message);
                }
            },
            error: function() {
                $textarea.removeClass('saving');
                $indicator.removeClass('saving saved').addClass('error');
                console.error('AJAX error');
            }
        });
    }

    /**
     * Change comment status
     */
    function changeCommentStatus(commentId, action, $btn) {
        $btn.prop('disabled', true);
        
        $.ajax({
            url: ovm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ovm_change_status',
                nonce: ovm_ajax.nonce,
                comment_id: commentId,
                action_type: action
            },
            success: function(result) {
                if (result.success) {
                    // Remove row with fade effect
                    $btn.closest('tr').fadeOut(400, function() {
                        $(this).remove();
                        updateEmptyState();
                        updateCountBadges();
                    });
                } else {
                    alert(result.data.message);
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert(ovm_ajax.strings.error);
                $btn.prop('disabled', false);
            }
        });
    }

    /**
     * Delete comment
     */
    function deleteComment(commentId, $btn) {
        $btn.prop('disabled', true);
        
        $.ajax({
            url: ovm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ovm_delete_comment',
                nonce: ovm_ajax.nonce,
                comment_id: commentId
            },
            success: function(result) {
                if (result.success) {
                    $btn.closest('tr').fadeOut(400, function() {
                        $(this).remove();
                        updateEmptyState();
                        updateCountBadges();
                    });
                } else {
                    alert(result.data.message);
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert(ovm_ajax.strings.error);
                $btn.prop('disabled', false);
            }
        });
    }

    /**
     * Handle bulk action
     */
    function handleBulkAction(action, checkedBoxes) {
        const commentIds = [];
        checkedBoxes.each(function() {
            commentIds.push($(this).val());
        });
        
        showLoadingOverlay();
        
        $.ajax({
            url: ovm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ovm_bulk_action',
                nonce: ovm_ajax.nonce,
                bulk_action: action,
                comment_ids: commentIds,
                current_status: currentTab
            },
            success: function(result) {
                hideLoadingOverlay();
                
                if (result.success) {
                    // Remove affected rows
                    checkedBoxes.each(function() {
                        $(this).closest('tr').fadeOut(400, function() {
                            $(this).remove();
                        });
                    });
                    
                    setTimeout(function() {
                        updateEmptyState();
                        updateCountBadges();
                        $('#cb-select-all-1, #cb-select-all-2').prop('checked', false);
                    }, 500);
                    
                    // Show success message
                    showNotification(result.data.message, 'success');
                } else {
                    alert(result.data.message);
                }
            },
            error: function() {
                hideLoadingOverlay();
                alert(ovm_ajax.strings.error);
            }
        });
    }

    /**
     * Filter by page
     */
    function filterByPage(pageId) {
        showLoadingOverlay();
        
        $.ajax({
            url: ovm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ovm_filter_by_page',
                nonce: ovm_ajax.nonce,
                page_id: pageId,
                status: currentTab
            },
            success: function(result) {
                hideLoadingOverlay();
                
                if (result.success) {
                    $('#ovm-comments-list').html(result.data.html);
                    $('#cb-select-all-1, #cb-select-all-2').prop('checked', false);
                }
            },
            error: function() {
                hideLoadingOverlay();
                alert(ovm_ajax.strings.error);
            }
        });
    }

    /**
     * Update empty state
     */
    function updateEmptyState() {
        const $tbody = $('#ovm-comments-list');
        if ($tbody.find('tr').not('.no-items').length === 0) {
            if ($tbody.find('.no-items').length === 0) {
                $tbody.append(
                    '<tr class="no-items">' +
                    '<td colspan="7">Geen opmerkingen gevonden</td>' +
                    '</tr>'
                );
            }
        }
    }

    /**
     * Update count badges
     */
    function updateCountBadges() {
        // This would require an AJAX call to get updated counts
        // For now, we'll just decrement the visible count
        const $badge = $('.nav-tab-active .ovm-count-badge');
        if ($badge.length) {
            let count = parseInt($badge.text()) - 1;
            if (count > 0) {
                $badge.text(count);
            } else {
                $badge.remove();
            }
        }
    }

    /**
     * Show loading overlay
     */
    function showLoadingOverlay() {
        if ($('.ovm-loading-overlay').length === 0) {
            $('body').append(
                '<div class="ovm-loading-overlay">' +
                '<div class="ovm-loading-spinner"></div>' +
                '</div>'
            );
        }
        $('.ovm-loading-overlay').addClass('active');
    }

    /**
     * Hide loading overlay
     */
    function hideLoadingOverlay() {
        $('.ovm-loading-overlay').removeClass('active');
    }

    /**
     * Save comment content
     */
    function saveCommentContent(commentId, content, $btn) {
        const $row = $btn.closest('tr');
        const $indicator = $row.find('.ovm-comment-save-indicator');
        
        $btn.prop('disabled', true);
        $indicator.removeClass('saved error').addClass('saving');
        
        $.ajax({
            url: ovm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ovm_save_comment_content',
                nonce: ovm_ajax.nonce,
                comment_id: commentId,
                content: content
            },
            success: function(result) {
                $btn.prop('disabled', false);
                $indicator.removeClass('saving');
                
                if (result.success) {
                    // Update the display content
                    const $display = $row.find('.ovm-content-display');
                    const $edit = $row.find('.ovm-content-edit');
                    const $controls = $row.find('.ovm-edit-controls');
                    
                    const $truncated = $display.find('.ovm-content-truncated');
                    const $full = $display.find('.ovm-content-full');
                    const $toggle = $display.find('.ovm-toggle-content');
                    
                    // Update content
                    $truncated.text(result.data.truncated);
                    $full.text(result.data.content);
                    
                    // Show/hide toggle link
                    if (result.data.has_more) {
                        if ($toggle.length === 0) {
                            $display.append('<a href="#" class="ovm-toggle-content">Meer</a>');
                        }
                        $display.addClass('has-more');
                    } else {
                        $toggle.remove();
                        $display.removeClass('has-more');
                    }
                    
                    // Hide edit, show display
                    $edit.hide();
                    $display.show();
                    $controls.show();
                    
                    $indicator.removeClass('error').addClass('saved');
                    setTimeout(function() {
                        $indicator.removeClass('saved');
                    }, 2000);
                } else {
                    $indicator.removeClass('saved').addClass('error');
                    alert(result.data.message);
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                $indicator.removeClass('saving saved').addClass('error');
                alert(ovm_ajax.strings.error);
            }
        });
    }

    /**
     * Start import process
     */
    function startImportProcess() {
        // Disable import button
        const $importBtn = $('#ovm-import-comments');
        $importBtn.prop('disabled', true);
        $importBtn.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>Importeren...');
        
        // Show progress modal
        showImportModal();
        
        // Start first batch
        importBatch(0, 0, 0);
    }
    
    /**
     * Show import modal
     */
    function showImportModal() {
        const modalHtml = `
            <div id="ovm-import-modal" class="ovm-modal">
                <div class="ovm-modal-content">
                    <div class="ovm-modal-header">
                        <h2>Comments Importeren</h2>
                    </div>
                    <div class="ovm-modal-body">
                        <div class="ovm-progress-container">
                            <div class="ovm-progress-bar">
                                <div class="ovm-progress-fill" style="width: 0%;"></div>
                            </div>
                            <div class="ovm-progress-text">0%</div>
                        </div>
                        <div id="ovm-import-status">Voorbereiden...</div>
                        <div id="ovm-import-details"></div>
                    </div>
                    <div class="ovm-modal-footer" style="display: none;">
                        <button type="button" class="button button-primary" id="ovm-close-modal">Sluiten</button>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        
        // Close modal event
        $('#ovm-close-modal').on('click', function() {
            $('#ovm-import-modal').remove();
            location.reload(); // Refresh page to show new comments
        });
    }
    
    /**
     * Import batch of comments
     */
    function importBatch(offset, totalProcessed, totalComments) {
        $.ajax({
            url: ovm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ovm_import_comments',
                nonce: ovm_ajax.nonce,
                offset: offset,
                total_processed: totalProcessed,
                total_comments: totalComments
            },
            success: function(result) {
                if (result.success) {
                    const data = result.data;
                    
                    // Update progress
                    updateImportProgress(data);
                    
                    if (data.has_more) {
                        // Continue with next batch
                        setTimeout(function() {
                            importBatch(data.offset, data.total_processed, data.total_comments);
                        }, 500); // Small delay to prevent overwhelming the server
                    } else {
                        // Import completed
                        finishImport(data);
                    }
                } else {
                    handleImportError(result.data.message);
                }
            },
            error: function() {
                handleImportError('Er is een fout opgetreden tijdens het importeren.');
            }
        });
    }
    
    /**
     * Update import progress
     */
    function updateImportProgress(data) {
        const percentage = Math.min(data.progress_percentage || 0, 100);
        
        $('#ovm-import-modal .ovm-progress-fill').css('width', percentage + '%');
        $('#ovm-import-modal .ovm-progress-text').text(percentage + '%');
        $('#ovm-import-status').text(data.message);
        
        const details = `${data.total_processed} geïmporteerd, ${data.skipped_in_batch} overgeslagen in deze batch`;
        $('#ovm-import-details').text(details);
    }
    
    /**
     * Finish import process
     */
    function finishImport(data) {
        $('#ovm-import-status').html('<strong style="color: #46b450;">✓ ' + data.message + '</strong>');
        $('#ovm-import-modal .ovm-modal-footer').show();
        
        // Re-enable import button
        const $importBtn = $('#ovm-import-comments');
        $importBtn.prop('disabled', false);
        $importBtn.html('<span class="dashicons dashicons-download" style="margin-top: 3px;"></span>Importeer Comments');
        
        // Show success notification
        showNotification(data.message, 'success');
    }
    
    /**
     * Handle import error
     */
    function handleImportError(message) {
        $('#ovm-import-status').html('<strong style="color: #dc3232;">✗ Fout: ' + message + '</strong>');
        $('#ovm-import-modal .ovm-modal-footer').show();
        
        // Re-enable import button
        const $importBtn = $('#ovm-import-comments');
        $importBtn.prop('disabled', false);
        $importBtn.html('<span class="dashicons dashicons-download" style="margin-top: 3px;"></span>Importeer Comments');
        
        showNotification('Import gefaald: ' + message, 'error');
    }

    /**
     * Show notification
     */
    function showNotification(message, type) {
        const $notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.ovm-admin-wrap h1').after($notification);
        
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

})(jQuery);