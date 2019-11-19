=== WP Super Network ===
Contributors: manfcarlo
Tags: network, multisite, share, sharing
Requires at least: 5.0
Tested up to: 5.2
Stable tag: 1.0.4
Requires PHP: 5.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Share content between sites and create offspring networks.

== Description ==

WP Super Network is a plugin that super-charges your WordPress multisite network!

This plugin will enhance WordPress multisite functionality in three core ways:

1. Enable identical posts and pages to be instantly republished across different sites in a network
1. Enable an existing site on a network to be used as the main site for a new WordPress network
1. Enable all sites on a network to be edited from the same admin area.

== Installation ==

This section describes how to install the plugin and get it working.

1. Create a WordPress network using the [instructions here](https://wordpress.org/support/article/create-a-network/)
1. Upload the plugin files to the `/wp-content/plugins/wp-super-network` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Access the network admin

== How to Use This Plugin ==

After installation, you will find a new Super Network screen in the WordPress network admin. Note that only super admins of the network have access to the network admin. Also note that this plugin will deactivate itself unless multisite is already active. If you have not yet set up multisite, you can follow the [instructions here.](https://wordpress.org/support/article/create-a-network/)

In the Super Network screen, you can see a list of all posts and pages across the network that were flagged to be republished (shared) across the whole network. You can also see a list of all sites on the network that they can be republished to.

To flag posts and pages for republication, access the Posts or Pages screen in WordPress, find the one you want to republish, and click on Republish below the post title. If you want to revoke the republication, just click on Revoke in the same place.

== Frequently Asked Questions ==

= How do I republish a page to the whole network? =

This feature is still under development. As of version 1.0.4, you can flag any post or page on a main site for republication by finding it on the Posts or Pages screen and clicking Republish.

After you have republished the post or page, you can access the network admin and click on Super Network. You will see a list of all posts and pages across all sites in the network that have been flagged for republication.

To revoke republication, just click on Revoke in the same place where you republished the post or page.

= Why can't I republish my post? =

If you can't see anywhere to click to republish your post, it might be because it is the wrong post type or because you are not on a main site. Currently, this plugin only allows you to flag a post or page for republication and only from the main site of a network.

= How do I turn an existing site into the main site for a new network? =

This feature is still to come.

= How do I manage all of my sites in a single admin area? =

This feature is still to come.

= How do I suggest a new feature or submit a bug report? =

Through the WordPress support forum, or on the [GitHub page here.](https://github.com/carlomanf/wp-super-network/issues)

== Changelog ==

= 1.0.4 =
* Add readme for wordpress plugin directory
* Enable posts and pages to be republished across the network
* Start building network admin page
