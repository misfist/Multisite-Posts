=== Plugin Name ===
Contributors: angelazou
Donate link: http://angelawang.me/
Tags: post, query, multisite
Requires at least: 3.0
Tested up to: 3.5.2
Stable tag: 2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Get posts from another child site in a multisite setup

== Description ==

Get posts from another child site in a multisite setup. You can also integrate the plugin into your theme file, very efficient. Offers shortcode and widget for feature usage. Internationalization ready. Be sure to check our [official post](http://blogat.centilin.com/everything_it/web-design/wordpress/multisite-post-plugin-released/ "Multisite Posts Plugin") for updates/tweaks.

> Shortcode Usage: [multisite_posts] with 4 options:
> * no - Number of Posts to display
> * cat_id - Name or ID of the category to grab post from
> * site_id - Numeric ID that presents the site. To find your site ID, follow [this tutorial](http://blogat.centilin.com/everything_it/web-design/wordpress/how-to-find-your-blog-id/ "How to find your blog ID")
> * query - For Advanced users only. Complete query (in array fashion) that's going to be applied to WP Query. For example: 'offset' => 0, 'category' => 'unknown', 'orderby' => 'post_date'

> Widget Usage: Same options

== Installation ==

1. Upload `multisite-posts` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Use the [multisite_posts] shortcode in the post/page content or use the Multisite Post Widget in your sidebar

== Frequently Asked Questions ==

= Why do I see [multisite_posts] only? =

Some erratic behaviors may show up due to different theme coding. Please consult the theme designer for more details.

== Screenshots ==

1. screenshot-1.png

== Changelog ==

= 2.0 =
* Bug Fix - Could not have multiple instances
* TinyMCE Editor Button

= 20120724 =
* Widget and Shortcode available for use
* Allow simple, predefined parameter (Category ID and Number of Posts) or advanced query for get_posts