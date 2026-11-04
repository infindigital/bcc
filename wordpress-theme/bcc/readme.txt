=== BCC ===
Requires at least: 6.0
Requires PHP: 7.4
License: GPLv2 or later

The bccbengaluru.in website as a standalone WordPress theme.

== Description ==

Every page uses the live site's own markup, stylesheets and scripts (bundled in assets/), so the
site looks and behaves the same as the Elementor build: sticky header, ElementsKit menu with the
mobile off-canvas panel, entrance animations, video popup, team cards and post grids. Elementor,
Pro Elements, ElementsKit and MetForm are NOT required.

* Home, About Us, Our Team, History and Contact Us: the original designs (views/*.html).
* Blog page, the home page "latest posts" grid and "You may also like": real WordPress posts.
* Single posts: the original single-post design with the post's title, category, featured image and content.
* Category/tag/date archives, search results, 404 and any other page: the site header and footer around the content.
* Contact form: the original form. Submissions are saved under "Contact Messages" in the admin and
  emailed to the site admin email (filter: bcc_contact_recipient).

== Installation ==

1. Appearance > Themes > Add New > Upload Theme, choose bcc-theme.zip, then Activate.
2. On activation the theme:
   * sets permalinks to /%postname%/ if they are plain;
   * creates the Home, About Us, Our Team, History, Contact Us and Blog pages if missing;
   * sets Home as the front page and Blog as the posts page if none are set;
   * creates the eight original posts (with featured images) if posts with those slugs don't exist.
   Existing pages, posts and settings are never overwritten. To skip the sample posts:
   add_filter( 'bcc_import_demo_posts', '__return_false' );
3. If you switch from the Elementor build on the same site, you can deactivate Elementor,
   Pro Elements, ElementsKit Lite and MetForm afterwards.

== Editing ==

The designed pages are HTML files in views/. Edit the text there (keep the {{TOKENS}}), or edit
scripts/source-html/ in the repository and run scripts/build_theme.py to regenerate them.
