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

        // Flag toggle button
        $(document).on('click', '.ovm-flag-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const commentId = $btn.data('comment-id');
            toggleFlag(commentId, $btn);
        });

        // Individual action buttons
        $(document).on('click', '.ovm-action-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const action = $btn.data('action');
            const commentId = $btn.data('comment-id');
            
            // Special handling for delete_wp_comment
            if (action === 'delete_wp_comment') {
                // Confirmation is handled in the onclick attribute
                changeCommentStatus(commentId, action, $btn);
                return;
            }
            
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
            
            if (action === 'delete_wp_comments' && !confirm('Weet je zeker dat je de WordPress comments wilt verwijderen? Dit kan niet ongedaan worden gemaakt.')) {
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
            // Get current sorting parameters from URL
            const urlParams = new URLSearchParams(window.location.search);
            const orderby = urlParams.get('orderby') || 'datum';
            const order = urlParams.get('order') || 'desc';
            filterByPage(pageId, orderby, order);
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

        // Image modal functionality
        $(document).on('click', '.ovm-view-image', function(e) {
            e.preventDefault();
            const imageUrl = $(this).attr('href');
            showImageModal(imageUrl);
        });

        // Close modal when clicking on background or close button
        $(document).on('click', '.ovm-image-modal', function(e) {
            if (e.target === this) {
                hideImageModal();
            }
        });

        $(document).on('click', '.ovm-image-modal-close', function(e) {
            e.preventDefault();
            hideImageModal();
        });

        // Close modal with Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('.ovm-image-modal.active').length > 0) {
                hideImageModal();
            }
        });

        // Tab switching
        $('.nav-tab').on('click', function() {
            const newTab = $(this).attr('href').replace('#', '');
            if (newTab !== currentTab) {
                currentTab = newTab;
                updatePageFilter(newTab);
            }
        });

        // ChatGPT button - generate response via API
        $(document).on('click', '.ovm-chatgpt-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const commentId = $btn.data('comment-id');
            
            // Only proceed if we're in "te_verwerken" status
            if (currentTab !== 'te_verwerken') {
                alert('ChatGPT functionaliteit is alleen beschikbaar voor items die nog te verwerken zijn.');
                return;
            }
            
            // Show loading state
            const originalText = $btn.html();
            $btn.prop('disabled', true);
            $btn.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>Genereren...');
            
            // Make API call
            generateChatGPTResponse(commentId, $btn, originalText);
        });

        // Update images button
        $('#ovm-update-images').on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            $btn.prop('disabled', true);
            $btn.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>Afbeeldingen updaten...');
            
            $.ajax({
                url: ovm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ovm_update_missing_images',
                    nonce: ovm_ajax.nonce
                },
                success: function(result) {
                    $btn.prop('disabled', false);
                    $btn.html('<span class="dashicons dashicons-format-image" style="margin-top: 3px;"></span>Update Afbeeldingen');
                    
                    if (result.success) {
                        showNotification(result.data.message, 'success');
                        if (result.data.updated > 0) {
                            // Reload the page to show updated images
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        alert('Update mislukt: ' + result.data.message);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                    $btn.html('<span class="dashicons dashicons-format-image" style="margin-top: 3px;"></span>Update Afbeeldingen');
                    alert('Er is een fout opgetreden tijdens het updaten.');
                }
            });
        });

        // Export button
        $(document).on('click', '.ovm-export-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const status = $btn.data('status');
            
            $btn.prop('disabled', true);
            $btn.text('PDF genereren...');
            
            $.ajax({
                url: ovm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ovm_export_comments',
                    nonce: ovm_ajax.nonce,
                    status: status
                },
                success: function(result) {
                    $btn.prop('disabled', false);
                    $btn.text('Export naar PDF');
                    
                    if (result.success) {
                        // Convert base64 to blob and download
                        const pdfData = atob(result.data.pdf);
                        const pdfArray = new Uint8Array(pdfData.length);
                        
                        for (let i = 0; i < pdfData.length; i++) {
                            pdfArray[i] = pdfData.charCodeAt(i);
                        }
                        
                        const blob = new Blob([pdfArray], { type: 'application/pdf' });
                        const link = document.createElement('a');
                        const url = URL.createObjectURL(blob);
                        
                        link.setAttribute('href', url);
                        link.setAttribute('download', result.data.filename);
                        link.style.visibility = 'hidden';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        // Clean up
                        setTimeout(function() {
                            URL.revokeObjectURL(url);
                        }, 100);
                        
                        showNotification('PDF export succesvol! (' + result.data.count + ' items)', 'success');
                    } else {
                        alert('Export mislukt: ' + result.data.message);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                    $btn.text('Export naar PDF');
                    alert('Er is een fout opgetreden tijdens het genereren van de PDF.');
                }
            });
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
                    if (action === 'delete_wp_comment') {
                        // For WordPress comment deletion, just show message and update button
                        alert(result.data.message);
                        $btn.text('WP Comment Verwijderd').prop('disabled', true).addClass('disabled');
                        $btn.prop('disabled', false); // Re-enable for potential other actions
                    } else {
                        // Remove row with fade effect for status changes
                        $btn.closest('tr').fadeOut(400, function() {
                            $(this).remove();
                            updateEmptyState();
                            updateCountBadges();
                        });
                    }
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
    function filterByPage(pageId, orderby, order) {
        showLoadingOverlay();
        
        $.ajax({
            url: ovm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ovm_filter_by_page',
                nonce: ovm_ajax.nonce,
                page_id: pageId,
                status: currentTab,
                orderby: orderby || 'datum',
                order: order || 'desc'
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
                    
                    // Update content with proper HTML escaping
                    const escapeHtml = function(text) {
                        const div = document.createElement('div');
                        div.textContent = text;
                        return div.innerHTML;
                    };
                    
                    $truncated.html(escapeHtml(result.data.truncated).replace(/\n/g, '<br>'));
                    $full.html(escapeHtml(result.data.content).replace(/\n/g, '<br>'));
                    
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

    /**
     * Copy text to clipboard
     */
    function copyToClipboard(text, $btn) {
        // Try using the modern clipboard API
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                // Success - show feedback
                showCopySuccess($btn);
            }).catch(function(err) {
                // Fallback to older method
                fallbackCopyToClipboard(text, $btn);
            });
        } else {
            // Use fallback for older browsers
            fallbackCopyToClipboard(text, $btn);
        }
    }

    /**
     * Fallback method for copying to clipboard
     */
    function fallbackCopyToClipboard(text, $btn) {
        const $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopySuccess($btn);
            } else {
                // Show the prompt in an alert as last resort
                alert('Kopieer de volgende prompt:\n\n' + text);
            }
        } catch (err) {
            // Show the prompt in an alert as last resort
            alert('Kopieer de volgende prompt:\n\n' + text);
        }
        
        $temp.remove();
    }

    /**
     * Show copy success feedback
     */
    function showCopySuccess($btn) {
        const originalText = $btn.html();
        $btn.addClass('copied');
        $btn.html('✓ Gekopieerd!');
        
        setTimeout(function() {
            $btn.removeClass('copied');
            $btn.html(originalText);
        }, 2000);
    }

    /**
     * Show image modal
     */
    function showImageModal(imageUrl) {
        // Create modal if it doesn't exist
        if ($('.ovm-image-modal').length === 0) {
            const modalHtml = `
                <div class="ovm-image-modal">
                    <div class="ovm-image-modal-content">
                        <button class="ovm-image-modal-close">&times;</button>
                        <h3 class="ovm-image-modal-title">Afbeelding</h3>
                        <img src="" alt="Comment image" />
                    </div>
                </div>
            `;
            $('body').append(modalHtml);
        }

        // Set the image source and show modal
        $('.ovm-image-modal img').attr('src', imageUrl);
        $('.ovm-image-modal').addClass('active');
        
        // Prevent body scrolling
        $('body').addClass('ovm-modal-open');
    }

    /**
     * Hide image modal
     */
    function hideImageModal() {
        $('.ovm-image-modal').removeClass('active');
        
        // Re-enable body scrolling
        $('body').removeClass('ovm-modal-open');
    }
    
    /**
     * Toggle flag status
     */
    function toggleFlag(commentId, $btn) {
        $btn.prop('disabled', true);
        
        $.ajax({
            url: ovm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ovm_toggle_flag',
                nonce: ovm_ajax.nonce,
                comment_id: commentId
            },
            success: function(result) {
                $btn.prop('disabled', false);
                
                if (result.success) {
                    const $row = $btn.closest('tr');
                    const $flagIcon = $btn.find('.flag-icon');
                    
                    if (result.data.flagged) {
                        $row.addClass('ovm-flagged-row');
                        $btn.addClass('is-flagged');
                        $flagIcon.text('🚩');
                    } else {
                        $row.removeClass('ovm-flagged-row');
                        $btn.removeClass('is-flagged');
                        $flagIcon.text('⚑');
                    }
                    
                    showNotification(result.data.message, 'success');
                } else {
                    showNotification(result.data.message || ovm_ajax.strings.error, 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                showNotification(ovm_ajax.strings.error, 'error');
            }
        });
    }

    /**
     * Update page filter options for the current tab
     */
    function updatePageFilter(status) {
        $.ajax({
            url: ovm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ovm_get_posts_for_status',
                nonce: ovm_ajax.nonce,
                status: status
            },
            success: function(result) {
                if (result.success) {
                    const $select = $('#ovm-page-filter');
                    const currentValue = $select.val();
                    
                    // Clear current options except "Alle pagina's"
                    $select.find('option:not(:first)').remove();
                    
                    // Add new options
                    result.data.posts.forEach(function(post) {
                        $select.append('<option value="' + post.post_id + '">' + post.post_title + '</option>');
                    });
                    
                    // Restore selection if still valid
                    if (currentValue && $select.find('option[value="' + currentValue + '"]').length > 0) {
                        $select.val(currentValue);
                    } else {
                        $select.val(''); // Reset to "Alle pagina's"
                    }
                }
            },
            error: function() {
                console.error('Failed to update page filter');
            }
        });
    }

    /**
     * Generate ChatGPT response
     */
    function generateChatGPTResponse(commentId, $btn, originalText) {
        $.ajax({
            url: ovm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ovm_chatgpt_generate_response',
                nonce: ovm_ajax.nonce,
                comment_id: commentId
            },
            success: function(result) {
                if (result.success) {
                    // Update the admin response textarea
                    const $row = $btn.closest('tr');
                    const $textarea = $row.find('.ovm-admin-response');
                    
                    if ($textarea.length) {
                        $textarea.val(result.data.response);
                        $textarea.trigger('input'); // Trigger auto-save
                    }
                    
                    // Show success feedback
                    $btn.removeClass('button-primary').addClass('button-secondary');
                    $btn.html('✓ Gegenereerd');
                    
                    setTimeout(function() {
                        $btn.removeClass('button-secondary').addClass('button-primary');
                        $btn.html(originalText);
                        $btn.prop('disabled', false);
                    }, 3000);
                    
                } else {
                    alert(result.data.message || 'Fout bij genereren van ChatGPT reactie');
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert('Netwerk fout bij ChatGPT API call');
                $btn.html(originalText);
                $btn.prop('disabled', false);
            }
        });
    }
    
    /**
     * Initialize touch-friendly textarea resizing for iOS Safari
     */
    function initTouchResizeTextareas() {
        // Check if we're on a touch device
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        
        if (!isTouchDevice) {
            return; // Only initialize for touch devices
        }
        
        // Find all textareas that need resize capability
        const textareas = document.querySelectorAll('.ovm-admin-response, .ovm-comment-edit, #chatgpt_prompt');
        
        textareas.forEach(textarea => {
            // Skip if already wrapped
            if (textarea.parentElement.classList.contains('ovm-textarea-wrapper')) {
                return;
            }
            
            // Create wrapper
            const wrapper = document.createElement('div');
            wrapper.className = 'ovm-textarea-wrapper';
            
            // Wrap the textarea
            textarea.parentNode.insertBefore(wrapper, textarea);
            wrapper.appendChild(textarea);
            
            // Create resize handle
            const handle = document.createElement('div');
            handle.className = 'ovm-textarea-resize-handle';
            handle.setAttribute('aria-label', 'Resize textarea');
            wrapper.appendChild(handle);
            
            let startY = 0;
            let startHeight = 0;
            let isResizing = false;
            
            // Touch start
            handle.addEventListener('touchstart', (e) => {
                e.preventDefault();
                isResizing = true;
                startY = e.touches[0].clientY;
                startHeight = parseInt(window.getComputedStyle(textarea).height, 10);
                textarea.style.transition = 'none';
            }, { passive: false });
            
            // Touch move
            document.addEventListener('touchmove', (e) => {
                if (!isResizing) return;
                
                e.preventDefault();
                const currentY = e.touches[0].clientY;
                const deltaY = currentY - startY;
                const newHeight = Math.max(100, startHeight + deltaY);
                
                textarea.style.height = newHeight + 'px';
            }, { passive: false });
            
            // Touch end
            document.addEventListener('touchend', () => {
                if (isResizing) {
                    isResizing = false;
                    textarea.style.transition = '';
                }
            });
            
            // Mouse events for testing on desktop
            handle.addEventListener('mousedown', (e) => {
                e.preventDefault();
                isResizing = true;
                startY = e.clientY;
                startHeight = parseInt(window.getComputedStyle(textarea).height, 10);
                textarea.style.transition = 'none';
                document.body.style.cursor = 'ns-resize';
            });
            
            document.addEventListener('mousemove', (e) => {
                if (!isResizing) return;
                
                const currentY = e.clientY;
                const deltaY = currentY - startY;
                const newHeight = Math.max(100, startHeight + deltaY);
                
                textarea.style.height = newHeight + 'px';
            });
            
            document.addEventListener('mouseup', () => {
                if (isResizing) {
                    isResizing = false;
                    textarea.style.transition = '';
                    document.body.style.cursor = '';
                }
            });
        });
    }
    
    // Initialize touch resize when DOM is ready
    $(document).ready(function() {
        initTouchResizeTextareas();
        
        // Re-initialize when new textareas are added dynamically
        $(document).on('DOMNodeInserted', function(e) {
            if ($(e.target).find('textarea').length || $(e.target).is('textarea')) {
                setTimeout(initTouchResizeTextareas, 100);
            }
        });
        
        // Initialize column sorting (jQuery UI sortable)
        if ($('#ovm-column-order-list').length) {
            $('#ovm-column-order-list').sortable({
                axis: 'y',
                placeholder: 'ovm-sortable-placeholder',
                update: function(event, ui) {
                    // Update hidden input with new order
                    var newOrder = [];
                    $('#ovm-column-order-list li').each(function() {
                        newOrder.push($(this).data('column'));
                    });
                    $('#ovm_column_order').val(newOrder.join(','));
                }
            });
            
            // Add placeholder styling
            $('<style>')
                .text('.ovm-sortable-placeholder { background: #e7f3ff !important; border: 2px dashed #007cba !important; height: 40px; margin-bottom: 5px; }')
                .appendTo('head');
        }
        
        // Media uploader for logo
        $('#ovm_upload_logo').on('click', function(e) {
            e.preventDefault();
            
            var mediaUploader = wp.media({
                title: 'Selecteer bedrijfslogo',
                button: {
                    text: 'Gebruik dit logo'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#ovm_logo_url').val(attachment.url);
                
                // Update preview
                var preview = $('#ovm_logo_url').parent().find('img');
                if (preview.length) {
                    preview.attr('src', attachment.url);
                } else {
                    $('#ovm_logo_url').parent().append(
                        '<div style="margin-top: 10px;">' +
                        '<img src="' + attachment.url + '" alt="Logo preview" style="max-height: 60px; max-width: 150px;" />' +
                        '</div>'
                    );
                }
            });
            
            mediaUploader.open();
        });
    });

})(jQuery);