=== WP.com Cross-Post ===
Contributors: nicolamustone, automattic
Tags: post, blog, cross-posts, cross, sync, wordpress.com, categories
Requires at least: 4.4
Tested up to: 4.6
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Cross-Post from your WordPress.com blog to your self-hosted WordPress website

== Description ==

WP.com Cross-Post is a free plugin that syncs your WordPress.com blog with your self-hosted WordPress site automatically, every day.

It creates posts on your self-hosted WordPress site by fetching them from WordPress.com through the API and publishes them automatically.

= Customization =

This plugin is customizable via filters. The available filters are:

* `wpcom_crosspost_author_email`: Pass the email address of the user to use as author of the cross-posts;
* `wpcom_crosspost_post_data`: Filter the data before to create the cross-post;
* `wpcom_crosspost_sync_frequency`: Change the frequency of the scheduled event to check for new posts;
* `wpcom_crosspost_api_call_params`: Filter the data sent to WordPress.com via API to retrieve posts;
* `wpcom_crosspost_sinc_from`: Change the "from" date to retrieve posts from WordPress.com. Default "yesterday".

= Google Duplicated Content =

This plugin is compatible with Yoast SEO to prevent duplicated content. Make sure to have Yoast SEO installed and active, the plugin will automatically filter the post URL and set the canonical cross-domain URL pointing to its original WordPress.com permalink.

== Installation ==

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of this plugin, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "WP.com Cross-Post" and click Search Plugins. Once you’ve found the plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading the plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Configuration =

Go to **Settings > WP.com X-Post** and fill in the Client ID and Secret. If you don't have them, click on the button "Create WordPress.com App" to create an app on WordPress.com.

Save the changes. The button "Connect to WordPress.com" will appear. Click on it to connect your self-hosted site to WordPress.com and choose the blog you want to sync with.

One hour after the first activation there will be the first sync.

== Changelog ==

= 1.0.0 =
* First release
