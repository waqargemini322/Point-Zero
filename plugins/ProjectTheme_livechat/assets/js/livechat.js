/**
 * Link2Investors Live Chat JavaScript
 * Handles all frontend chat interactions
 */

(function($) {
    'use strict';
    
    // Chat Manager Class
    class L2IChatManager {
        constructor() {
            this.currentThreadId = null;
            this.lastMessageId = 0;
            this.pollInterval = null;
            this.typingTimeout = null;
            this.isTyping = false;
            this.pollIntervalMs = 3000; // 3 seconds
            this.typingTimeoutMs = 1000; // 1 second
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.initializeChat();
            this.startPolling();
        }
        
        bindEvents() {
            // Message form submission
            $(document).on('submit', '#l2i-message-form', (e) => {
                e.preventDefault();
                this.sendMessage();
            });
            
            // Conversation selection
            $(document).on('click', '.l2i-conversation-item', (e) => {
                const threadId = $(e.currentTarget).data('thread-id');
                this.selectConversation(threadId);
            });
            
            // Start chat button
            $(document).on('click', '.l2i-start-chat-btn', (e) => {
                const recipientId = $(e.currentTarget).data('recipient-id');
                const recipientName = $(e.currentTarget).data('recipient-name');
                this.startConversation(recipientId, recipientName);
            });
            
            // File upload
            $(document).on('change', '#l2i-file-input', (e) => {
                this.handleFileUpload(e.target.files[0]);
            });
            
            // Typing indicator
            $(document).on('input', '#l2i-message-input', () => {
                this.handleTyping();
            });
            
            // Auto-resize textarea
            $(document).on('input', '#l2i-message-input', (e) => {
                this.autoResizeTextarea(e.target);
            });
            
            // Zoom meeting button
            $(document).on('click', '.l2i-zoom-btn', (e) => {
                const threadId = $(e.currentTarget).data('thread-id');
                this.showZoomDialog(threadId);
            });
            
            // Search conversations
            $(document).on('input', '#l2i-search-input', (e) => {
                this.searchConversations(e.target.value);
            });
            
            // Mark messages as read when thread is opened
            $(document).on('click', '.l2i-conversation-item', (e) => {
                const threadId = $(e.currentTarget).data('thread-id');
                setTimeout(() => this.markMessagesRead(threadId), 500);
            });
            
            // Keyboard shortcuts
            $(document).on('keydown', '#l2i-message-input', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    $('#l2i-message-form').submit();
                }
            });
        }
        
        initializeChat() {
            const urlParams = new URLSearchParams(window.location.search);
            const threadId = urlParams.get('thread_id');
            
            if (threadId) {
                this.currentThreadId = parseInt(threadId);
                this.loadMessages();
                this.markConversationActive(threadId);
            }
            
            // Get last message ID for polling
            const lastMessage = $('.l2i-message').last();
            if (lastMessage.length) {
                this.lastMessageId = parseInt(lastMessage.data('message-id')) || 0;
            }
        }
        
        sendMessage() {
            const form = $('#l2i-message-form');
            const messageInput = $('#l2i-message-input');
            const messageContent = messageInput.val().trim();
            const attachmentId = form.find('input[name="attachment_id"]').val();
            
            if (!messageContent && !attachmentId) {
                return;
            }
            
            if (!this.currentThreadId) {
                this.showError('No conversation selected.');
                return;
            }
            
            // Disable form during sending
            this.setFormState(false);
            
            const formData = new FormData();
            formData.append('action', 'l2i_send_message');
            formData.append('thread_id', this.currentThreadId);
            formData.append('message_content', messageContent);
            formData.append('nonce', l2i_chat_vars.nonce);
            
            if (attachmentId) {
                formData.append('attachment_id', attachmentId);
            }
            
            $.ajax({
                url: l2i_chat_vars.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        messageInput.val('');
                        this.autoResizeTextarea(messageInput[0]);
                        form.find('input[name="attachment_id"]').val('');
                        
                        // Add message to chat immediately
                        if (response.data.message_data) {
                            this.addMessageToChat(response.data.message_data);
                            this.scrollToBottom();
                        }
                        
                        // Update conversation list
                        this.updateConversationPreview(messageContent);
                        
                    } else {
                        this.showError(response.data.message || 'Failed to send message.');
                    }
                },
                error: () => {
                    this.showError('Network error. Please try again.');
                },
                complete: () => {
                    this.setFormState(true);
                }
            });
        }
        
        loadMessages() {
            if (!this.currentThreadId) return;
            
            $.ajax({
                url: l2i_chat_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'l2i_get_messages',
                    thread_id: this.currentThreadId,
                    nonce: l2i_chat_vars.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderMessages(response.data.messages);
                        this.scrollToBottom();
                    }
                }
            });
        }
        
        selectConversation(threadId) {
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('thread_id', threadId);
            window.history.pushState({}, '', url);
            
            this.currentThreadId = threadId;
            this.markConversationActive(threadId);
            this.loadMessages();
            this.markMessagesRead(threadId);
            
            // Reset polling
            this.lastMessageId = 0;
        }
        
        startConversation(recipientId, recipientName) {
            $.ajax({
                url: l2i_chat_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'l2i_start_conversation',
                    recipient_id: recipientId,
                    nonce: l2i_chat_vars.nonce
                },
                success: (response) => {
                    if (response.success) {
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            this.selectConversation(response.data.thread_id);
                        }
                    } else {
                        this.showError(response.data.message || 'Failed to start conversation.');
                        
                        // If it's a credit issue, show upgrade option
                        if (response.data.code === 'insufficient_credits') {
                            this.showUpgradeDialog();
                        }
                    }
                },
                error: () => {
                    this.showError('Network error. Please try again.');
                }
            });
        }
        
        handleFileUpload(file) {
            if (!file) return;
            
            // Validate file size (5MB limit)
            if (file.size > 5 * 1024 * 1024) {
                this.showError('File size must be less than 5MB.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'l2i_upload_file');
            formData.append('file', file);
            formData.append('nonce', l2i_chat_vars.nonce);
            
            // Show upload progress
            this.showUploadProgress();
            
            $.ajax({
                url: l2i_chat_vars.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        // Set attachment ID in form
                        $('#l2i-message-form').find('input[name="attachment_id"]').val(response.data.attachment_id);
                        
                        // Show file preview
                        this.showFilePreview(response.data);
                        
                        // Focus message input
                        $('#l2i-message-input').focus();
                        
                    } else {
                        this.showError(response.data.message || 'Failed to upload file.');
                    }
                },
                error: () => {
                    this.showError('Upload failed. Please try again.');
                },
                complete: () => {
                    this.hideUploadProgress();
                    // Reset file input
                    $('#l2i-file-input').val('');
                }
            });
        }
        
        handleTyping() {
            if (!this.currentThreadId) return;
            
            // Clear previous timeout
            clearTimeout(this.typingTimeout);
            
            // Send typing indicator if not already typing
            if (!this.isTyping) {
                this.isTyping = true;
                this.sendTypingStatus(true);
            }
            
            // Set timeout to stop typing indicator
            this.typingTimeout = setTimeout(() => {
                this.isTyping = false;
                this.sendTypingStatus(false);
            }, this.typingTimeoutMs);
        }
        
        sendTypingStatus(isTyping) {
            $.ajax({
                url: l2i_chat_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'l2i_update_typing',
                    thread_id: this.currentThreadId,
                    is_typing: isTyping ? 1 : 0,
                    nonce: l2i_chat_vars.nonce
                }
            });
        }
        
        startPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }
            
            this.pollInterval = setInterval(() => {
                this.pollUpdates();
            }, this.pollIntervalMs);
        }
        
        pollUpdates() {
            if (!this.currentThreadId) return;
            
            $.ajax({
                url: l2i_chat_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'l2i_poll_updates',
                    thread_id: this.currentThreadId,
                    last_message_id: this.lastMessageId,
                    nonce: l2i_chat_vars.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Add new messages
                        if (response.data.new_messages && response.data.new_messages.length > 0) {
                            response.data.new_messages.forEach(message => {
                                this.addMessageToChat(message);
                            });
                            this.scrollToBottom();
                            
                            // Update last message ID
                            const lastMessage = response.data.new_messages[response.data.new_messages.length - 1];
                            this.lastMessageId = parseInt(lastMessage.id);
                        }
                        
                        // Update typing indicator
                        this.updateTypingIndicator(response.data.typing_status);
                        
                        // Update unread count
                        this.updateUnreadCount(response.data.unread_count);
                    }
                }
            });
        }
        
        markMessagesRead(threadId) {
            $.ajax({
                url: l2i_chat_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'l2i_mark_read',
                    thread_id: threadId,
                    nonce: l2i_chat_vars.nonce
                }
            });
        }
        
        searchConversations(searchTerm) {
            const conversations = $('.l2i-conversation-item');
            
            if (!searchTerm.trim()) {
                conversations.show();
                return;
            }
            
            conversations.each(function() {
                const $item = $(this);
                const name = $item.find('.l2i-conversation-name').text().toLowerCase();
                const preview = $item.find('.l2i-conversation-preview').text().toLowerCase();
                const search = searchTerm.toLowerCase();
                
                if (name.includes(search) || preview.includes(search)) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        }
        
        showZoomDialog(threadId) {
            const dialog = $(`
                <div class="l2i-zoom-dialog-overlay">
                    <div class="l2i-zoom-dialog">
                        <h3>Start Zoom Meeting</h3>
                        <form id="l2i-zoom-form">
                            <div class="l2i-form-group">
                                <label for="meeting-topic">Meeting Topic (optional)</label>
                                <input type="text" id="meeting-topic" name="meeting_topic" placeholder="Enter meeting topic...">
                            </div>
                            <div class="l2i-form-group">
                                <label for="meeting-duration">Duration (minutes)</label>
                                <select id="meeting-duration" name="meeting_duration">
                                    <option value="30">30 minutes</option>
                                    <option value="60" selected>1 hour</option>
                                    <option value="120">2 hours</option>
                                    <option value="180">3 hours</option>
                                </select>
                            </div>
                            <div class="l2i-form-actions">
                                <button type="button" class="l2i-btn-cancel">Cancel</button>
                                <button type="submit" class="l2i-btn-primary">Start Meeting</button>
                            </div>
                        </form>
                    </div>
                </div>
            `);
            
            $('body').append(dialog);
            
            // Handle form submission
            dialog.find('#l2i-zoom-form').on('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                formData.append('action', 'l2i_send_zoom_invite');
                formData.append('thread_id', threadId);
                formData.append('nonce', l2i_chat_vars.nonce);
                
                this.sendZoomInvite(formData, dialog);
            });
            
            // Handle cancel
            dialog.find('.l2i-btn-cancel').on('click', () => {
                dialog.remove();
            });
            
            // Close on overlay click
            dialog.on('click', (e) => {
                if (e.target === dialog[0]) {
                    dialog.remove();
                }
            });
        }
        
        sendZoomInvite(formData, dialog) {
            const submitBtn = dialog.find('button[type="submit"]');
            submitBtn.prop('disabled', true).text('Creating Meeting...');
            
            $.ajax({
                url: l2i_chat_vars.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        dialog.remove();
                        this.showSuccess('Zoom meeting invitation sent!');
                        
                        // Add the zoom message to chat
                        if (response.data.message_id) {
                            this.pollUpdates(); // Refresh to get the new message
                        }
                    } else {
                        this.showError(response.data.message || 'Failed to create meeting.');
                        
                        if (response.data.code === 'insufficient_zoom_credits') {
                            this.showUpgradeDialog();
                        }
                    }
                },
                error: () => {
                    this.showError('Network error. Please try again.');
                },
                complete: () => {
                    submitBtn.prop('disabled', false).text('Start Meeting');
                }
            });
        }
        
        // Helper Methods
        renderMessages(messages) {
            const messagesContainer = $('#l2i-chat-messages');
            messagesContainer.empty();
            
            messages.forEach(message => {
                this.addMessageToChat(message);
            });
        }
        
        addMessageToChat(message) {
            const messagesContainer = $('#l2i-chat-messages');
            const messageHtml = this.createMessageHtml(message);
            messagesContainer.append(messageHtml);
            
            // Update last message ID
            if (parseInt(message.id) > this.lastMessageId) {
                this.lastMessageId = parseInt(message.id);
            }
        }
        
        createMessageHtml(message) {
            const messageClass = message.is_own_message ? 'own-message' : 'other-message';
            const messageTypeClass = 'message-type-' + message.message_type;
            
            let messageContent = '';
            
            if (message.message_type === 'zoom_invite' && message.metadata) {
                messageContent = this.createZoomInviteHtml(message);
            } else if (message.attachment_id && message.attachment) {
                messageContent = this.createFileMessageHtml(message);
            } else {
                messageContent = `<div class="l2i-message-text">${this.escapeHtml(message.message_content).replace(/\n/g, '<br>')}</div>`;
            }
            
            let avatarHtml = '';
            if (!message.is_own_message) {
                avatarHtml = `
                    <div class="l2i-message-avatar">
                        <img src="${message.sender_avatar}" alt="${this.escapeHtml(message.sender_name)}">
                    </div>
                `;
            }
            
            return `
                <div class="l2i-message ${messageClass} ${messageTypeClass}" data-message-id="${message.id}">
                    ${avatarHtml}
                    <div class="l2i-message-content">
                        ${messageContent}
                        <div class="l2i-message-time">${message.formatted_time}</div>
                    </div>
                </div>
            `;
        }
        
        createZoomInviteHtml(message) {
            const metadata = message.metadata;
            let joinButton = '';
            
            if (metadata.zoom_join_url) {
                joinButton = `<a href="${metadata.zoom_join_url}" class="l2i-zoom-join-btn" target="_blank">Join Meeting</a>`;
            }
            
            return `
                <div class="l2i-zoom-invite-message">
                    <div class="l2i-zoom-icon">🎥</div>
                    <div class="l2i-zoom-content">
                        <h4>Zoom Meeting Invitation</h4>
                        <p>${this.escapeHtml(message.message_content).replace(/\n/g, '<br>')}</p>
                        ${joinButton}
                    </div>
                </div>
            `;
        }
        
        createFileMessageHtml(message) {
            const attachment = message.attachment;
            let messageText = '';
            
            if (message.message_content) {
                messageText = `<div class="l2i-message-text">${this.escapeHtml(message.message_content).replace(/\n/g, '<br>')}</div>`;
            }
            
            return `
                <div class="l2i-file-message">
                    ${messageText}
                    <div class="l2i-file-attachment">
                        <div class="l2i-file-icon">📎</div>
                        <div class="l2i-file-info">
                            <a href="${attachment.url}" target="_blank" class="l2i-file-name">${this.escapeHtml(attachment.title)}</a>
                            <div class="l2i-file-size">${attachment.file_size}</div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        updateTypingIndicator(typingStatus) {
            const indicator = $('#l2i-typing-indicator');
            const typingText = indicator.find('.l2i-typing-text');
            
            if (typingStatus && typingStatus.length > 0) {
                const names = typingStatus.map(user => user.display_name).join(', ');
                typingText.text(`${names} ${typingStatus.length === 1 ? 'is' : 'are'} typing`);
                indicator.show();
            } else {
                indicator.hide();
            }
        }
        
        updateUnreadCount(count) {
            const badge = $('.l2i-unread-badge');
            const countDisplay = $('.l2i-unread-count');
            
            if (count > 0) {
                badge.text(count).show();
                countDisplay.attr('data-count', count);
            } else {
                badge.hide();
                countDisplay.attr('data-count', '0');
            }
        }
        
        updateConversationPreview(messageContent) {
            const activeConversation = $('.l2i-conversation-item.active');
            if (activeConversation.length) {
                activeConversation.find('.l2i-conversation-preview').text(messageContent.substring(0, 50) + (messageContent.length > 50 ? '...' : ''));
                activeConversation.find('.l2i-conversation-time').text('now');
            }
        }
        
        markConversationActive(threadId) {
            $('.l2i-conversation-item').removeClass('active');
            $(`.l2i-conversation-item[data-thread-id="${threadId}"]`).addClass('active');
        }
        
        scrollToBottom() {
            const messagesContainer = $('#l2i-chat-messages');
            messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
        }
        
        autoResizeTextarea(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }
        
        setFormState(enabled) {
            const form = $('#l2i-message-form');
            form.find('input, textarea, button').prop('disabled', !enabled);
            
            if (enabled) {
                $('#l2i-message-input').focus();
            }
        }
        
        showUploadProgress() {
            // Implementation for upload progress
            const progress = $('<div class="l2i-upload-progress">Uploading...</div>');
            $('.l2i-chat-input').append(progress);
        }
        
        hideUploadProgress() {
            $('.l2i-upload-progress').remove();
        }
        
        showFilePreview(fileData) {
            const preview = $(`
                <div class="l2i-file-preview">
                    <span class="l2i-file-name">${this.escapeHtml(fileData.attachment_title)}</span>
                    <span class="l2i-file-size">${fileData.file_size}</span>
                    <button type="button" class="l2i-remove-file" onclick="$(this).closest('.l2i-file-preview').remove(); $('#l2i-message-form').find('input[name=attachment_id]').val('');">×</button>
                </div>
            `);
            
            $('.l2i-chat-input form').prepend(preview);
        }
        
        showError(message) {
            this.showNotification(message, 'error');
        }
        
        showSuccess(message) {
            this.showNotification(message, 'success');
        }
        
        showNotification(message, type = 'info') {
            const notification = $(`
                <div class="l2i-notification l2i-notification-${type}">
                    ${this.escapeHtml(message)}
                    <button type="button" class="l2i-notification-close">×</button>
                </div>
            `);
            
            $('body').append(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 5000);
            
            // Remove on click
            notification.find('.l2i-notification-close').on('click', () => {
                notification.fadeOut(() => notification.remove());
            });
        }
        
        showUpgradeDialog() {
            const dialog = $(`
                <div class="l2i-upgrade-dialog-overlay">
                    <div class="l2i-upgrade-dialog">
                        <h3>Upgrade Required</h3>
                        <p>You need more credits to perform this action. Please upgrade your membership to continue.</p>
                        <div class="l2i-dialog-actions">
                            <button type="button" class="l2i-btn-cancel">Cancel</button>
                            <a href="${l2i_chat_vars.upgrade_url || '#'}" class="l2i-btn-primary">Upgrade Now</a>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(dialog);
            
            // Handle cancel
            dialog.find('.l2i-btn-cancel').on('click', () => {
                dialog.remove();
            });
            
            // Close on overlay click
            dialog.on('click', (e) => {
                if (e.target === dialog[0]) {
                    dialog.remove();
                }
            });
        }
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        destroy() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }
            if (this.typingTimeout) {
                clearTimeout(this.typingTimeout);
            }
        }
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize if chat interface exists
        if ($('.l2i-chat-interface, .l2i-conversation-list, .l2i-start-chat-btn').length > 0) {
            window.L2IChatManager = new L2IChatManager();
        }
        
        // Handle page visibility change to pause/resume polling
        document.addEventListener('visibilitychange', function() {
            if (window.L2IChatManager) {
                if (document.hidden) {
                    // Page is hidden, reduce polling frequency
                    window.L2IChatManager.pollIntervalMs = 10000; // 10 seconds
                } else {
                    // Page is visible, normal polling frequency
                    window.L2IChatManager.pollIntervalMs = 3000; // 3 seconds
                    // Poll immediately when page becomes visible
                    window.L2IChatManager.pollUpdates();
                }
            }
        });
        
        // Cleanup on page unload
        $(window).on('beforeunload', function() {
            if (window.L2IChatManager) {
                window.L2IChatManager.destroy();
            }
        });
    });
    
})(jQuery);

// Additional CSS for notifications and dialogs (injected via JS)
jQuery(document).ready(function($) {
    if (!$('#l2i-chat-dynamic-styles').length) {
        $('head').append(`
            <style id="l2i-chat-dynamic-styles">
                .l2i-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 12px 16px;
                    border-radius: 6px;
                    color: white;
                    font-weight: 600;
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    min-width: 300px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                }
                
                .l2i-notification-error {
                    background: #e74c3c;
                }
                
                .l2i-notification-success {
                    background: #27ae60;
                }
                
                .l2i-notification-info {
                    background: #3498db;
                }
                
                .l2i-notification-close {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 16px;
                    cursor: pointer;
                    padding: 0;
                    margin-left: auto;
                }
                
                .l2i-zoom-dialog-overlay,
                .l2i-upgrade-dialog-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .l2i-zoom-dialog,
                .l2i-upgrade-dialog {
                    background: white;
                    border-radius: 8px;
                    padding: 24px;
                    max-width: 400px;
                    width: 90%;
                    max-height: 90vh;
                    overflow-y: auto;
                }
                
                .l2i-zoom-dialog h3,
                .l2i-upgrade-dialog h3 {
                    margin: 0 0 16px 0;
                    color: #2c3e50;
                }
                
                .l2i-form-group {
                    margin-bottom: 16px;
                }
                
                .l2i-form-group label {
                    display: block;
                    margin-bottom: 6px;
                    font-weight: 600;
                    color: #2c3e50;
                }
                
                .l2i-form-group input,
                .l2i-form-group select {
                    width: 100%;
                    padding: 10px 12px;
                    border: 1px solid #e0e0e0;
                    border-radius: 6px;
                    font-size: 14px;
                }
                
                .l2i-form-actions,
                .l2i-dialog-actions {
                    display: flex;
                    gap: 12px;
                    justify-content: flex-end;
                    margin-top: 20px;
                }
                
                .l2i-btn-cancel {
                    background: #95a5a6;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                    text-decoration: none;
                }
                
                .l2i-btn-primary {
                    background: #3498db;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                    text-decoration: none;
                }
                
                .l2i-btn-primary:hover {
                    background: #2980b9;
                    text-decoration: none;
                    color: white;
                }
                
                .l2i-file-preview {
                    background: #f8f9fa;
                    border: 1px solid #e0e0e0;
                    border-radius: 6px;
                    padding: 8px 12px;
                    margin-bottom: 12px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .l2i-file-preview .l2i-file-name {
                    flex: 1;
                    font-weight: 600;
                }
                
                .l2i-file-preview .l2i-file-size {
                    font-size: 12px;
                    color: #7f8c8d;
                }
                
                .l2i-remove-file {
                    background: #e74c3c;
                    color: white;
                    border: none;
                    border-radius: 50%;
                    width: 20px;
                    height: 20px;
                    cursor: pointer;
                    font-size: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .l2i-upload-progress {
                    background: #3498db;
                    color: white;
                    padding: 8px 12px;
                    border-radius: 6px;
                    margin-bottom: 12px;
                    text-align: center;
                    font-size: 13px;
                }
            </style>
        `);
    }
});