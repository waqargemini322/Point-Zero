<?php
/**
 * My Services Account Page Function (Fixed)
 * Fixed version to prevent fatal errors and improve security
 */

defined('ABSPATH') || exit;

function project_theme_account_my_services() {
    ob_start();

    global $current_user, $wp_query;
    $current_user = wp_get_current_user();
    $uid = $current_user->ID;

    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '<div class="alert alert-warning">' . __('Please log in to view your services.', 'ProjectTheme') . '</div>';
    }

    // Include aside menu if function exists
    if (function_exists('get_template_part')) {
        get_template_part('lib/my_account/aside-menu');
    }
    ?>

    <div class="page-wrapper" style="display:block">
        <div class="container-fluid">

            <?php
            // Fire action hook if it exists
            if (has_action('pt_for_demo_work_3_0')) {
                do_action('pt_for_demo_work_3_0');
            }
            ?>

            <div class="container">
                <div class="row">
                    <div class="col-sm-12 col-lg-12">
                        <div class="page-header">
                            <h1 class="page-title">
                                <?php echo sprintf(__('Services', 'ProjectTheme')); ?>
                            </h1>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="account-main-area col-xs-12 col-sm-12 col-md-12 col-lg-12">

                        <div class="w-100 mb-3">
                            <?php 
                            $post_service_page = get_option('ProjectTheme_post_service');
                            if ($post_service_page) {
                                ?>
                                <a href="<?php echo esc_url(get_permalink($post_service_page)); ?>" class="btn btn-success btn-sm">
                                    <?php _e('Post New Service', 'ProjectTheme'); ?>
                                </a>
                                <?php
                            }
                            ?>
                        </div>

                        <?php
                        // Query user's services
                        global $wp_query;
                        $query_vars = $wp_query->query_vars;
                        $post_per_page = 5;

                        // Initialize meta query array
                        $meta_query = array();

                        // Add closed filter
                        $closed = array(
                            'key' => 'closed',
                            'value' => "0",
                            'compare' => '='
                        );
                        $meta_query[] = $closed;

                        // Build query arguments
                        $args = array(
                            'post_type' => 'service', 
                            'author' => $uid, 
                            'order' => 'DESC', 
                            'orderby' => 'date', 
                            'posts_per_page' => $post_per_page,
                            'paged' => 1, 
                            'meta_query' => $meta_query, 
                            'post_status' => array('draft', 'publish')
                        );

                        // Check if service post type exists
                        if (!post_type_exists('service')) {
                            echo '<div class="alert alert-warning">';
                            _e("Service post type is not registered. Please ensure the plugin is properly activated.", 'ProjectTheme');
                            echo '</div>';
                        } else {
                            $services_query = new WP_Query($args);

                            if ($services_query->have_posts()) :
                                ?>
                                <div class="services-list">
                                    <?php
                                    while ($services_query->have_posts()) : 
                                        $services_query->the_post();
                                        
                                        // Call the service display function if it exists
                                        if (function_exists('projectTheme_get_service_acc')) {
                                            projectTheme_get_service_acc();
                                        } else {
                                            // Fallback display if function doesn't exist
                                            ?>
                                            <div class="card section-vbox padd20">
                                                <div class="padd10 nopadd-top">
                                                    <h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
                                                    <div class="excerpt-thing">
                                                        <?php the_excerpt(); ?>
                                                    </div>
                                                    <div class="service-meta">
                                                        <span class="posted-date">
                                                            <i class="fa fa-calendar"></i> <?php echo get_the_date(); ?>
                                                        </span>
                                                        <?php if (get_post_status() == 'draft'): ?>
                                                            <span class="badge badge-warning"><?php _e('Draft', 'ProjectTheme'); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="service-actions">
                                                        <a href="<?php the_permalink(); ?>" class="btn btn-light btn-sm">
                                                            <i class="fas fa-book"></i> <?php _e("View", "ProjectTheme"); ?>
                                                        </a>
                                                        <a href="<?php echo esc_url(home_url()); ?>/?p_action=edit_project&pid=<?php the_ID(); ?>" class="btn btn-light btn-sm">
                                                            <i class="far fa-edit"></i> <?php _e("Edit", "ProjectTheme"); ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php
                                        }
                                    endwhile;
                                    ?>
                                </div>

                                <?php
                                // Pagination if needed
                                if ($services_query->max_num_pages > 1) {
                                    echo '<div class="pagination-wrapper">';
                                    echo paginate_links(array(
                                        'total' => $services_query->max_num_pages,
                                        'current' => max(1, get_query_var('paged')),
                                        'prev_text' => __('« Previous', 'ProjectTheme'),
                                        'next_text' => __('Next »', 'ProjectTheme'),
                                    ));
                                    echo '</div>';
                                }

                            else:
                                echo '<div class="card">';
                                echo '<div class="box_content padd20">';
                                _e("You have no services yet.", 'ProjectTheme');
                                echo '</div>';
                                echo '</div>';
                            endif;

                            // Reset post data
                            wp_reset_postdata();
                        }
                        ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $data = ob_get_contents();
    ob_end_clean();
    return $data;
}

?>
