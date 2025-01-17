<?php
/**
Plugin Name: Custom Post Types Dashboard Widget
Description: Add a customizable dashboard widgets to display selected custom post types.
Version: 1.0.1
Author: phirebase
Author URI: https://phirebase.com/
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: custom-post-types-dashboard-widget
 *
 * @package CustomPostTypesDashboardWidget
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load the plugin's text domain for translations.
 */
function cptdw_load_textdomain() {
	load_plugin_textdomain(
		'custom-post-types-dashboard-widget',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'init', 'cptdw_load_textdomain' );

/**
 * Enqueue the settings page styles and scripts.
 *
 * @param string $hook_suffix The current admin page hook suffix.
 */
function cptdw_enqueue_dashboard_assets( $hook_suffix ) {
	if ( 'index.php' === $hook_suffix ) {
		wp_enqueue_style( 'cptdw-dashboard-style', plugin_dir_url( __FILE__ ) . 'css/admin-style.css', array(), '1.1' );
		wp_enqueue_script( 'cptdw-dashboard-script', plugin_dir_url( __FILE__ ) . 'js/admin-script.js', array( 'jquery' ), '1.1', true );
	}
}
add_action( 'admin_enqueue_scripts', 'cptdw_enqueue_dashboard_assets' );

/**
 * Register the settings page.
 */
function cptdw_register_settings_page() {
	add_options_page(
		__( 'Custom Post Widget Settings', 'custom-post-types-dashboard-widget' ), // Translated title.
		__( 'Custom Post Widget Settings', 'custom-post-types-dashboard-widget' ), // Translated menu title.
		'manage_options',
		'cptdw_settings',
		'cptdw_render_settings_page'
	);
}
add_action( 'admin_menu', 'cptdw_register_settings_page' );

/**
 * Render the settings page.
 */
function cptdw_render_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Custom Post Widget Settings', 'custom-post-types-dashboard-widget' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'cptdw_settings_group' );
			do_settings_sections( 'cptdw_settings' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Register settings.
 */
function cptdw_register_settings() {
	register_setting(
		'cptdw_settings_group',
		'cptdw_selected_post_types',
		array(
			'sanitize_callback' => 'cptdw_sanitize_post_types',
		)
	);

	add_settings_section(
		'cptdw_main_section',
		__( 'Widget Settings', 'custom-post-types-dashboard-widget' ),
		'__return_null',
		'cptdw_settings'
	);

	add_settings_field(
		'cptdw_post_types',
		__( 'Select Post Types', 'custom-post-types-dashboard-widget' ),
		'cptdw_render_post_types_field',
		'cptdw_settings',
		'cptdw_main_section'
	);
}
add_action( 'admin_init', 'cptdw_register_settings' );

/**
 * Add "Settings" link to plugin list actions.
 */
function cptdw_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=cptdw_settings' ) . '">' . __( 'Settings', 'custom-post-types-dashboard-widget' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cptdw_add_settings_link' );

/**
 * Sanitize post types.
 */
function cptdw_sanitize_post_types( $input ) {
	if ( ! is_array( $input ) ) {
		return array();
	}
	return array_map( 'sanitize_text_field', $input );
}

/**
 * Render the post types field.
 */
function cptdw_render_post_types_field() {
	$post_types          = get_post_types( array( 'public' => true ), 'objects' );
	$selected_post_types = get_option( 'cptdw_selected_post_types', array() );

	foreach ( $post_types as $post_type ) {
		// Exclude 'attachment' post type (Media).
		if ( $post_type->name === 'attachment' ) {
			continue;
		}

		?>
		<label>
			<input type="checkbox" name="cptdw_selected_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>"
				<?php checked( in_array( $post_type->name, $selected_post_types ) ); ?>>
			<?php echo esc_html( $post_type->label ); ?>
		</label><br>
		<?php
	}
}

/**
 * Add the dashboard widgets.
 */
function cptdw_add_dashboard_widgets() {
	$selected_post_types = get_option( 'cptdw_selected_post_types', array() );

	if ( empty( $selected_post_types ) ) {
		return;
	}

	foreach ( $selected_post_types as $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object ) {
			continue;
		}

		wp_add_dashboard_widget(
			'cptdw_dashboard_widget_' . $post_type,
			$post_type_object->label,
			function () use ( $post_type ) {
				cptdw_render_dashboard_widget( $post_type );
			}
		);
	}
}
add_action( 'wp_dashboard_setup', 'cptdw_add_dashboard_widgets' );

/**
 * Render a single dashboard widget.
 */
function cptdw_render_dashboard_widget( $post_type ) {
	$post_type_object = get_post_type_object( $post_type );
	if ( ! $post_type_object ) {
		/* translators: Displayed when the selected post type is invalid. */
		printf(
			'<p>%s</p>',
			esc_html__( 'Invalid post type.', 'custom-post-types-dashboard-widget' )
		);
		return;
	}

	$count = wp_count_posts( $post_type )->publish;

	/* translators: Text shown before the number of published posts. */
	$text = __( 'Total published:', 'custom-post-types-dashboard-widget' );
	printf( '<p>%s %d</p>', esc_html( $text ), intval( $count ) );

	$recent_posts = get_posts(
		array(
			'post_type'      => $post_type,
			'posts_per_page' => 5,
		)
	);

	if ( empty( $recent_posts ) ) {
		/* translators: Displayed when no recent posts are found for the post type. */
		printf(
			'<p>%s</p>',
			esc_html__( 'No recent posts.', 'custom-post-types-dashboard-widget' )
		);
	} else {
		echo '<ul class="cptdw-recent-posts">';
		foreach ( $recent_posts as $post ) {
			$title = get_the_title( $post ) ?: __( '(No title)', 'custom-post-types-dashboard-widget' );
			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( get_edit_post_link( $post->ID ) ),
				esc_html( $title )
			);
		}
		echo '</ul>';
	}
}
