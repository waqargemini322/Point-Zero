<?php
/**
 * Service Posting Form Function (Fixed)
 * Fixed version to prevent fatal errors and improve security
 */

defined('ABSPATH') || exit;

function project_theme_post_service_fn() {
    ob_start();

    global $wp_query, $projectOK, $current_user, $MYerror;
    $current_user = wp_get_current_user();

    $new_Project_step = isset($wp_query->query_vars['post_new_step']) ? intval($wp_query->query_vars['post_new_step']) : 1;
    $pid = isset($wp_query->query_vars['projectid']) ? intval($wp_query->query_vars['projectid']) : 1;
    $uid = $current_user->ID;

    // Initialize variables to prevent undefined errors
    if (!isset($projectOK)) $projectOK = 0;
    if (!isset($MYerror)) $MYerror = array();

    // Get post data safely
    $post = null;
    if (!empty($pid)) {
        $post = get_post($pid);
    }

    // Get categories safely
    $cat = array();
    if (taxonomy_exists('service_cat')) {
        $cat = wp_get_object_terms($pid, 'service_cat', array('order' => 'ASC', 'orderby' => 'term_id'));
    }

    // Display errors if any
    if (is_array($MYerror) && count($MYerror) > 0 && $projectOK == 0) {
        echo '<div class="alert alert-danger">';
        echo __('Your form has errors. Please check below, correct the errors, then submit again.', 'ProjectTheme');
        echo '</div>';
    }
    ?>

    <div id="content" class="sonita">
        <div class="card">
            <div class="box_content">
                <div class="sonita2">

                    <?php if ($new_Project_step == 1) { ?>

                        <form method="post" action="<?php echo esc_url(ProjectTheme_post_new_with_pid_stuff_thg_service($pid, '1')); ?>">
                            <?php wp_nonce_field('pt_service_post_' . $pid, 'service_nonce'); ?>
                            <ul class="post-new">

                                <li class="<?php echo esc_attr(function_exists('projecttheme_get_post_new_error_thing') ? projecttheme_get_post_new_error_thing('project_title') : ''); ?>">
                                    <?php 
                                    if (function_exists('projecttheme_get_post_new_error_thing_display')) {
                                        echo projecttheme_get_post_new_error_thing_display('project_title');
                                    }
                                    ?>

                                    <h2><?php echo __('Your service title', 'ProjectTheme'); ?></h2>
                                    <p>
                                        <input type="text" size="50" class="form-control full_wdth_me" name="project_title" 
                                               placeholder="<?php esc_attr_e('eg: I can create a website.', 'ProjectTheme') ?>" 
                                               value="<?php 
                                               echo esc_attr(
                                                   empty($_POST['project_title']) ? 
                                                   (($post && $post->post_title == "Auto Draft") ? "" : ($post ? $post->post_title : "")) : 
                                                   sanitize_text_field($_POST['project_title'])
                                               ); ?>" />
                                    </p>
                                </li>

                                <?php
                                $pst = '';
                                if ($post && $post->post_content) {
                                    $pst = $post->post_content;
                                    $pst = str_replace("<br />", "", $pst);
                                }
                                ?>

                                <li class="<?php echo esc_attr(function_exists('projecttheme_get_post_new_error_thing') ? projecttheme_get_post_new_error_thing('project_description') : ''); ?>">
                                    <?php 
                                    if (function_exists('projecttheme_get_post_new_error_thing_display')) {
                                        echo projecttheme_get_post_new_error_thing_display('project_description');
                                    }
                                    ?>

                                    <h2><?php echo __('Description', 'ProjectTheme'); ?></h2>
                                    <p>
                                        <textarea rows="6" cols="60" class="full_wdth_me form-control description_edit" 
                                                  placeholder="<?php esc_attr_e('Describe here your service scope.', 'ProjectTheme') ?>" 
                                                  name="project_description"><?php echo esc_textarea(trim($pst)); ?></textarea>
                                    </p>
                                </li>

                                <li>
                                    <h2><?php echo __('Service Budget', 'ProjectTheme'); ?></h2>
                                    <p class="strom_100">
                                        <?php 
                                        $price = get_post_meta($pid, 'price', true);
                                        $currency = function_exists('ProjectTheme_get_currency') ? ProjectTheme_get_currency() : '$';
                                        ?>
                                        <input type="number" required size="50" class="form-control full_wdth_me" 
                                               name="price" placeholder="<?php echo esc_attr($currency); ?>" 
                                               value="<?php echo esc_attr($price); ?>" />
                                    </p>
                                </li>

                                <li>
                                    <h3><?php _e('Attach Images', 'ProjectTheme'); ?></h3>
                                </li>

                                <li>
                                    <div class="cross_cross">
                                        <?php if (function_exists('projectTheme_generate_thumb')): ?>
                                            <script type="text/javascript" src="<?php echo esc_url(get_template_directory_uri()); ?>/js/dropzone.js"></script>
                                            <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/css/dropzone.css" type="text/css" />

                                            <script>
                                            Dropzone.autoDiscover = false;

                                            jQuery(function() {
                                                Dropzone.autoDiscover = false;
                                                var myDropzoneOptions = {
                                                    maxFilesize: 15,
                                                    addRemoveLinks: true,
                                                    acceptedFiles: 'image/*',
                                                    clickable: true,
                                                    url: "<?php echo esc_url(home_url()); ?>/?my_upload_of_project_files2=1",
                                                };

                                                var myDropzone = new Dropzone('div#myDropzoneElement2', myDropzoneOptions);

                                                myDropzone.on("sending", function(file, xhr, formData) {
                                                    formData.append("author", "<?php echo esc_js($uid); ?>");
                                                    formData.append("ID", "<?php echo esc_js($pid); ?>");
                                                });

                                                <?php
                                                $args = array(
                                                    'order' => 'ASC',
                                                    'orderby' => 'menu_order',
                                                    'post_type' => 'attachment',
                                                    'post_parent' => $pid,
                                                    'post_status' => null,
                                                    'post_mime_type' => 'image',
                                                    'numberposts' => -1,
                                                );
                                                $attachments = get_posts($args);

                                                if ($pid > 0 && $attachments) {
                                                    foreach ($attachments as $attachment) {
                                                        $url = wp_get_attachment_url($attachment->ID);
                                                        $thumb_url = function_exists('projectTheme_generate_thumb') ? 
                                                            projectTheme_generate_thumb($attachment->ID, 100, 100) : 
                                                            $url;
                                                        ?>
                                                        var mockFile = { 
                                                            name: "<?php echo esc_js($attachment->post_title); ?>", 
                                                            size: 12345, 
                                                            serverId: '<?php echo esc_js($attachment->ID); ?>' 
                                                        };
                                                        myDropzone.options.addedfile.call(myDropzone, mockFile);
                                                        myDropzone.options.thumbnail.call(myDropzone, mockFile, "<?php echo esc_url($thumb_url); ?>");
                                                        <?php
                                                    }
                                                }
                                                ?>

                                                myDropzone.on("success", function(file, response) {
                                                    file.serverId = response;
                                                    file.thumbnail = "<?php echo esc_url(get_template_directory_uri()); ?>/images/file_icon.png";
                                                });

                                                myDropzone.on("removedfile", function(file, response) {
                                                    if (typeof delete_this2 === 'function') {
                                                        delete_this2(file.serverId);
                                                    }
                                                });
                                            });
                                            </script>

                                            <?php _e('Click the grey area below to add project images. Other files are not accepted. Use the form below.', 'ProjectTheme'); ?>
                                            <div class="dropzone dropzone-previews" id="myDropzoneElement2"></div>
                                        <?php else: ?>
                                            <p><?php _e('Image upload functionality requires ProjectTheme to be properly configured.', 'ProjectTheme'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </li>

                                <li>
                                    <h2>&nbsp;</h2>
                                    <p>
                                        <input type="submit" name="project_submit1" 
                                               value="<?php esc_attr_e("Submit Service", 'ProjectTheme'); ?>" 
                                               class="btn btn-primary" />
                                    </p>
                                </li>

                            </ul>
                        </form> 

                    <?php } ?>

                    <?php if ($new_Project_step == 2) { ?>
                        <?php 
                        $my_account_url = get_permalink(get_option('ProjectTheme_my_account_page_id'));
                        if (!$my_account_url) {
                            $my_account_url = home_url();
                        }
                        ?>
                        <div class="alert alert-success">
                            <?php echo sprintf(
                                __('Your service has been posted. <a href="%s">Click here</a> to go to your account.', 'ProjectTheme'), 
                                esc_url($my_account_url)
                            ); ?>
                        </div>
                    <?php } ?>

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
