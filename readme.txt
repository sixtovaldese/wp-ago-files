=== aGo Files ===
Contributors: sixtovaldese
Donate link: https://paypal.me/sixtovaldes
Tags: media library, folders, organization, taxonomy, file manager
Requires at least: 6.0
Tested up to: 6.9
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
* Create, rename, delete and reorder folders.
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

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
