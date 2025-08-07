<?php
/**
 * Service Category Widget (Fixed)
 * Fixed version to prevent fatal errors and improve security
 */

defined('ABSPATH') || exit;

// Register the widget
add_action('widgets_init', 'projecttheme_register_service_category_widget');

function projecttheme_register_service_category_widget() {
    if (class_exists('WP_Widget')) {
        register_widget('ProjectTheme_Service_Category_Widget');
    }
}

class ProjectTheme_Service_Category_Widget extends WP_Widget {

    function __construct() {
        $widget_ops = array(
            'classname' => 'service-category-widget', 
            'description' => __('Display service categories with thumbnails', 'ProjectTheme')
        );
        
        parent::__construct(
            'service-category-widget',
            __('ProjectTheme - Service Categories', 'ProjectTheme'),
            $widget_ops
        );
    }

    function widget($args, $instance) {
        // Check if service categories exist
        if (!taxonomy_exists('service_cat')) {
            return;
        }

        extract($args);

        echo $before_widget;

        if (!empty($instance['title'])) {
            echo $before_title . apply_filters('widget_title', $instance['title']) . $after_title;
        }

        // Get service categories
        $terms = get_terms(array(
            'taxonomy' => 'service_cat',
            'hide_empty' => false,
            'parent' => 0,
            'number' => isset($instance['count']) ? intval($instance['count']) : 10
        ));

        if (!is_wp_error($terms) && !empty($terms)) {
            echo '<div class="service-categories-widget">';
            
            foreach ($terms as $term) {
                $term_link = get_term_link($term);
                if (!is_wp_error($term_link)) {
                    echo '<div class="service-category-item">';
                    echo '<a href="' . esc_url($term_link) . '">';
                    echo '<span class="category-name">' . esc_html($term->name) . '</span>';
                    if ($term->count > 0) {
                        echo '<span class="category-count">(' . intval($term->count) . ')</span>';
                    }
                    echo '</a>';
                    echo '</div>';
                }
            }
            
            echo '</div>';
            
            // Add basic styling
            ?>
            <style>
            .service-categories-widget .service-category-item {
                margin-bottom: 8px;
                padding: 5px 0;
                border-bottom: 1px solid #eee;
            }
            .service-categories-widget .service-category-item:last-child {
                border-bottom: none;
            }
            .service-categories-widget .category-count {
                color: #666;
                font-size: 0.9em;
                margin-left: 5px;
            }
            </style>
            <?php
        } else {
            echo '<p>' . __('No service categories found.', 'ProjectTheme') . '</p>';
        }

        echo $after_widget;
    }

    function form($instance) {
        $title = isset($instance['title']) ? esc_attr($instance['title']) : __('Service Categories', 'ProjectTheme');
        $count = isset($instance['count']) ? intval($instance['count']) : 10;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php _e('Title:', 'ProjectTheme'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" 
                   value="<?php echo esc_attr($title); ?>" />
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('count')); ?>">
                <?php _e('Number of categories to show:', 'ProjectTheme'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('count')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('count')); ?>" type="number" 
                   value="<?php echo esc_attr($count); ?>" min="1" max="20" />
        </p>
        <?php
    }

    function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['count'] = (!empty($new_instance['count'])) ? intval($new_instance['count']) : 10;
        
        return $instance;
    }
}

?>