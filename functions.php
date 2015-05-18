<?php

	class starter_theme_ecommerce {

		function __construct() {

			add_action(
				'init',
				Array( $this, 'create_post_types' )
			);

			add_action(
				'add_meta_boxes',
				Array( $this, 'add_product_metaboxes' )
			);

			add_action(
				'save_post',
				Array( $this, 'custom_save' )
			);
		}

		/**
		 * Custom post types
		 *
		 * Add more custom post types using register_post_type().
		 */
		function create_post_types() {

			register_post_type( 'product',
				Array(
					'labels' => Array(
						'name' => __( 'Products' ),
						'singular_name' => __( 'Product' ),
						'add_new_item' => __( 'New product' ),
						'edit_item' => __( 'Edit product' ),
						'new_item' => __( 'New product' ),
						'view_item' => __( 'View product' ),
						'search_item' => __( 'Search products' ),
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
						'page-attributes'
					),
					'taxonomies' => Array(
						'category'
					)
				)
			);
		}

		/**
		 * Add meta-boxes to custom post types
		 */

		/**
		 * Meta-boxes for custom post type "product"
		 *
		 * Add more using add_meta_box()
		 */
		function add_product_metaboxes() {

			add_meta_box(
				'product_variation',

				__( 'Variations' ),

				Array( $this, 'metabox_variations' ),

				'product',
				'advanced',
				'high'
			);
		}

		/**
		 * Meta-box base_metabox_1
		 */ 
		function metabox_variations() {
			global $post;

			// Noncename needed to verify where the data originated
			echo '<input type="hidden" name="variations_noncename" id="variations_noncename" value="' . 
			wp_create_nonce( plugin_basename(__FILE__) ) . '" />';

			// Get the location data if its already been entered
			//$post_meta_value= get_post_meta($post->ID, 'price', true);
		
			// Echo out the field
			//echo '<input type="text" name="price" value="' . $post_meta_value. '" />';

			$format = '<p><input type="text" size="30" placeholder="%s" /> <a class="button" href="#">%s</a> <a class="button button-primary" href="#">%s</a></p>';
			printf( $format, __( 'Variation ID or title…' ), __( 'Search' ), __( 'New' ) );

			//echo '<hr />';
		}

		/**
		 * Save input (received from html form)
		 *
		 * $post_id is automatically passed by do_action().
		 */
		function custom_save( $post_id ) {

			// If $post_id is missing get it from global $post
			if( !$post_id ) {
				global $post;
				$post_id = $post->ID;
			}

			// Security check
			if ( !wp_verify_nonce( $_POST['_noncename'], plugin_basename(__FILE__) ) )
				return $post_id;

			// Is the user allowed to edit the post or page?
			if ( !current_user_can( 'edit_post', $post->ID ))
				return $post_id;

			// Input names we wanna save
			// TODO Avoid hard-coding input names?
			//$events_meta['price'] = $_POST['price'];
			
			// Add values of $events_meta as custom fields
			// Cycle through the $events_meta array!
			foreach( $events_meta as $key => $value ) {

				// Don't store custom data twice
				if( $post->post_type == 'revision' ) {
					return;
				}

				// If $value is an array, make it a CSV (unlikely)
				$value = implode( ',', (array) $value );

				// If the custom field already has a value
				if( get_post_meta( $post_id, $key, FALSE ) ) {
					update_post_meta( $post_id, $key, $value );
				}

				// If the custom field doesn't have a value
				else {
					add_post_meta( $post_id, $key, $value );
				}

				// Delete if blank
				if( !$value ) {
					delete_post_meta( $post_id, $key );
				}
			}
		}
	}

	$theme = new starter_theme_ecommerce();
?>
