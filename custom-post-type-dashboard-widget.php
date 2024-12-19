<?php
/*
Plugin Name: Custom Post Types Dashboard Widget
Version: 1.0
Description: Add a customizable dashboard widget to display selected custom post types.
Author: phirebase
Author URI: https://phirebase.com/
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Enqueue the settings page styles.
function cptdw_enqueue_admin_styles($hook_suffix) {
    if ('settings_page_cptdw_settings' === $hook_suffix) {
        wp_enqueue_style('cptdw-admin-style', plugin_dir_url(__FILE__) . 'admin-style.css', [], '1.0');
    }
}
add_action('admin_enqueue_scripts', 'cptdw_enqueue_admin_styles');

// Register the settings page.
function cptdw_register_settings_page() {
    add_options_page(
        'CPT Widget Settings',
        'CPT Widget Settings',
        'manage_options',
        'cptdw_settings',
        'cptdw_render_settings_page'
    );
}
add_action('admin_menu', 'cptdw_register_settings_page');

// Render the settings page.
function cptdw_render_settings_page() {
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cpt_widget_types'])) {
        check_admin_referer('cptdw_settings');
        $selected_types = array_map('sanitize_text_field', wp_unslash($_POST['cpt_widget_types']));
        update_option('cptdw_selected_post_types', $selected_types);
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $selected_types = get_option('cptdw_selected_post_types', []);
    $post_types = get_post_types(['public' => true], 'objects');
    ?>
    <div class="wrap">
        <h1>CPT Widget Settings</h1>
        <form method="post">
            <?php wp_nonce_field('cptdw_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Select Post Types</th>
                    <td>
                        <?php foreach ($post_types as $post_type => $details) : ?>
                            <label>
                                <input type="checkbox" name="cpt_widget_types[]" value="<?php echo esc_attr($post_type); ?>" <?php checked(in_array($post_type, $selected_types, true)); ?>>
                                <?php echo esc_html($details->label); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Add individual dashboard widgets for each selected post type.
function cptdw_add_dashboard_widgets() {
    $selected_types = get_option('cptdw_selected_post_types', []);

    if (empty($selected_types)) {
        return;
    }

    foreach ($selected_types as $post_type) {
        $post_type_obj = get_post_type_object($post_type);
        if ($post_type_obj) {
            wp_add_dashboard_widget(
                'cptdw_dashboard_widget_' . $post_type,
                esc_html($post_type_obj->label) . ' Overview',
                function() use ($post_type) {
                    cptdw_render_dashboard_widget($post_type);
                }
            );
        }
    }
}
add_action('wp_dashboard_setup', 'cptdw_add_dashboard_widgets');

// Render a dashboard widget for a specific post type.
function cptdw_render_dashboard_widget($post_type) {
    $post_type_obj = get_post_type_object($post_type);
    if (!$post_type_obj) {
        echo '<p>Invalid post type.</p>';
        return;
    }

    $total = wp_count_posts($post_type);
    $published = isset($total->publish) ? $total->publish : 0;

    echo '<p>Total Published: ' . esc_html($published) . '</p>';

    $recent_posts = new WP_Query([
        'post_type'      => $post_type,
        'posts_per_page' => 5,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    if ($recent_posts->have_posts()) {
        echo '<ul class="cptdw-post-list">';
        while ($recent_posts->have_posts()) {
            $recent_posts->the_post();

            echo '<li>';
            echo '<a href="' . esc_url(get_edit_post_link()) . '">' . esc_html(get_the_title()) . '</a> - ' . esc_html(get_the_date());
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No published posts found.</p>';
    }

    wp_reset_postdata();
}

// Add CSS for styling the dashboard widgets.
function cptdw_add_admin_styles() {
    echo '<style>
        .cptdw-post-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .cptdw-post-list li {
            padding: 10px;
        }
        .cptdw-post-list li:nth-child(odd) {
            background-color: #f9f9f9;
        }
        .cptdw-post-list li:nth-child(even) {
            background-color: #e8f4fc;
        }
    </style>';
}
add_action('admin_head', 'cptdw_add_admin_styles');
