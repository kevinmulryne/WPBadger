<?php

class WPBadger_Badge_Schema {
	private $post_type_name;

	function __construct() {
		$this->set_post_type_name();

		add_action( 'init', array( &$this, 'register_post_type' ) );
	}

	public function get_post_type_name() {
		return $this->post_type_name;
	}

	private function set_post_type_name() {
		$this->post_type_name = apply_filters( 'wpbadger_badge_post_type_name', 'badge' );
	}

	function register_post_type() {
		$labels = array(
			'name' => _x('Badges', 'post type general name'),
			'singular_name' => _x('Badge', 'post type singular name'),
			'add_new' => _x('Add New', 'badge'),
			'add_new_item' => __('Add New Badge'),
			'edit_item' => __('Edit Badge'),
			'new_item' => __('New Badge'),
			'all_items' => __('All Badges'),
			'view_item' => __('View Badge'),
			'search_items' => __('Search Badges'),
			'not_found' =>  __('No badges found'),
			'not_found_in_trash' => __('No badges found in Trash'),
			'parent_item_colon' => '',
			'menu_name' => 'Badges'
		);

		$args = array(
			'labels' => $labels,
			'public' => true,
			'query_var' => true,
			'rewrite'      => array(
				'slug'       => 'badges',
				'with_front' => false,
			),
			'capability_type' => 'post',
			'has_archive' => true,
			'hierarchical' => false,
			'supports' => array( 'title', 'editor', 'thumbnail' )
		);

		register_post_type( $this->get_post_type_name(), $args );
	}
}
new WPBadger_Badge_Schema();

add_action( 'load-post.php', 'wpbadger_badges_meta_boxes_setup' );
add_action( 'load-post-new.php', 'wpbadger_badges_meta_boxes_setup' );

function wpbadger_badges_meta_boxes_setup() {
	add_action( 'add_meta_boxes', 'wpbadger_add_badge_meta_boxes' );
	add_action( 'save_post', 'wpbadger_save_badge_meta', 10, 2 );
}

// Create metaboxes for post editor
function wpbadger_add_badge_meta_boxes() {

	add_meta_box(
		'wpbadger-badge-version',		// Unique ID
		esc_html__( 'Badge Version', 'example' ),	// Title
		'wpbadger_badges_meta_box',		// Callback function
		'badge',						// Admin page (or post type)
		'side',							// Context
		'default'						// Priority
	);
}

// Display metaboxes
function wpbadger_badges_meta_box( $object, $box ) { ?>

	<?php wp_nonce_field( basename( __FILE__ ), 'wpbadger_badge_nonce' ); ?>

	<p>
		<input class="widefat" type="text" name="wpbadger-badge-version" id="wpbadger-badge-version" value="<?php echo esc_attr( get_post_meta( $object->ID, 'wpbadger-badge-version', true ) ); ?>" size="30" />
	</p>
<?php }

function wpbadger_save_badge_meta( $post_id, $post ) {
	
	$post_type = get_post_type_object( $post->post_type );

	if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
		return $post_id;

    $new_meta_value = $_POST['wpbadger-badge-version'];
    if (preg_match( '/^\d+$/', $new_meta_value )) {
        $new_meta_value .= '.0';
    } elseif (!preg_match( '/^\d+(\.\d+)+$/', $new_meta_value )) {
        $new_meta_value = '1.0';
    }

	$meta_key = 'wpbadger-badge-version';
	$meta_value = get_post_meta( $post_id, $meta_key, true );

	if ( $new_meta_value && '' == $meta_value ) {
		add_post_meta( $post_id, $meta_key, $new_meta_value, true );
	} elseif ( $new_meta_value && $new_meta_value != $meta_value ) {
		update_post_meta( $post_id, $meta_key, $new_meta_value );
	} elseif ( '' == $new_meta_value && $meta_value ) {
		delete_post_meta( $post_id, $meta_key, $meta_value );		
	}
}

add_action( 'add_meta_boxes', 'wpbadger_change_badge_image_meta_box', 0 );

function wpbadger_change_badge_image_meta_box() {
	global $wp_meta_boxes;

	unset( $wp_meta_boxes['post']['side']['core']['postimagediv'] );
	add_meta_box('postimagediv',
	__('Badge Image'),
	'post_thumbnail_meta_box',
	'badge',
	'side',
	'low');
}

add_filter( 'user_can_richedit', 'wpbadger_disable_wysiwyg_for_badges' );

function wpbadger_disable_wysiwyg_for_badges( $default ) {
    global $post;
    if ( 'badge' == get_post_type( $post ) )
        return false;
    return $default;
}

/* Filter the content of the badge post type in the display, so badge metadata
including badge image are displayed on the page. */
add_filter( 'the_content', 'wpbadger_badge_content_filter' );

function wpbadger_badge_content_filter($content) {
	if (get_post_type() == 'badge') {
		return '<p>' . get_the_post_thumbnail(get_the_ID(), 'thumbnail', array('class' => 'alignright')) . get_the_content() . '</p>';
	} else {
		return $content;
	}
}

/* Filter the title of a badge post type in its display to include version */
add_filter( 'the_title', 'wpbadger_badge_title_filter', 10, 3 );

function wpbadger_badge_title_filter($title) {
	if (get_post_type() == 'badge' && in_the_loop()) {
		$new_title = $title . ' (Version ' . get_post_meta(get_the_ID(), 'wpbadger-badge-version', true) . ')';
		return $new_title;
	} else {
		return $title;
	}
}

add_filter('manage_badge_posts_columns', 'wpbadger_columns_badges', 10);  
add_action('manage_badge_posts_custom_column', 'wpbadger_columns_content_only_badges', 10, 2);  
  
function wpbadger_columns_badges	($defaults) {  
	$defaults['badge_version'] = 'Badge Version';
    return $defaults;  
}  
function wpbadger_columns_content_only_badges($column_name, $post_id) {  
    if ($column_name == 'badge_version') {  
		echo get_post_meta($post_id, 'wpbadger-badge-version', true);
    }
}
?>
