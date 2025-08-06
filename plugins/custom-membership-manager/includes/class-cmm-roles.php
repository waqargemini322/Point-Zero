<?php
class CMM_Roles {
    public static function add_roles() {
        add_role('member_free', 'Free Member', ['read' => true]);
        add_role('member_silver', 'Silver Member', ['read' => true, 'limited_messaging' => true]);
        add_role('member_gold', 'Gold Member', ['read' => true, 'unlimited_messaging' => true]);
    }

    public static function remove_roles() {
        remove_role('member_free');
        remove_role('member_silver');
        remove_role('member_gold');
    }
}
