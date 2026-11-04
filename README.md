# BCC Bengaluru — static HTML copy

This is a static HTML/CSS/JS copy of **https://bccbengaluru.in**, made from the pages the live WordPress
site renders (Hello Elementor theme, Elementor + Pro Elements, ElementsKit, MetForm). The markup, classes,
stylesheets and scripts are the site's own, so the copy looks and behaves the same: sticky header, ElementsKit
mega/mobile menu, entrance animations, video popup, team cards, blog grids and the contact form layout.

## Pages

| Page | File |
| --- | --- |
| Home | `index.html` |
| About Us | `about-us/index.html` |
| Our Team | `our-team/index.html` |
| History | `history/index.html` |
| Blog | `blog/index.html` |
| Contact Us | `contact-us/index.html` |
| Category archives | `category/history/index.html`, `category/blog/index.html` |
| Blog posts (8) | `voting-awareness-campaign/`, `eid-summit-iftar-meet/`, `membership-campaign/`, `bearys-the-guardian-of-coast/`, `the-brave-hearts-beary-warriors-and-freedom-fighters/`, `the-visionaries-education-social-reform/`, `the-litterateurs-llanguage-culture/`, `highlights-of-historical-beary-koota-2026/` |

All links between pages are relative and point at `…/index.html`. That means the site works when opened straight from disk
(`file://`) and on any static host, such as GitHub Pages, Netlify, Vercel, Hostinger or cPanel, from the domain root or from a subfolder.

## One-time step: download the assets

The images, stylesheets, scripts and fonts live under `wp-content/` and `wp-includes/`, at the same paths as on the
live site. Download them once by doing either of the following:

- **GitHub (no install):** go to **Actions → Fetch site assets → Run workflow** on this branch. It downloads everything
  and commits it back to the branch.
- **On your computer:**

  ```bash
  python3 scripts/fetch_assets.py
  ```

The script reads `scripts/assets.txt` and `scripts/assets-extra.txt`, follows the `url(...)` references inside every
stylesheet (fonts, icon fonts, background images) and skips files it already has, so you can re-run it safely.

## What still depends on the live WordPress site

A static site can't run server code, so these features keep calling `https://bccbengaluru.in`:

- **Contact form (MetForm):** submissions still post to the live site's `/wp-json/metform/...` endpoint. They only work
  while the WordPress site is online, and the form's security nonce in the HTML expires after about a day. For a
  permanent static form, point it at a form service such as Formspree or Web3Forms, or use the WhatsApp button the site
  already has.
- **Newsletter (Hostinger Reach)** and **ElementsKit AJAX calls** use the live site's REST API in the same way.

Everything else is fully static.

## Updating the copy

1. Replace the HTML files in `scripts/source-html/` with fresh rendered HTML of each page (the verbatim WordPress output).
2. Run `python3 scripts/build.py` to regenerate the pages and `scripts/assets.txt`.
3. Run `python3 scripts/fetch_assets.py`, or the GitHub workflow, to pick up any new images.

`scripts/build.py` rewrites links between pages and asset URLs to relative paths, and removes WordPress-only `<head>` tags
(RSS feeds, oEmbed, REST discovery, xmlrpc).
