=== NGT jsDelivr CDN ===
Contributors: nico23
Tags: CDN, JS, JavaScript, jsdelivr, nextgenthemes
Donate link: https://nextgenthemes.com/donate
Requires at least: 6.2.0
Requires PHP: 8.0
Tested up to: 6.8.2
Stable tag: 1.3.0
License: GPL 3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Free CDN for for all assets from wordpress.org Github and NPM.

== Changelog ==

= 2025-10-11 1.3.0 =
* New: Full support for script modules, including import map and `<link rel="modulepreload" `

= 2025-09-16 1.2.6 =
* Fix: Prevent 404 for some files by checking if files detected by hash are available on the CDN.

= 2025-03-22 1.2.4 =
* Fix: Wrong type returned.
* Fix: Not included dialog.js file.
* Improved: Minor code improvements.

= 2024-12-21 1.2.3 =
* Fix: Plugin style detection not working.

= 2024-12-06 1.2.1 =
* Fix: Code mistake caused `integrity` attribute to be wrong, plugin files would get blocked.

= 2024-12-04 1.2.0 =
* New: A info dialog was added that is only loaded when the admin bar is visible.
* New/Fix: Support for script modules.
* Improved: Shorten potentially too long transient names.
* Improved: Replaced `get_headers` with `wp_safe_remote_head`. WP coding standards and more efficient.
* Improved: Simplified and improved the code.

= 2024-05-14 1.1.0 =
* Modernized the code. 

= 2019-09-07 1.0.0 =
* Run only once per page-load.
* Better function names and some useful comments.
* Send this plugins url as user-agent to jsDelivr knows how it's used. (They asked for this). This also means more privacy as the `wp_remote_get` referrer by default would send your site URL (I really do not like that)

= 2019-08-31 0.9.4 =
* Coding standards, some minor things.

= 2018-09-23 0.9.2 =
* Fix: Force the recheck of file hashes when WP gets updated to not end up with core assets.

= 2018-09-01 0.9.1 =
* Release

== Description ==
It replaces all assets with versions available on jsDelivr. No options, nothing to configure, just works.

The code needs to be openly hosted on NPM, Github or wordpress.org.

This plugin adds a little a invisible button on the admin bar on the top right, left of "Howdy, Name". You can click that and see the assets loaded from jsDelivr.

= Support me =

It took me a lot of time to come up with this plugin and I had many iterations over various different approaches how to do this until I came up with this working solution that also does not need much code. I know the official plugin was abandoned years ago and I looked at complicated bloated code and did not even feel like learning what its doing and never looked at it again and started from scratch.

Please check out my commercial plugin and level up your video embeds with [ARVE Pro](https://nextgenthemes.com/plugins/arve-pro/) or [Donate here](https://nextgenthemes.com/donate/)
