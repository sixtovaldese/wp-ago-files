=== aGo Files ===
Contributors: agolab
Donate link: https://paypal.me/sixtovaldes
Tags: media library, folders, media folders, organize media, drag and drop
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Virtual folders for the Media Library that follow WordPress roles: authors file their own uploads, editors manage the shared folders.

== Description ==

aGo Files adds a folder tree to the Media Library. The files stay where they are on disk and keep their URLs: a folder is a label stored in a private taxonomy, so you can filter, move and assign files by folder without breaking a single link.

**Folders that follow WordPress roles**

On a site with several people uploading, the folders are shared, but not everyone should be able to reorganize them.

* Anyone who can upload files can create folders and file the uploads they are allowed to edit. An author can move their own images, not the images of a colleague.
* Renaming or deleting a folder changes how the whole team finds its files, so it takes the same permission WordPress asks for to manage categories (editors and administrators by default).
* A request that mixes a file the user may not edit with files they may edit changes nothing.
* Folder names are not published through the public REST API.

**Features**

* Folder tree in the Media Library, in list and grid view, and in the media picker of the editor.
* Nested folders, created from the sidebar or from the settings screen.
* Drag and drop one file or a whole selection onto a folder.
* Bulk move from the list view.
* "Uncategorized" filter for files that are not in any folder.
* Rename, create subfolder and delete from a right-click menu.
* Files on disk are never moved or renamed.
* Uninstall removes every folder and the plugin settings, and leaves the files untouched.

The plugin is completely free. There is no Pro version.

== Installation ==

1. Upload the `ago-files` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin through the Plugins menu in WordPress.
3. Open Media > Library. The folder tree appears on the left.
4. Optional: the settings screen is under aGo Tools > Files.

== Frequently Asked Questions ==

= Will it move my files on the server? =

No. Files stay where they are and keep their URLs. Folders only exist in the Media Library.

= Who can create, rename or delete folders? =

Users who can upload files can create folders and file the uploads they are allowed to edit. Renaming and deleting folders requires the manage_categories capability, which editors and administrators have by default.

= What happens to the files when I delete a folder? =

Nothing. The files stay in the Media Library and show up under "Uncategorized" if they were in no other folder.

= Can a file be in more than one folder? =

Yes. Dragging a file onto a folder moves it there; the plugin's REST route agofiles/v1/assign adds a folder while keeping the others.

= Does it import folders from other folder plugins? =

No. It starts with an empty tree.

== Screenshots ==

1. Folder tree in the Media Library grid view.
2. Folder tree in the list view, with the Folders column and bulk move.
3. Settings screen: where the folders appear and folder management.

== External services ==

This plugin does not connect to or send data to any external service. Everything runs on your own WordPress installation.

The settings screen links to external sites that only open when you click them: the aGo Lab website and documentation, the WordPress.org documentation and PayPal donation pages. No data is transmitted automatically.

== Privacy ==

The plugin stores the folders as terms of a private taxonomy (agofiles_media_folder) attached to the files of the Media Library, plus one option (agofiles_settings) with the two display settings. It stores no personal data and sends nothing to third parties. On uninstall, all folders and the settings option are removed; the files are not touched.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
