=== WP Super Network ===
Contributors: manfcarlo
Tags: network, multisite, share, sharing, move, migrate, migration, duplicate, syndication, content, management
Tested up to: 5.9
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Share content between sites and create offspring networks.

== Description ==

WP Super Network is a plugin that super-charges your WordPress multisite network!

This plugin will enhance WordPress multisite functionality in three core ways:

1. Enable posts and pages to be instantly republished across different sites in a network
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

As of version 1.0.5, you could flag any post or page on a main site for republication by finding it on the Posts or Pages screen and clicking Republish, but it would not take any effect. Version 1.1.0 now implements the behaviour of this feature!

After you have republished the post or page, it will start showing up across every site on your network. You can also access the network admin and click on Super Network. You will see a list of all posts and pages across all sites in the network that have been flagged for republication.

To revoke republication, just click on Revoke in the same place where you republished the post or page.

= Why do I see "Can't Republish" for some posts and pages? =

If two or more sites on a network have a post with the same numeric ID, none of these posts is able to be republished unless all others with the same ID are permanently deleted. This limitation is very unlikely to change in future versions.

= How do I turn an existing site into the main site for a new network? =

This feature is still to come.

= How do I manage all of my sites in a single admin area? =

Go to Settings > Network and turn on consolidated mode. Effectively, this republishes all posts and pages without needing to individually flag them.

= After turning on consolidated mode, why do some of my posts and pages disappear? =

If two or more sites on a network have a post with the same numeric ID, none of these posts is able to be accessed on consolidated mode. You can eliminate post ID collisions by deleting posts you don't want via the network admin screen > Super Network.

= Why are republished posts and pages not editable in the admin area? =

This feature is still to come.

= How do I suggest a new feature or submit a bug report? =

Through the WordPress support forum, or on the [GitHub page here.](https://github.com/carlomanf/wp-super-network/issues)

== Changelog ==

= 1.1.0 =
* Behaviour implemented for republished posts and pages
* New setting for consolidated mode
* New setting to declare post types as network-only
* Elimination of post ID collisions available in network admin

= 1.0.8 =
* Fix defect from 1.0.7

= 1.0.7 =
* New settings API
* Import options values from main site

= 1.0.6 =
* Only republish posts when consolidated mode is on
* New method to turn on consolidated mode ad-hoc

= 1.0.5 =
* Enable all post types to be republished
* Start republishing posts across the network
* Correct permalinks for republished posts and CPT's

= 1.0.4 =
* Add readme for wordpress plugin directory
* Enable posts and pages to be republished across the network
* Start building network admin page
