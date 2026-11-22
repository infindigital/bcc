#!/usr/bin/env python3
"""Build the "BCC" WordPress theme (wordpress-theme/bcc) from the rendered HTML in scripts/source-html/.

Every view is the live site's own markup with tokens in place of anything site-specific:
  {{ASSETS}} / {{ASSETS_JSON}}  theme assets URL (plain / JSON-escaped)
  {{HOME}}   / {{HOME_JSON}}    home_url('/') (plain / JSON-escaped)
  {{NONCE}}                     a fresh wp_rest nonce
  {{WP_HEAD}} {{WP_BODY_OPEN}} {{WP_FOOTER}} {{BODY_CLASS}}
plus content tokens that the PHP templates fill from the WordPress database
({{POST_TITLE}}, {{POST_CONTENT}}, {{GRID:...}} and so on).

Usage:  python3 scripts/build_theme.py
"""
import html as htmlmod
import json
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, "scripts", "source-html")
THEME = os.path.join(ROOT, "wordpress-theme", "bcc")
VIEWS = os.path.join(THEME, "views")
HOST = "bccbengaluru.in"

POSTS = {  # source file -> (slug, original post ID; IDs give the publishing order)
    "voting-awareness-campaign.html": ("voting-awareness-campaign", 270),
    "eid-summit-iftar-meet.html": ("eid-summit-iftar-meet", 274),
    "membership-campaign.html": ("membership-campaign", 284),
    "bearys-the-guardian-of-coast.html": ("bearys-the-guardian-of-coast", 363),
    "the-brave-hearts-beary-warriors-and-freedom-fighters.html": ("the-brave-hearts-beary-warriors-and-freedom-fighters", 379),
    "the-visionaries-education-social-reform.html": ("the-visionaries-education-social-reform", 386),
    "the-litterateurs-llanguage-culture.html": ("the-litterateurs-llanguage-culture", 394),
    "highlights-of-historical-beary-koota-2026.html": ("highlights-of-historical-beary-koota-2026", 821),
}

URL_RE = re.compile(
    r"https?:(?P<s>\\?/)(?:\\?/)" + re.escape(HOST) + r"(?P<path>(?:\\?/[^\"'\s)<>,\\]*)*)"
)

HEAD_DROP = [
    re.compile(r"<title>.*?</title>\n?", re.S),
    re.compile(r"<meta name='robots'[^>]*>\n?"),
    re.compile(r"<link rel=\"canonical\"[^>]*>\n?"),
    re.compile(r"<link rel=\"alternate\"[^>]*>\n?"),
    re.compile(r"<link rel=\"https://api\.w\.org/\"[^>]*>\n?"),
    re.compile(r"<link rel=\"EditURI\"[^>]*>\n?"),
    re.compile(r"<link rel='shortlink'[^>]*>\n?"),
    re.compile(r"<meta name=\"generator\"[^>]*>\n?"),
    re.compile(r"<style id='wp-emoji-styles-inline-css'>.*?</style>\n?", re.S),
    re.compile(r"<script type=\"speculationrules\">.*?</script>\n?", re.S),
    re.compile(r"<script id=\"wp-emoji-settings\" type=\"application/json\">.*?</script>\n?", re.S),
    re.compile(r"<script type=\"module\">\n/\*! This file is auto-generated \*/.*?</script>\n?", re.S),
    re.compile(r"<script data-cfasync=\"false\" src=\"/cdn-cgi/[^\"]*\"></script>"),
    re.compile(r"\n<!-- Page cached by LiteSpeed Cache[^>]*-->\s*$"),
]


def tokenize_urls(html):
    def sub(m):
        esc = m.group("s") == "\\/"
        path = m.group("path").replace("\\/", "/")
        bare, _, query = path.partition("?")
        if bare.startswith(("/wp-content/", "/wp-includes/")):
            out = "{{ASSETS}}" + bare.lstrip("/")
            if query and not query.startswith("ver="):
                out += "?" + query
            return out.replace("{{ASSETS}}", "{{ASSETS_JSON}}").replace("/", "\\/") if esc else out
        bare = bare.replace("%f0%9f%8c%99-", "").replace("%F0%9F%8C%99-", "")
        out = "{{HOME}}" + bare.lstrip("/") + ("?" + query if query else "")
        return out.replace("{{HOME}}", "{{HOME_JSON}}").replace("/", "\\/") if esc else out

    return URL_RE.sub(sub, html)


def prepare(html):
    for rx in HEAD_DROP:
        html = rx.sub("", html)
    html = tokenize_urls(html)
    html = re.sub(r'"nonce":"[0-9a-f]{10}"', '"nonce":"{{NONCE}}"', html)
    html = re.sub(r'data-wp-nonce="[0-9a-f]{10}"', 'data-wp-nonce="{{NONCE}}"', html)
    html = re.sub(r'data-form-nonce="[0-9a-f]{10}"', 'data-form-nonce="{{FORM_NONCE}}"', html)
    html = html.replace("</head>", "{{WP_HEAD}}\n</head>", 1)
    html, n = re.subn(r'<body class="([^"]*)">', r'<body class="{{BODY_CLASS:\1}}">\n{{WP_BODY_OPEN}}', html, count=1)
    assert n == 1
    i = html.rfind("</body>")
    html = html[:i] + "{{WP_FOOTER}}\n" + html[i:]
    return html


def match_div(html, start):
    """Return the index just past the </div> closing the <div ...> that starts at `start`."""
    depth = 0
    for m in re.compile(r"<div\b|</div>").finditer(html, start):
        depth += 1 if m.group(0) == "<div" else -1
        if depth == 0:
            return m.end()
    raise ValueError("unbalanced div")


def inner_bounds(html, start):
    open_end = html.index(">", start) + 1
    close_end = match_div(html, start)
    return open_end, close_end - len("</div>")


def replace_inner(html, start, token):
    a, b = inner_bounds(html, start)
    return html[:a] + token + html[b:]


def widget_start(html, data_id):
    return html.rindex("<div", 0, html.index(f'data-id="{data_id}"'))


def extract_grid(html, token):
    """Replace the ElementsKit post grid items with `token`; return (html, first item markup)."""
    m = re.search(r'<div id="post-items--[^"]+" class="[^"]*">', html)
    a, b = inner_bounds(html, m.start())
    inner = html[a:b]
    first = re.search(r'<div class="post-item ', inner).start()
    item = inner[first:match_div(inner, first)]
    return html[:a] + token + html[b:], item


def card_template(item):
    url = re.search(r'<a href="([^"]+)" class="elementskit-entry-thumb"', item).group(1)
    item = item.replace(url, "{{URL}}")
    item = re.sub(r'(<img[^>]*?src=")[^"]*(" alt=")[^"]*(")', r"\1{{IMG}}\2{{TITLE_ATTR}}\3", item, count=1)
    item = re.sub(r'(<h2 class="entry-title">\s*<a href="\{\{URL\}\}">)\s*.*?\s*(</a>)', r"\1{{TITLE}}\2", item, count=1, flags=re.S)
    item = re.sub(r'(<span class="elementskit-meta-wraper">\s*).*?(\s*</span>\s*</div>)', r"\1{{CATS}}\2", item, count=1, flags=re.S)
    item = re.sub(r"<p>.*?</p>", "<p>{{EXCERPT}}</p>", item, count=1, flags=re.S)
    return item


def write(name, content):
    assert "bccbengaluru.in" not in content.replace("bccbengaluru?igsh", ""), name
    with open(os.path.join(VIEWS, name), "w", encoding="utf-8") as f:
        f.write(content)
    print("view", name, len(content))


def read(name):
    return open(os.path.join(SRC, name), encoding="utf-8").read()


def main():
    os.makedirs(VIEWS, exist_ok=True)

    # Front page: the latest-posts grid becomes dynamic.
    home, card = extract_grid(prepare(read("home.html")), "{{GRID:image:6}}")
    write("front-page.html", home)
    write("card-image.html", card_template(card))

    # Static pages, rendered exactly as captured.
    for slug in ("about-us", "our-team", "history", "contact-us"):
        write(f"page-{slug}.html", prepare(read(f"{slug}.html")))

    # Blog (posts page): the grid shows the main query, followed by pagination.
    blog, _ = extract_grid(prepare(read("blog.html")), "{{GRID:main}}")
    write("blog.html", blog)

    # Single post (Elementor single-post template, id 366).
    single = prepare(read("membership-campaign.html"))
    single, block = extract_grid(single, "{{GRID:related:2}}")
    write("card-block.html", card_template(block))
    single = re.sub(r'(<h1 class="elementor-heading-title elementor-size-default">)<a href="[^"]*" rel="tag">Blog</a>(</h1>)',
                    r"\1{{POST_TERMS}}\2", single, count=1)
    single = single.replace('<h2 class="elementor-heading-title elementor-size-default">MEMBERSHIP CAMPAIGN</h2>',
                            '<h2 class="elementor-heading-title elementor-size-default">{{POST_TITLE}}</h2>', 1)
    single = replace_inner(single, widget_start(single, "a4cf51a"), "{{POST_THUMBNAIL}}")
    single = replace_inner(single, widget_start(single, "f209a00"), "\n{{POST_CONTENT}}\n")
    # Sidebar "Latest News" and "Categories" become dynamic lists (item templates kept as views).
    def dynamic_list(html, data_id, token):
        w = widget_start(html, data_id)
        ul = re.compile(r'<ul class="[^"]*">').search(html, w)
        end = html.index("</ul>", ul.end())
        inner = html[ul.end():end]
        first = re.search(r"<li\b.*?</li>", inner, re.S).group(0)
        return html[:ul.end()] + token + html[end:], first

    single, li = dynamic_list(single, "285b03e7", "{{SIDEBAR_POSTS}}")
    li = re.sub(r'<a href="[^"]*"', '<a href="{{URL}}"', li, count=1)
    li = re.sub(r"<img [^>]*/>", "{{THUMB}}", li, count=1)
    li = re.sub(r'(<span class="elementor-icon-list-text">).*?(</span>)', r"\1{{TITLE}}\2", li, count=1, flags=re.S)
    li = re.sub(r'(<span class="meta-date">).*?(</span>)', r"\1 {{DATE}} \2", li, count=1, flags=re.S)
    li = re.sub(r'(</i>)\s*[^<]*?(\s*</span>)', r"\1 {{CAT}}\2", li, count=1)
    write("item-post-list.html", li)
    single, li = dynamic_list(single, "149a7434", "{{SIDEBAR_CATS}}")
    li = re.sub(r'<a href="[^"]*"', '<a href="{{URL}}"', li, count=1)
    li = re.sub(r'(<span class="elementor-icon-list-text">).*?(</span>)', r"\1{{NAME}}\2", li, count=1, flags=re.S)
    write("item-category.html", li)
    single = re.sub(r"postid-284 ", "", single)
    single = single.replace("post-284 ", "").replace(" category-blog\"", "\"")
    write("single.html", single)

    # Archive / generic frame (Hello Elementor markup inside the site header and footer).
    arch = prepare(read("category-history.html"))
    a = arch.index('<main id="content" class="site-main">')
    b = arch.index("</main>", a) + len("</main>")
    arch = arch[:a] + "{{MAIN}}" + arch[b:]
    arch = re.sub(r'\{\{BODY_CLASS:[^}]*\}\}',
                  "{{BODY_CLASS:wp-theme-hello-elementor hello-elementor-default elementor-default elementor-kit-11}}", arch)
    write("frame.html", arch)

    # Demo content: the eight live posts, imported on theme activation if missing.
    demo = []
    for src, (slug, pid) in POSTS.items():
        s = tokenize_urls(read(src))
        title = htmlmod.unescape(re.search(r"<title>(.*?) &#8211; BCC</title>", s, re.S).group(1))
        cat = re.search(r'<h1 class="elementor-heading-title elementor-size-default"><a href="\{\{HOME\}\}category/([^/]+)/" rel="tag">([^<]+)</a>', s)
        a, b = inner_bounds(s, widget_start(s, "f209a00"))
        content = s[a:b].strip()
        ta, tb = inner_bounds(s, widget_start(s, "a4cf51a"))
        thumb = re.search(r'src="\{\{ASSETS\}\}([^"]+)"', s[ta:tb])
        demo.append({
            "id": pid, "slug": slug, "title": title,
            "category": {"slug": cat.group(1), "name": htmlmod.unescape(cat.group(2))},
            "thumbnail": thumb.group(1) if thumb else "",
            "content": content,
        })
    demo.sort(key=lambda p: p["id"])
    with open(os.path.join(THEME, "inc", "demo-posts.json"), "w", encoding="utf-8") as f:
        json.dump(demo, f, ensure_ascii=False, indent=1)
    print("demo posts", len(demo))

    # Asset list for the in-WordPress asset mirror (inc/assets.php).
    lists = []
    for name in ("assets.txt", "assets-extra.txt"):
        path = os.path.join(ROOT, "scripts", name)
        if os.path.exists(path):
            lists += [l.strip() for l in open(path) if l.strip() and not l.startswith("#")]
    lists.append("wp-content/plugins/skyboot-custom-icons-for-elementor/assets/css/materialdesignicons.min.css")
    lists = sorted(set(lists))
    with open(os.path.join(THEME, "inc", "assets-list.txt"), "w") as f:
        f.write("\n".join(lists) + "\n")
    print("asset list", len(lists))


if __name__ == "__main__":
    main()
