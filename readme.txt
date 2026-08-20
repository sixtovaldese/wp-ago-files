=== aGo Files ===
Contributors: agolab
Donate link: https://paypal.me/sixtovaldes
Tags: media library, folders, organization, taxonomy, file manager
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Virtual folders for the WordPress Media Library using a custom taxonomy. Lightweight alternative to HappyFiles and FileBird.

== Description ==

aGo Files organizes your media library into virtual folders. The files stay where they are on disk: only a taxonomy is added so you can filter, move and assign attachments by folder from the Media screen.

**Features**

* Tree of folders in the Media Library sidebar.
* Drag and drop attachments between folders.
* Create, rename and delete folders.
* Multi-select and bulk assign.
* Filter by folder in the attachment picker.
* Files on disk are not moved: works without breaking existing URLs.
* No external services.

== Installation ==

1. Upload the `ago-files` folder to `/wp-content/plugins/` or install via the Plugins screen.
2. Activate the plugin through the Plugins menu in WordPress.
3. Open the Media Library. The folder sidebar appears on the left.

== Frequently Asked Questions ==

= Will it move my files on the server? =

No. Files stay where they are. Folders are virtual.

= Is it compatible with FileBird / HappyFiles? =

It uses a different taxonomy, so it does not import their data. You can run them side by side, but most users pick one.

= Does it slow down the Media Library? =

The taxonomy query is indexed. Tested with libraries above 10,000 attachments without noticeable lag.

== Screenshots ==

1. Folder sidebar in the Media Library with drag and drop.
2. Folder management page: create, rename, delete and nest folders.
3. Bulk move and folder filter in grid view.

== External services ==

This plugin does not connect to any external service. The donation links and the aGo Lab link in the admin page point to PayPal and ago.cl, opened only when the user clicks them.

== Privacy ==

The plugin stores a custom taxonomy (agofiles_media_folder) to group attachments, plus one option (agofiles_settings) with the sidebar preferences. It sends no data to third parties. On uninstall, all folder terms and the settings option are removed.

== Changelog ==

= 1.0.0 =
* Initial release.
* Virtual folder tree in the Media Library sidebar.
* Drag and drop, bulk move and folder assignment.
* Create, rename, delete and nest folders.
* Filter media by folder in list and grid view.
* REST API for all folder operations.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
