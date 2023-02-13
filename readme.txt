=== WP Super Network ===
Contributors: manfcarlo
Tags: network, multisite, share, sharing, move, migrate, migration, duplicate, syndication, content, management
Tested up to: 6.1
Stable tag: 1.2.0
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

Flag any post or page on a main site for republication by finding it on the Posts or Pages screen and clicking Republish.

After you have republished the post or page, it will start showing up across every site on your network. You can also access the network admin and click on Super Network. You will see a list of all posts and pages across all sites in the network that have been flagged for republication.

To revoke republication, just click on Revoke in the same place where you republished the post or page.

= Why do I see "Can't Republish" for some posts and pages? =

If two or more sites on a network have a post with the same numeric ID, none of these posts is able to be republished unless all others with the same ID are permanently deleted. This limitation is very unlikely to change in future versions.

= How do I turn an existing site into the main site for a new network? =

This feature is scheduled for the next major release.

= How do I manage all of my sites in a single admin area? =

Go to Settings > Network and turn on consolidated mode. Effectively, this republishes all posts and pages without needing to individually flag them.

= After turning on consolidated mode, why do some of my posts and pages disappear? =

If two or more sites on a network have a post with the same numeric ID, none of these posts is able to be accessed on consolidated mode. You can eliminate post ID collisions by deleting posts you don't want via the network admin screen > Super Network.

= Is this the same as Global Terms? =

No!

WP Super Network does not enable any "global" data relationships. The data you can create with WP Super Network is the same as the data you can create without it. WP Super Network merely brings the management and the viewing of the data together into the one place.

= Is this the same as WP Multi Network? =

No!

WP Super Network is compatible with multi-network environments, but it does not include the management tools that WP Multi Network includes. Therefore, the two plugins have different purposes and can work well together.

= Is this the same as Distributor? =

No!

WP Super Network does not duplicate content between sites, and all posts will always continue to have one and only one permalink. Posts from across the network may preview in archives, but their permalinks will always direct back to their original respective sites.

= Is this the same as MainWP? =

Only a little bit.

WP Super Network and MainWP share a few similar features. However, MainWP is only a good fit for users who don't use the block editor and don't use multisite, since MainWP is yet to announce any support for the block editor and MainWP's own documentation states that it does not test or support multisite. This is despite the block editor and multisite both being core WordPress features for a significant number of years.

Also, MainWP's features do not integrate with the core WordPress interface and can only be accessed through its own specially-built interface.

Although WP Super Network requires multisite, it does not require you to learn a new interface. Instead, you can continue working with the core WordPress interfaces you are already familiar with, including both the block editor and classic editor.

= Which plugins are incompatible with WP Super Network? =

WP Super Network is incompatible with [Link Manager](https://wordpress.org/plugins/link-manager/). Users of Link Manager will not be supported and are advised to not install WP Super Network.

WP Super Network is currently incompatible with [SQLite Database Integration](https://wordpress.org/plugins/sqlite-database-integration/). Users of SQLite Database Integration are advised to not install WP Super Network for the time being. Compatibility is planned for a later release, so check back later.

If you use a plugin that adds a db.php drop-in, it may or may not be incompatible with WP Super Network. To know if you have a db.php drop-in, go to the Plugins screen, click on Drop-in and check if db.php is listed. If the db.php drop-in was added by a plugin that follows the WordPress conventions well and has high user ratings, you are unlikely to see any incompatibility issues with WP Super Network, but you are welcome to ask on the support forum.

= Is WP Super Network safe to install? =

See above question about incompatible plugins.

For best results, the free version of WP Super Network should be activated on a fresh network. While results will vary, you may find that some features (e.g. consolidated mode) are limited in utility for existing networks and/or cause a slow-down in performance for large networks.

None the less, the utmost care has been taken to avoid permanent data loss or corruption. Keeping regular database back-ups is always recommended, but deactivating the plugin should return your network back to its previous state.

A premium version is planned for the future, focusing on enhanced performance for medium-to-large networks.

= How do I suggest a new feature or submit a bug report? =

Through the WordPress support forum, or on the [GitHub page here.](https://github.com/carlomanf/wp-super-network/issues)

== Changelog ==

= 1.2.0 =
* Republished posts and pages can be inserted, updated and deleted
* Availability of taxonomies and comments for republished posts
* Changed behaviour of select queries that target a specific post
* Cache implemented for duplicate queries
* Fixed a few bugs with network-based options
* Minimum PHP version lifted to 7.2

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
