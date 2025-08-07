<?php
/**
 * Chat Shortcodes Class
 * Provides frontend chat functionality through WordPress shortcodes
 */

defined('ABSPATH') || exit;

class L2I_Chat_Shortcodes {
    
    private static $instance = null;
    private $core;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->core = L2I_Chat_Core::get_instance();
        $this->init_shortcodes();
    }
    
    private function init_shortcodes() {
        // Main chat interface shortcodes
        add_shortcode('l2i_messaging_interface', array($this, 'messaging_interface_shortcode'));
        add_shortcode('l2i_conversation_list', array($this, 'conversation_list_shortcode'));
        add_shortcode('l2i_chat_thread', array($this, 'chat_thread_shortcode'));
        
        // Utility shortcodes
        add_shortcode('l2i_start_chat_button', array($this, 'start_chat_button_shortcode'));
        add_shortcode('l2i_unread_count', array($this, 'unread_count_shortcode'));
        add_shortcode('l2i_online_status', array($this, 'online_status_shortcode'));
        
        // Legacy compatibility
        add_shortcode('project_theme_my_account_livechat', array($this, 'messaging_interface_shortcode'));
    }
    
    /**
     * Main messaging interface shortcode
     */
    public function messaging_interface_shortcode($atts) {
        $atts = shortcode_atts(array(
            'theme' => 'default',
            'show_search' => 'true',
            'show_online_status' => 'true',
            'enable_file_upload' => 'true',
            'enable_zoom' => 'true'
        ), $atts, 'l2i_messaging_interface');
        
        if (!is_user_logged_in()) {
            return '<div class="l2i-chat-login-required">' .
                   '<h3>' . __('Login Required', 'l2i-livechat') . '</h3>' .
                   '<p>' . __('Please log in to access your messages.', 'l2i-livechat') . '</p>' .
                   '<a href="' . wp_login_url(get_permalink()) . '" class="button">' . __('Login', 'l2i-livechat') . '</a>' .
                   '</div>';
        }
        
        $user_id = get_current_user_id();
        $current_thread_id = isset($_GET['thread_id']) ? (int) $_GET['thread_id'] : null;
        
        // Get user's conversations
        $conversations = $this->core->get_user_conversations($user_id, 20);
        if (is_wp_error($conversations)) {
            $conversations = array();
        }
        
        // Get current thread messages if thread_id is provided
        $current_messages = array();
        $current_thread = null;
        if ($current_thread_id) {
            $current_thread = $this->core->get_thread_info($current_thread_id, $user_id);
            if (!is_wp_error($current_thread)) {
                $current_messages = $this->core->get_thread_messages($current_thread_id, $user_id);
                if (is_wp_error($current_messages)) {
                    $current_messages = array();
                }
            }
        }
        
        ob_start();
        include $this->get_template_path('chat-interface');
        return ob_get_clean();
    }
    
    /**
     * Conversation list shortcode
     */
    public function conversation_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => '20',
            'show_search' => 'true',
            'show_online_status' => 'true',
            'format' => 'list'
        ), $atts, 'l2i_conversation_list');
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your conversations.', 'l2i-livechat') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $conversations = $this->core->get_user_conversations($user_id, (int) $atts['limit']);
        
        if (is_wp_error($conversations)) {
            return '<p>' . __('Error loading conversations.', 'l2i-livechat') . '</p>';
        }
        
        if (empty($conversations)) {
            return '<div class="l2i-no-conversations">' .
                   '<p>' . __('No conversations yet.', 'l2i-livechat') . '</p>' .
                   '<p>' . __('Start chatting with other members to see your conversations here.', 'l2i-livechat') . '</p>' .
                   '</div>';
        }
        
        $output = '<div class="l2i-conversation-list" data-format="' . esc_attr($atts['format']) . '">';
        
        if ($atts['show_search'] === 'true') {
            $output .= '<div class="l2i-conversation-search">';
            $output .= '<input type="text" id="l2i-search-conversations" placeholder="' . __('Search conversations...', 'l2i-livechat') . '">';
            $output .= '</div>';
        }
        
        $output .= '<div class="l2i-conversations">';
        
        foreach ($conversations as $conversation) {
            $output .= $this->render_conversation_item($conversation, $atts);
        }
        
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Chat thread shortcode
     */
    public function chat_thread_shortcode($atts) {
        $atts = shortcode_atts(array(
            'thread_id' => '',
            'height' => '400px',
            'enable_file_upload' => 'true',
            'enable_zoom' => 'true'
        ), $atts, 'l2i_chat_thread');
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view messages.', 'l2i-livechat') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $thread_id = (int) $atts['thread_id'];
        
        if (!$thread_id) {
            return '<p>' . __('Invalid conversation ID.', 'l2i-livechat') . '</p>';
        }
        
        // Get thread info
        $thread = $this->core->get_thread_info($thread_id, $user_id);
        if (is_wp_error($thread)) {
            return '<p>' . $thread->get_error_message() . '</p>';
        }
        
        // Get messages
        $messages = $this->core->get_thread_messages($thread_id, $user_id);
        if (is_wp_error($messages)) {
            $messages = array();
        }
        
        ob_start();
        include $this->get_template_path('chat-thread');
        return ob_get_clean();
    }
    
    /**
     * Start chat button shortcode
     */
    public function start_chat_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'recipient_id' => '',
            'recipient_name' => '',
            'button_text' => __('Start Chat', 'l2i-livechat'),
            'button_class' => 'l2i-start-chat-btn',
            'check_credits' => 'true'
        ), $atts, 'l2i_start_chat_button');
        
        if (!is_user_logged_in()) {
            return '<a href="' . wp_login_url(get_permalink()) . '" class="' . esc_attr($atts['button_class']) . '">' . 
                   __('Login to Chat', 'l2i-livechat') . '</a>';
        }
        
        $current_user_id = get_current_user_id();
        $recipient_id = (int) $atts['recipient_id'];
        
        if (!$recipient_id || $recipient_id == $current_user_id) {
            return '';
        }
        
        // Check if user has messaging permissions
        if ($atts['check_credits'] === 'true' && class_exists('L2I_Restrictions')) {
            $restrictions = L2I_Restrictions::get_instance();
            $can_message = $restrictions->check_messaging_permission(true, $current_user_id, $recipient_id);
            
            if (!$can_message) {
                return '<button class="' . esc_attr($atts['button_class']) . ' disabled" disabled>' . 
                       __('Upgrade to Chat', 'l2i-livechat') . '</button>';
            }
        }
        
        $recipient_name = $atts['recipient_name'] ?: get_userdata($recipient_id)->display_name;
        
        return '<button class="' . esc_attr($atts['button_class']) . '" ' .
               'data-recipient-id="' . esc_attr($recipient_id) . '" ' .
               'data-recipient-name="' . esc_attr($recipient_name) . '">' .
               esc_html($atts['button_text']) .
               '</button>';
    }
    
    /**
     * Unread count shortcode
     */
    public function unread_count_shortcode($atts) {
        $atts = shortcode_atts(array(
            'format' => 'number',
            'show_zero' => 'false',
            'wrapper_class' => 'l2i-unread-count'
        ), $atts, 'l2i_unread_count');
        
        if (!is_user_logged_in()) {
            return '';
        }
        
        $user_id = get_current_user_id();
        $unread_count = $this->core->get_unread_count($user_id);
        
        if ($unread_count == 0 && $atts['show_zero'] === 'false') {
            return '';
        }
        
        $output = '<span class="' . esc_attr($atts['wrapper_class']) . '" data-count="' . $unread_count . '">';
        
        if ($atts['format'] === 'badge') {
            $output .= '<span class="l2i-unread-badge">' . $unread_count . '</span>';
        } elseif ($atts['format'] === 'text') {
            $output .= sprintf(_n('%d unread message', '%d unread messages', $unread_count, 'l2i-livechat'), $unread_count);
        } else {
            $output .= $unread_count;
        }
        
        $output .= '</span>';
        
        return $output;
    }
    
    /**
     * Online status shortcode
     */
    public function online_status_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => '',
            'format' => 'indicator',
            'show_text' => 'false'
        ), $atts, 'l2i_online_status');
        
        $user_id = (int) $atts['user_id'];
        if (!$user_id) {
            return '';
        }
        
        $is_online = L2I_LiveChat_Manager::is_user_online($user_id);
        $status_class = $is_online ? 'online' : 'offline';
        $status_text = $is_online ? __('Online', 'l2i-livechat') : __('Offline', 'l2i-livechat');
        
        $output = '<span class="l2i-online-status ' . $status_class . '">';
        
        if ($atts['format'] === 'indicator') {
            $output .= '<span class="l2i-status-indicator"></span>';
        }
        
        if ($atts['show_text'] === 'true') {
            $output .= '<span class="l2i-status-text">' . $status_text . '</span>';
        }
        
        $output .= '</span>';
        
        return $output;
    }
    
    /**
     * Render conversation item
     */
    private function render_conversation_item($conversation, $atts) {
        $unread_class = $conversation['has_unread'] ? 'has-unread' : '';
        $online_class = $conversation['other_user_online'] ? 'user-online' : 'user-offline';
        
        $output = '<div class="l2i-conversation-item ' . $unread_class . ' ' . $online_class . '" ' .
                  'data-thread-id="' . $conversation['id'] . '" ' .
                  'data-user-id="' . $conversation['other_user_id'] . '">';
        
        // Avatar
        $output .= '<div class="l2i-conversation-avatar">';
        $output .= '<img src="' . esc_url($conversation['other_user_avatar']) . '" alt="' . esc_attr($conversation['other_user_name']) . '">';
        
        if ($atts['show_online_status'] === 'true') {
            $status_class = $conversation['other_user_online'] ? 'online' : 'offline';
            $output .= '<span class="l2i-online-indicator ' . $status_class . '"></span>';
        }
        
        $output .= '</div>';
        
        // Content
        $output .= '<div class="l2i-conversation-content">';
        $output .= '<div class="l2i-conversation-header">';
        $output .= '<h4 class="l2i-conversation-name">' . esc_html($conversation['other_user_name']) . '</h4>';
        
        if ($conversation['last_message_time']) {
            $output .= '<span class="l2i-conversation-time">' . esc_html($conversation['last_message_time_ago']) . '</span>';
        }
        
        $output .= '</div>';
        
        if ($conversation['last_message_preview']) {
            $output .= '<p class="l2i-conversation-preview">' . esc_html($conversation['last_message_preview']) . '</p>';
        }
        
        $output .= '</div>';
        
        // Unread indicator
        if ($conversation['has_unread']) {
            $output .= '<div class="l2i-unread-indicator"></div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get template path
     */
    private function get_template_path($template_name) {
        $template_path = L2I_LIVECHAT_PLUGIN_DIR . 'templates/' . $template_name . '.php';
        
        if (file_exists($template_path)) {
            return $template_path;
        }
        
        // Return inline template if file doesn't exist
        return $this->get_inline_template($template_name);
    }
    
    /**
     * Get inline template (fallback)
     */
    private function get_inline_template($template_name) {
        switch ($template_name) {
            case 'chat-interface':
                return $this->render_inline_chat_interface();
            case 'chat-thread':
                return $this->render_inline_chat_thread();
            default:
                return '';
        }
    }
    
    /**
     * Render inline chat interface
     */
    private function render_inline_chat_interface() {
        ob_start();
        ?>
        <div class="l2i-chat-interface" id="l2i-chat-interface">
            <div class="l2i-chat-sidebar">
                <div class="l2i-chat-header">
                    <h3><?php _e('Messages', 'l2i-livechat'); ?></h3>
                    <div class="l2i-unread-count-display">
                        <?php echo do_shortcode('[l2i_unread_count format="badge"]'); ?>
                    </div>
                </div>
                
                <div class="l2i-conversation-search">
                    <input type="text" id="l2i-search-input" placeholder="<?php _e('Search conversations...', 'l2i-livechat'); ?>">
                </div>
                
                <div class="l2i-conversation-list" id="l2i-conversation-list">
                    <?php if (empty($conversations)): ?>
                        <div class="l2i-no-conversations">
                            <p><?php _e('No conversations yet.', 'l2i-livechat'); ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conversation): ?>
                            <?php echo $this->render_conversation_item($conversation, array('show_online_status' => 'true')); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="l2i-chat-main">
                <?php if ($current_thread): ?>
                    <div class="l2i-chat-header">
                        <div class="l2i-chat-user-info">
                            <img src="<?php echo esc_url($current_thread['other_user_avatar']); ?>" alt="<?php echo esc_attr($current_thread['other_user_name']); ?>">
                            <div>
                                <h4><?php echo esc_html($current_thread['other_user_name']); ?></h4>
                                <?php echo do_shortcode('[l2i_online_status user_id="' . $current_thread['other_user_id'] . '" show_text="true"]'); ?>
                            </div>
                        </div>
                        
                        <div class="l2i-chat-actions">
                            <?php if (get_option('l2i_chat_enable_zoom_integration')): ?>
                                <button class="l2i-zoom-btn" data-thread-id="<?php echo $current_thread_id; ?>">
                                    <?php _e('Start Zoom Meeting', 'l2i-livechat'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="l2i-chat-messages" id="l2i-chat-messages" data-thread-id="<?php echo $current_thread_id; ?>">
                        <?php foreach ($current_messages as $message): ?>
                            <?php echo $this->render_message($message); ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="l2i-typing-indicator" id="l2i-typing-indicator" style="display: none;">
                        <span class="l2i-typing-text"></span>
                    </div>
                    
                    <div class="l2i-chat-input">
                        <form id="l2i-message-form" enctype="multipart/form-data">
                            <input type="hidden" name="thread_id" value="<?php echo $current_thread_id; ?>">
                            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('l2i_chat_nonce'); ?>">
                            
                            <?php if (get_option('l2i_chat_enable_file_upload')): ?>
                                <input type="file" id="l2i-file-input" name="file" style="display: none;">
                                <button type="button" class="l2i-file-btn" onclick="document.getElementById('l2i-file-input').click();">
                                    📎
                                </button>
                            <?php endif; ?>
                            
                            <textarea id="l2i-message-input" name="message_content" placeholder="<?php _e('Type your message...', 'l2i-livechat'); ?>" rows="1"></textarea>
                            <button type="submit" class="l2i-send-btn"><?php _e('Send', 'l2i-livechat'); ?></button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="l2i-chat-welcome">
                        <h3><?php _e('Select a conversation', 'l2i-livechat'); ?></h3>
                        <p><?php _e('Choose a conversation from the sidebar to start chatting.', 'l2i-livechat'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render inline chat thread
     */
    private function render_inline_chat_thread() {
        ob_start();
        ?>
        <div class="l2i-chat-thread" style="height: <?php echo esc_attr($atts['height']); ?>">
            <div class="l2i-chat-messages" id="l2i-chat-messages-<?php echo $thread_id; ?>">
                <?php foreach ($messages as $message): ?>
                    <?php echo $this->render_message($message); ?>
                <?php endforeach; ?>
            </div>
            
            <div class="l2i-chat-input">
                <form class="l2i-message-form" data-thread-id="<?php echo $thread_id; ?>">
                    <textarea name="message_content" placeholder="<?php _e('Type your message...', 'l2i-livechat'); ?>"></textarea>
                    <button type="submit"><?php _e('Send', 'l2i-livechat'); ?></button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render message
     */
    private function render_message($message) {
        $message_class = $message['is_own_message'] ? 'own-message' : 'other-message';
        $message_type_class = 'message-type-' . $message['message_type'];
        
        $output = '<div class="l2i-message ' . $message_class . ' ' . $message_type_class . '" data-message-id="' . $message['id'] . '">';
        
        if (!$message['is_own_message']) {
            $output .= '<div class="l2i-message-avatar">';
            $output .= '<img src="' . esc_url($message['sender_avatar']) . '" alt="' . esc_attr($message['sender_name']) . '">';
            $output .= '</div>';
        }
        
        $output .= '<div class="l2i-message-content">';
        
        if ($message['message_type'] === 'zoom_invite' && $message['metadata']) {
            $output .= $this->render_zoom_invite_message($message);
        } elseif ($message['attachment_id']) {
            $output .= $this->render_file_message($message);
        } else {
            $output .= '<div class="l2i-message-text">' . nl2br(esc_html($message['message_content'])) . '</div>';
        }
        
        $output .= '<div class="l2i-message-time">' . esc_html($message['formatted_time']) . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render Zoom invite message
     */
    private function render_zoom_invite_message($message) {
        $metadata = $message['metadata'];
        
        $output = '<div class="l2i-zoom-invite-message">';
        $output .= '<div class="l2i-zoom-icon">🎥</div>';
        $output .= '<div class="l2i-zoom-content">';
        $output .= '<h4>' . __('Zoom Meeting Invitation', 'l2i-livechat') . '</h4>';
        $output .= '<p>' . nl2br(esc_html($message['message_content'])) . '</p>';
        
        if (isset($metadata['zoom_join_url'])) {
            $output .= '<a href="' . esc_url($metadata['zoom_join_url']) . '" class="l2i-zoom-join-btn" target="_blank">';
            $output .= __('Join Meeting', 'l2i-livechat');
            $output .= '</a>';
        }
        
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render file message
     */
    private function render_file_message($message) {
        $attachment = $message['attachment'];
        
        $output = '<div class="l2i-file-message">';
        
        if ($message['message_content']) {
            $output .= '<div class="l2i-message-text">' . nl2br(esc_html($message['message_content'])) . '</div>';
        }
        
        $output .= '<div class="l2i-file-attachment">';
        $output .= '<div class="l2i-file-icon">📎</div>';
        $output .= '<div class="l2i-file-info">';
        $output .= '<a href="' . esc_url($attachment['url']) . '" target="_blank" class="l2i-file-name">';
        $output .= esc_html($attachment['title']);
        $output .= '</a>';
        $output .= '<div class="l2i-file-size">' . esc_html($attachment['file_size']) . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }
}