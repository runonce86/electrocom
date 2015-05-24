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

			add_action(
				'pre_get_posts',
				Array( $this, 'hide_children' )
			);

			add_action(
				'admin_action_create_child_post',
				Array( $this, 'create_child_post' )
			);

			add_filter(
				'page_row_actions',
				Array( $this, 'create_child_post_link' )
			);

			add_filter(
				'wp_insert_post_data',
				Array( $this, 'filter_post_data' )
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

		
		function hide_children( $query ) {

			remove_action( 'pre_get_posts', current_filter() );

			if ( is_admin() or ! $query->is_main_query() ) 
				return;

			if ( ! $query->is_post_type_archive( 'product' ) )
				return;

			// Only non-child posts
			$query->set( 'post_parent', 0 );
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
				'product_childs',

				__( 'Child products' ),

				Array( $this, 'metabox_childs' ),

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

		}

		/**
		 * Meta-box base_metabox_1
		 */ 
		function metabox_childs() {

			global $post;

			$format = '<p>%s</p>';
			printf( $format,  __( 'Each child product represents a variation of its parent. Add childs by relating another product to this one.' ) );

			$format = '<p><a href="admin.php?action=create_child_post&amp;post=%s" class="button">%s</a></p>';
			printf( $format, $post->ID, __( 'New child' ) );

			// List or table of child posts.

			$childs = get_children( Array(
					'post_parent' => $post->ID,
					'post_type' => 'product')
				);

			if( count( $childs ) > 0 ) {

				print '<ul>';

				foreach( $childs as $child ) {

					$format = '<li><a href="%s">%s (%s)</a></li>';
					$url = get_edit_post_link( $child->ID );

					printf( $format, $url, $child->post_title, $child->ID );
				}

				print '</ul>';
			}
		}

		function metabox_stock() {

			global $post;

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

		/*
		 * Function creates post duplicate as a draft and redirects then to the edit post screen
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

		function create_child_post_link( $actions ) {
			global $post;

			if (current_user_can('edit_posts') && $post->post_type == 'product') {
				$format = '<a href="admin.php?action=create_child_post&amp;post=%s">%s</a>';
				$actions['create_child'] = sprintf( $format, $post->ID, __( 'New child' ) );
			}
			return $actions;
		}

		function filter_post_data( $data ) {
			global $post;
			// TODO If $post has childs
			//	Force $post to post_parent = 0
		}
	}

	$theme = new starter_theme_ecommerce();
?>
