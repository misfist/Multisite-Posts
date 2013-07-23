<?php
/**
 * Plugin Name: Multisite Posts
 * Plugin URI: http://angelawang.me/
 * Description: Get posts from another child site in a multisite setup
 * Author: Angela
 * Version: 2.0
 * Author URI: http://angelawang.me/
 * License: GPL2
 *
 * Copyright 2013 Angela Wang (email : idu.angela@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
/*
TODO:
1- Fix Styling
2- Editor Button
3- Custom Query
 */

class Multisite_Posts {

	function __construct($options = array(), $blog_id = false) {

		$this->default 		= array(
			"post_no"		=> 10,
			"excerpt" 	=> false,
			"category"	=> "",
			"thumbnail" => false,
		);
		$this->transient 	= "msp_posts";
		$this->duration 	= 60 * 60 * 6;
		$this->blog_id 		= !empty($blog_id) ? $blog_id : get_current_blog_id();
		$this->options 		= $options ? shortcode_atts( $this->default, $options ) : $this->default;
		$this->domain 		= "MSP";
		$this->query 			= array(
			"cat"							=> $this->options["category"],
			"paged"						=> 1,
			"post_status"			=> "publish",
			"posts_per_page"	=> $this->options["post_no"],
		);

		load_plugin_textdomain( "MSP", false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		add_action( "admin_footer", array($this, "admin_footer_callback") );
		add_action( "wp_enqueue_scripts", array($this, "wp_enqueue_scripts_callback") );
		add_action( "admin_enqueue_scripts", array($this, "admin_enqueue_scripts_callback") );
		add_action( "media_buttons_context", array($this, "media_buttons_context_callback") );
		add_action( "wp_schedule_event_callback", array($this, "wp_schedule_event_callback") );

		add_filter( 'the_excerpt', 'do_shortcode' );
		add_filter( 'widget_text', 'do_shortcode' );

		add_shortcode( "multisite_posts", array($this, "multisite_posts_shortcode_callback") );

		register_activation_hook( __FILE__, array($this, "install") );
		register_deactivation_hook( __FILE__, array($this, "uninstall") );

	}

	function install() {

		wp_schedule_event( current_time( 'timestamp' ), 'twicedaily', "wp_schedule_event_callback" );

	}

	function uninstall() {

		wp_clear_scheduled_hook( 'wp_schedule_event_callback' );
		delete_transient( $this->transient );

	}

	//Get a List Of Blogs
	function get_blog_list($type = "plain") {

		global $wpdb;

		$blogs_table 	= $wpdb->base_prefix . "blogs";
		$blogs_list 	= $wpdb->get_results( "
			SELECT blog_id, domain FROM {$blogs_table} ORDER BY blog_id"
		);
		$output 			= "";

		foreach ($blogs_list as $blog) {

			switch ($type) {
				case "plain":
					$output[$blog->blog_id] = $blog->domain;
					break;
				case "dropdown":
					$output .= '<option name="' . $blog->blog_id . '">' . $blog->domain . '</option>';
					break;
			}

		}

		return $output;

	}

	//Find match based on options and blog_id
	function options_deep_search($msp_posts, $options, $blog_id) {

		if( !empty($msp_posts) ) {

			foreach ($msp_posts as $index => $one_msp_posts) {

				if($one_msp_posts["criteria"] == $options && $one_msp_posts["blog_id"] == $blog_id) {

					return $index;

				}

				continue;

			}

		}

		return false;

	}

	//Append to List of Options if fit
	//TODO - Allow Custom Query
	function populate_msp_posts($msp_posts, $blog_id, $options) {

		switch_to_blog($blog_id);

		$index = $this->options_deep_search($msp_posts, $options, $blog_id);

		if( !$index ) {

			//Append New Items If Not Exist
			$new_posts = new WP_Query( $this->query );

			array_push( $msp_posts, array(
				"criteria"	=> $options,
				"all_post" 	=> $new_posts,
				"blog_id"		=> $blog_id,
			) );

		}

		restore_current_blog();

		return $msp_posts;

	}

	//Display the posts
	function display_msp_posts($one_msp_posts, $echo = false, $options = false) {

		$blog_id 	= $one_msp_posts["blog_id"];
		$all_post = $one_msp_posts["all_post"];
		$criteria = $one_msp_posts["criteria"];
		$output 	= '<ul class="blog-posts" data-blogid="' . $blog_id . '">';

		if($all_post->post_count > 0) {

			while($all_post->have_posts()) {

				$all_post->the_post();

				$post_id 		= get_the_ID();
				$post_title = get_the_title();

				$output 	 .= '<li>';

				if( !empty($criteria["thumbnail"]) ) {
					if( has_post_thumbnail() ) {
						$output .= get_the_post_thumbnail( array(100, 100) );
					} else {
						$output .= '<img class="msp-thumbnail" src="' . plugins_url("assets/msp_noimg.jpg", __FILE__) . '" />';
					}
				}

				$output 	 .= '<div class="msp-content"><h3><a href="' . get_post_permalink() . '" title="' . $post_title . '">' . $post_title . '</a></h3>';
				if( !empty($criteria["excerpt"]) ) {
					$output  .= '<p>' . get_the_excerpt() . '</p>';
				}

				$output 	 .= '</div></li>';

			}

			wp_reset_postdata();

		}

		$output  .= '</ul>';

		if($echo) {
			echo $output;
			return;
		} else {
			return $output;
		}

	}

	//Obtain msp posts for one Blog
	function fetch_msp_posts($options = false, $blog_id = false, $echo = false) {

		$msp_posts 	= get_transient($this->transient);
		$one_msp_posts;

		$options 		= !empty($options) ? $options : $this->default;
		$blog_id 		= !empty($blog_id) ? $blog_id : $this->blog_id;
		$msp_posts 	= !empty($msp_posts) ? $msp_posts : array();
		$msp_index 	= $this->options_deep_search($msp_posts, $options, $blog_id);

		if($msp_index !== false) { //Get existing set

			$one_msp_posts 	= $msp_posts[$msp_index];

		} else { //Do independent query

			$msp_posts 			= $this->populate_msp_posts($msp_posts, $blog_id, $options);
			$one_msp_posts 	= $msp_posts[count($msp_posts) - 1];
			set_transient($this->transient, $msp_posts, $this->duration);

		}

		$result = $this->display_msp_posts($one_msp_posts, $echo);

		if(!$echo) return $result;
		return;

	}

	function admin_footer_callback() {

		$args 		= array("post_no", "category", "blog_id", "excerpt", "thumbnail");
		?>
		<div id="msp_container" style="display:none;">
			<h2><?php _e("Generate Shortcode", $this->domain); ?></h2>
			<table class="form-table">
				<?php
					foreach ($args as $arg) {

						$label = __( ucwords( str_replace( "_", " ", trim($arg) ) ), $this->domain );

						?>
						<tr class="form-field">
							<th scope="row"><?php echo $label; ?></th>
							<td>
							<?php

								if( in_array($arg, array("post_no", "category")) ) {

									?>
									<input id="<?php echo $arg; ?>" name="<?php echo $arg; ?>" type="text" value="<?php echo esc_attr( $this->default[$arg] ); ?>" />
									<?php

								} else if( $arg == "blog_id" ) {

									?>
									<select id="<?php echo $arg; ?>" name="<?php echo $arg; ?>">
										<?php
											$dropdown = $this->get_blog_list();
											foreach ($dropdown as $key => $value) {
												?><option value="<?php echo $key; ?>"><?php echo $value; ?></option><?php
											}
											unset($dropdown);
										?>
									</select>
									<?php

								} else if( in_array($arg, array("excerpt", "thumbnail")) ) {

									?>
									<input id="<?php echo $arg; ?>" name="<?php echo $arg; ?>" type="checkbox" value="on" />
									<?php

								}

							?>
							</td>
						</tr>
						<?php
					}
				?>
			<tr><td colspan="2"><button class="button button-primary"><?php _e("Insert Shortcode", $this->domain); ?></button></td></tr>
			</table>
		</div>
		<?php

	}

	function media_buttons_context_callback($context) {

		if ( !current_user_can('edit_posts') && !current_user_can('edit_pages') && get_user_option('rich_editing') == 'true') {
			return;
		}

		$image 		= plugins_url("assets/msp_icon.png", __FILE__);
		$context .= "<a title='Multisite Posts Shortcode' class='thickbox msp_img' href='#TB_inline?width=400&height=600&inlineId=msp_container'><img src='{$image}' style='width: 24px;' /></a>";

    return $context;

	}

	//Enqueue scripts
	function wp_enqueue_scripts_callback() {

		wp_enqueue_style( "msp", plugins_url("assets/msp.css", __FILE__), false, false, 'all' );

		return;

	}

	function admin_enqueue_scripts_callback() {

		wp_enqueue_style( "thickbox" );
		wp_enqueue_style( "wp-jquery-ui-dialog" );
		wp_enqueue_script( "thickbox" );
		wp_enqueue_script( "msp", plugins_url("assets/msp_icon.min.js", __FILE__), array("jquery"), false, true );

		return;

	}

	//Update msp posts
	function wp_schedule_event_callback() {

		$msp_posts 	= get_transient($this->transient);
		$msp_posts 	= !empty($msp_posts) ? $msp_posts : array();
		$blogs_list = $this->get_blog_list();

		foreach ($blogs_list as $blog_id => $domain) {

			$msp_posts = $this->populate_msp_posts($msp_posts, $blog_id, $this->default);

		}

		set_transient($this->transient, $msp_posts, $this->duration);

		return;

	}

	//Shortcode functionality
	function multisite_posts_shortcode_callback($atts) {

		$options 	= shortcode_atts($this->default, $atts);
		$blog_id 	= !empty($atts["blog_id"]) ? $atts["blog_id"] : $this->blog_id;
		$output 	= $this->fetch_msp_posts($options, $blog_id, false);

		return $output;

	}

}

class Multisite_Posts_Widget extends WP_Widget {
	
	function __construct() {

		$this->msp 			= new Multisite_Posts();
		$this->default 	= $this->msp->default;
		$this->domain 	= "MSP";
		$this->default["title"] 	= "";
		$this->default["blog_id"]	= 1;

		parent::__construct(
	 		'multisite_posts_widget',
			__('Multisite Posts Widget', 'MSP'),
			array(
				'description' => __( 'Get posts from different subsites', 'MSP' ),
			)
		);

	}

	function widget( $args, $instance ) {

		$title 		= apply_filters( 'widget_title', $instance['title'] );
		$instance = shortcode_atts( $this->default, $instance );
		$temp_msp = new Multisite_Posts( $instance, $instance["blog_id"] );

		echo $args["before_widget"];
		if ( !empty( $title ) ) echo $args["before_title"] . $title . $args["after_title"];
		$temp_msp->fetch_msp_posts($instance, $instance["blog_id"], true);
		echo $args["after_widget"];
		unset($temp_msp);

	}

	function update( $new_instance, $old_instance ) {

		$instance = $old_instance;
		foreach ($new_instance as $key => $value) {

			$instance[$key] = strip_tags( $new_instance[$key] );

			continue;
		}

		return $new_instance;

	}

	function form( $instance ) {

		$instance = shortcode_atts( $this->default, $instance );
		$args 		= array("title", "post_no", "category", "blog_id", "excerpt", "thumbnail");

		foreach ($args as $arg) {

			$item_id 		= $this->get_field_id($arg);
			$item_name	=	$this->get_field_name($arg);
			$item_label = __( ucwords( str_replace( "_", " ", trim($arg) ) ), $this->domain );

			if( in_array($arg, array("title", "post_no", "category")) ) {

				?>
				<p>
					<label for="<?php echo $item_id; ?>"><?php echo $item_label; ?></label>
					<input class="widefat" id="<?php echo $item_id; ?>" name="<?php echo $item_name; ?>" type="text" value="<?php echo esc_attr( $instance[$arg] ); ?>" />
				</p>
				<?php

			} else if( $arg == "blog_id" ) {

				?>
				<p>
					<label for="<?php echo $item_id; ?>"><?php _e( "Blog ID", $this->domain ); ?></label> 
					<select class="widefat" id="<?php echo $item_id; ?>" name="<?php echo $item_name; ?>">
						<?php
							$dropdown = $this->msp->get_blog_list();
							foreach ($dropdown as $key => $value) {
								?><option value="<?php echo $key; ?>" <?php selected($instance["blog_id"], $key); ?>><?php echo $value; ?></option><?php
							}
							unset($dropdown);
						?>
					</select>
				</p>
				<?php

			} else if( in_array($arg, array("excerpt", "thumbnail")) ) {

				?>
				<p>
					<label for="<?php echo $item_id; ?>"><?php echo $item_label; ?></label>
					<input class="widefat" id="<?php echo $item_id; ?>" name="<?php echo $item_name; ?>" type="checkbox" <?php checked( $instance[$arg], "on" ); ?> />
				</p>
				<?php

			}

		}

	}
}

$msp = new Multisite_Posts();
add_action( 'widgets_init', create_function( '', 'register_widget( "Multisite_Posts_Widget" );') );
?>