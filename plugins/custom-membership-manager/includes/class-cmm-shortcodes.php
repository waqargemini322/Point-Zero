<?php
class CMM_Shortcodes {
    public static function init() {
        add_shortcode('cmm_register_form', [__CLASS__, 'render_register_form']);
    }

    public static function render_register_form() {
        ob_start(); ?>
        <form method="post">
            <input type="text" name="cmm_username" placeholder="Username" required />
            <input type="email" name="cmm_email" placeholder="Email" required />
            <input type="password" name="cmm_password" placeholder="Password" required />
            <select name="cmm_membership" required>
                <option value="member_free">Free</option>
                <option value="member_silver">Silver</option>
                <option value="member_gold">Gold</option>
            </select>
            <button type="submit" name="cmm_register_submit">Register</button>
        </form>
        <?php
        if (isset($_POST['cmm_register_submit'])) {
            $username = sanitize_user($_POST['cmm_username']);
            $email = sanitize_email($_POST['cmm_email']);
            $password = sanitize_text_field($_POST['cmm_password']);
            $role = sanitize_text_field($_POST['cmm_membership']);

            $user_id = wp_create_user($username, $password, $email);
            if (!is_wp_error($user_id)) {
                $user = new WP_User($user_id);
                $user->set_role($role);
                echo '<p>Registration successful! You can now log in.</p>';
            } else {
                echo '<p>Registration failed: ' . $user_id->get_error_message() . '</p>';
            }
        }
        return ob_get_clean();
    }
}
CMM_Shortcodes::init();
