<?php
/*
Plugin Name: Electronic commerce
Description: Adds post type Product.
Author: Camilo Rivera
Author URI: https://github.com/runonce86
*/

	class starter_theme_ecommerce {

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
				'save_post',
				Array( $this, 'save_product_meta' )
			);

			add_action(
				'save_post',
				Array( $this, 'variations_rules' )
			);

			/**
			 * Views
			 *
			 */
			add_action(
				'pre_get_posts',
				Array( $this, 'hide_product_variations' )
			);

			add_action(
				'admin_enqueue_scripts',
				Array( $this, 'load_admin_style' )
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
						'page-attributes'
					),
					'taxonomies' => Array(
						'category',
						'post_tag'
					)
				)
			);
		}


		/**
		 * Add Product post type meta.
		 *
		 */
		function add_product_meta() {

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
		}

		/**
		 * Variations metabox.
		 *
		 */ 
		function metabox_variations() {

			global $post;

			$format = '<div class="action"><a href="admin.php?action=create_child_post&amp;post=%s" target="_blank" class="button">%s</a></div>';
			printf( $format, $post->ID, __( 'New child' ) );

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
			print '<div class="input"><input type="number" min="0" step="50" /></div>';
		}

		/**
		 * Stock metabox.
		 *
		 */ 
		function metabox_stock() {

			global $post;

			print '<div class="input"><input type="number" min="0" /></div>';

		}

		/**
		 * Save product meta.
		 *
		 */
		function save_product_meta( $post_id ) {

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

		/**
		 * Rules for product's variations.
		 *
		 */
		function variations_rules( $post_id ) {

			// Avoid infinite loop
			remove_action(
				'save_post',
				Array( $this, 'variations_rules' )
			);

			// Avoid adding a child to an already child post
			$post = get_post( $post_id );

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
		 * Remove product variations from archive loop.
		 *
		 */		
		function hide_product_variations( $query ) {

			remove_action( 'pre_get_posts', current_filter() );

			if ( is_admin() or ! $query->is_main_query() ) 
				return;

			if ( ! $query->is_post_type_archive( 'product' ) )
				return;

			// Only non-child posts
			$query->set( 'post_parent', 0 );
		}

		/**
		 * Add admin styles.
		 *
		 */
		function load_admin_style() {

                        wp_register_style(
                                'admin-style',
                                plugin_dir_url( __FILE__ ) . 'admin-style.css'
                        );

                        wp_enqueue_style(
                                'admin-style'
                        );
		}

		/**
		 * Function creates post duplicate as a draft and redirects then to the edit post screen.
		 *
		 */
		function create_child_post() {

			global $wpdb;

			if (! ( isset( $_GET['post']) || isset( $_POST['post'])  || ( isset($_REQUEST['action']) && 'create_child_post' == $_REQUEST['action'] ) ) ) {
				wp_die('No post to duplicate has been supplied!');
			}

			// Get the original post id
			$post_id = (isset($_GET['post']) ? $_GET['post'] : $_POST['post']);

			// And all the original post data then
			$post = get_post( $post_id );

			// Post author
			$current_user = wp_get_current_user();
			$new_post_author = $current_user->ID;

			// If post data exists, create the post duplicate
			if (isset( $post ) && $post != null) {

				// New post data array
				$args = array(
					'comment_status' => $post->comment_status,
					'ping_status'    => $post->ping_status,
					'post_author'    => $new_post_author,
					'post_content'   => $post->post_content,
					'post_excerpt'   => $post->post_excerpt,
					'post_name'      => $post->post_name,
					'post_parent'    => $post->ID,
					'post_password'  => $post->post_password,
					'post_status'    => 'draft',
					'post_title'     => $post->post_title,
					'post_type'      => $post->post_type,
					'to_ping'        => $post->to_ping,
					'menu_order'     => $post->menu_order
				);

				// Insert the post by wp_insert_post() function
				$new_post_id = wp_insert_post( $args );

				// Get all current post terms ad set them to the new post draft
				// Returns array of taxonomy names for post type, ex array("category", "post_tag")
				$taxonomies = get_object_taxonomies($post->post_type);
				foreach ($taxonomies as $taxonomy) {
					$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
					wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
				}

				// Duplicate all post meta
				$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
				if (count($post_meta_infos)!=0) {
					$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
					foreach ($post_meta_infos as $meta_info) {
						$meta_key = $meta_info->meta_key;
						$meta_value = addslashes($meta_info->meta_value);
						$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
					}
					$sql_query.= implode(" UNION ALL ", $sql_query_sel);
					$wpdb->query($sql_query);
				}

				// Finally, redirect to the edit post screen for the new draft
				wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
				exit;
			} else {
				wp_die('Post creation failed, could not find original post: ' . $post_id);
			}
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

	$theme = new starter_theme_ecommerce();
?>
