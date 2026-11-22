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

== Assets and icons ==

The theme uses the original site's stylesheets, scripts, icon fonts and images. If they are not
bundled in assets/, the theme copies them once into wp-content/uploads/bcc-theme-assets/ (in the
background after activation, or right away from Appearance > BCC Assets > Download now) and then
serves them from your own domain. Icon fonts only display when served from the same domain.

== Settings (wp-admin > BCC Theme) ==

* Contact Form: the email address(es) that receive messages, subject prefix, thank-you message,
  sender name/address, optional SMTP (host, port, encryption, username, password) and a
  "Send test email" button. Messages are also saved under Contact Messages.
* Analytics: see below.
* Sample Posts: import the eight original BCC posts on demand.
* Assets: where styles and images are loaded from.

== Your own posts ==

The home page "Insights & Article" grid, Blog page, single posts, "You may also like", the
Latest News / Categories sidebar and category pages all show the site's own posts. The eight
sample posts are only added on activation when the site has no posts of its own.

== Analytics ==

BCC Theme > Analytics: paste a Google Analytics 4, Google Tag Manager, Meta Pixel or
Microsoft Clarity ID, or any other service's code (head / after body / before </body>).
Clear a field to remove that tracker. Logged-in administrators are not tracked (optional).

== Installation ==

1. Appearance > Themes > Add New > Upload Theme, choose bcc-theme.zip, then Activate.
2. On activation the theme:
   * sets permalinks to /%postname%/ if they are plain;
   * creates the Home, About Us, Our Team, History, Contact Us and Blog pages if missing;
   * sets Home as the front page and Blog as the posts page if none are set;
   * on a site without posts, creates the eight original posts (with featured images).
   Existing pages, posts and settings are never overwritten. To skip the sample posts:
   add_filter( 'bcc_import_demo_posts', '__return_false' );
3. If you switch from the Elementor build on the same site, you can deactivate Elementor,
   Pro Elements, ElementsKit Lite and MetForm afterwards.

== Editing ==

The designed pages are HTML files in views/. Edit the text there (keep the {{TOKENS}}), or edit
scripts/source-html/ in the repository and run scripts/build_theme.py to regenerate them.
