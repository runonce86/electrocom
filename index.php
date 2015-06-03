<?php
/**
 * Plugin Name: Electrocom
 * Description: Electronic commerce.
 * Plugin URI: https://github.com/runonce86/electrocom
 * Author: Camilo Rivera
 * Author URI: https://github.com/runonce86
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define class
if ( ! class_exists( 'Electrocom' ) ) {

	class Electrocom {

		/**
		 * Add actions and filters.
		 *
		 */
		function __construct() {

			/**
			 * Models
			 *
			 */
			add_action(
				'init',
				Array( $this, 'create_product_type' )
			);

			add_action(
				'add_meta_boxes',
				Array( $this, 'add_product_meta' )
			);

			add_action(
				'save_post_product',
				Array( $this, 'save_product_meta' ),
				null,
				2
			);

			add_action(
				'save_post_product',
				Array( $this, 'variations_rules' ),
				null,
				2
			);

			/**
			 * Views
			 *
			 */
			add_action(
				'admin_enqueue_scripts',
				Array( $this, 'load_style' )
			);

			add_action(
				'admin_enqueue_scripts',
				Array( $this, 'load_admin_scripts' )
			);

			/**
			 * Controllers
			 *
			 */
			add_action(
				'admin_action_create_child_post',
				Array( $this, 'create_child_post' )
			);
		}

		function load_admin_scripts() {

			// TODO Only load when posting a new Product

			wp_register_script(
				'script',
				plugin_dir_url( __FILE__ ) . 'script.js'
			);

			// Pre-load images metadata
			$args = Array(
				'post_type' => 'attachment',
				'post_mime_type' =>'image',
				'post_status' => 'inherit',
				'posts_per_page' => -1,
			);

			$images = new WP_Query( $args );
			$images = $images->posts;

			foreach( $images as $image ) {

				$thumbnails[$image->ID] = wp_get_attachment_image_src( $image->ID );
			}

			// Send thumbnails data
			wp_localize_script(
				'script',
				'elcom_images',
				$thumbnails
			);

			wp_enqueue_script(
				'script'
			);
		}

		/**
		 * Create Product post type.
		 *
		 */
		function create_product_type() {

			register_post_type( 'product',
				Array(
					'labels' => Array(
						'name' => __( 'Products' ),
						'singular_name' => __( 'Product' ),
						'all_items' => __( 'All products' ),
						'add_new_item' => __( 'New product' ),
						'edit_item' => __( 'Edit product' ),
						'new_item' => __( 'New product' ),
						'view_item' => __( 'View product' ),
						'search_items' => __( 'Search products' ),
						'not_found' => __( 'No products found' ),
						'not_found_in_trash' => __( 'No products found in trash' ),
						'parent_item_colon' => __( 'Parent product' )
					),
					'public' => true,
					'has_archive' => 'products',
					'hierarchical' => true,
					'supports' => Array(
						'title',
						'editor',
						'page-attributes',
						'custom-fields'
					),
					'taxonomies' => Array(
						'category',
						'post_tag'
					),
					'menu_position' => 5,
					'menu_icon' => 'dashicons-cart'
				)
			);
		}

		/**
		 * Add Product post type meta.
		 *
		 */
		function add_product_meta() {

			// TODO Hide Variations for posts with parent > 0
			add_meta_box(
				'product_variations',
				__( 'Variations' ),
				Array( $this, 'metabox_variations' ),
				'product',
				'side',
				'high'
			);

			add_meta_box(
				'product_stock',
				__( 'Stock' ),
				Array( $this, 'metabox_stock' ),
				'product',
				'side'
			);

			add_meta_box(
				'product_price',
				__( 'Price' ),
				Array( $this, 'metabox_price' ),
				'product',
				'side'
			);

			add_meta_box(
				'product_images',
				__( 'Images' ),
				Array( $this, 'metabox_images' ),
				'product'
			);
		}

		/**
		 * Variations metabox.
		 *
		 */ 
		function metabox_variations() {

			global $post;

			$format = '<div class="action"><a href="admin.php?action=create_child_post&amp;post=%s" target="_blank" class="button">%s</a></div>';
			printf( $format, $post->ID, __( 'New variation' ) );

			// List of child posts.
			$children = get_children( Array(
					'post_parent' => $post->ID,
					'post_type' => 'product')
				);

			if( count( $children ) > 0 ) {

				print '<ul>';

				foreach( $children as $child ) {

					$format = '<li><a href="%s">%s (%s)</a></li>';
					$url = get_edit_post_link( $child->ID );

					printf( $format, $url, $child->post_title, $child->ID );
				}

				print '</ul>';
			}
		}

		/**
		 * Price metabox.
		 *
		 */ 
		function metabox_price() {

			global $post;

			// Noncename needed to verify where the data originated
			$nonce = wp_create_nonce( plugin_basename ( __FILE__ ) );
			$format = '<input type="hidden" name="_product_price_nonce" value="%s" />'; 
			printf( $format, $nonce );

			// Get the location data if its already been entered
			$value = get_post_meta( $post->ID, '_product_price', true );
		
			// Echo out the field
			$format = '<div class="input"><input name="_product_price" type="number" min="0" value="%s" /></div>';
			printf( $format, $value );
		}

		/**
		 * Stock metabox.
		 *
		 */ 
		function metabox_stock() {

			global $post;

			// Noncename needed to verify where the data originated
			$nonce = wp_create_nonce( plugin_basename ( __FILE__ ) );
			$format = '<input type="hidden" name="_product_stock_nonce" value="%s" />'; 
			printf( $format, $nonce );

			// Get the location data if its already been entered
			$value = get_post_meta( $post->ID, '_product_stock', true );
		
			// Echo out the field
			$format = '<div class="input"><input name="_product_stock" type="number" min="0" value="%s" /></div>';
			printf( $format, $value );


		}

		function metabox_images() {

			print '<div><a href="#" class="button insert-media" data-editor="product-images">Insert images</a></div>';

		}

		/**
		 * Save product meta.
		 *
		 */
		function save_product_meta( $post_id, $post ) {

			// Avoid infinite loop
			remove_action(
				'save_post_product',
				Array( $this, 'save_product_meta' ),
				null,
				2
			);
		
			// Input names we wanna save
			// TODO Avoid hard-coding input names?
			$events_meta['_product_price'] = $_POST['_product_price'];
			$events_meta['_product_stock'] = $_POST['_product_stock'];

			// Is the user allowed to edit the post or page?
			if ( ! current_user_can( 'edit_post', $post->ID )) {
				return $post_id;
			}

			// Add values of $events_meta as custom fields
			// Cycle through the $events_meta array!
			foreach( $events_meta as $key => $value ) {

				// Security check
				if ( ! wp_verify_nonce( $_POST[$key . '_nonce'], plugin_basename( __FILE__ ) ) ) {
					return $post_id;
				}

				// Don't store custom data twice
				if( $post->post_type == 'revision' ) {
					return;
				}

				// If the custom field already has a value
				if( get_post_meta( $post_id, $key, FALSE ) ) {
					update_post_meta( $post_id, $key, $value );
				}

				// If the custom field doesn't have a value
				else {
					add_post_meta( $post_id, $key, $value );
				}

				// Delete if blank
				if( ! $value ) {
					delete_post_meta( $post_id, $key );
				}
			}
		}

		/**
		 * Rules for product's variations.
		 *
		 */
		function variations_rules( $post_id, $post ) {

			// Avoid infinite loop
			remove_action(
				'save_post_product',
				Array( $this, 'variations_rules' ),
				null,
				2
			);

			// Avoid adding a child to an already child post
			if( $post->post_parent > 0 ) {
				$ancestors = get_post_ancestors( $post_id );

				// If more than one ancestors
				if( count( $ancestors ) > 1 ) {

					// Parent is also a child so it's not allowed
					$this->remove_parent( $post_id );
				}
			}

			// Avoid adding parent to a post with childs
			$args = Array(
				'post_parent' => $post_id,
				'post_type' => 'product'
			);

			$children = get_children( $args );

			if( count( $children ) > 0 ) {

				// Remove post parent
				$this->remove_parent( $post_id );

			}
		}

		/**
		 * Add styles.
		 *
		 */
		function load_style() {

			wp_register_style(
				'style',
				plugin_dir_url( __FILE__ ) . 'style.css'
			);

			wp_enqueue_style(
				'style'
			);
		}

		/**
		 * Function creates post duplicate as a draft and redirects then to the edit post screen.
		 *
		 */
		function create_child_post() {

			global $wpdb;

			// Validate parent post ID
			if( isset( $_GET['post'] ) ) {
				$post_id = $_GET['post'];
			}

			elseif( isset( $_POST['post'] ) ) {
				$post_id = $_POST['post'];
			}

			else {
				wp_die( __( 'Missing required argument.' ) );
			}

			$post_id = ( int ) $post_id;

			if( $post_id < 1 ) {
				wp_die( __( 'Invalid argument.' ) );
			}

			// Get parent post
			$post = get_post( $post_id );

			if( !$post ) {
				wp_die( __( 'Post not found.' ) );
			}

			// Post author
			$current_user = wp_get_current_user();
			$author = $current_user->ID;

			// New post data array
			$args = array(
				'post_author'    => $author,
				'post_content'   => $post->post_content,
				'post_excerpt'   => $post->post_excerpt,
				'post_name'      => $post->post_name,
				'post_parent'    => $post->ID,
				'post_status'    => 'draft',
				'post_title'     => $post->post_title,
				'post_type'      => $post->post_type
			);

			// Insert the post by wp_insert_post() function
			$new_post_id = wp_insert_post( $args );

			// Get all current post terms and set them to the new post draft
			// Returns array of taxonomy names for post type, ex array("category", "post_tag")
			$taxonomies = get_object_taxonomies( $post->post_type );

			foreach( $taxonomies as $taxonomy ) {

				$post_terms = wp_get_object_terms( $post_id, $taxonomy, Array( 'fields' => 'slugs' ) );
				wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
			}

			// Duplicate all post meta
			$post_meta_infos = $wpdb->get_results( "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id" );

			if ( count( $post_meta_infos ) !=0 ) {

				$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";

				foreach( $post_meta_infos as $meta_info ) {

					$meta_key = $meta_info->meta_key;
					$meta_value = addslashes( $meta_info->meta_value );
					$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
				}

				$sql_query.= implode( " UNION ALL ", $sql_query_sel );
				$wpdb->query( $sql_query );
			}

			// Finally, redirect to the edit post screen for the new draft
			wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
		}

		/**
		 * Helper for removing parent post.
		 *
		 */
		function remove_parent( $post_id ) {
			wp_update_post(
				Array(
					'ID' => $post_id,
					'post_parent' => 0
				)
			);
		}
	}
}

// Create instance
new Electrocom();
?>
