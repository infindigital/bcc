#!/usr/bin/env python3
"""Build the static copy of bccbengaluru.in from the rendered HTML in scripts/source-html/.

- Rewrites links between pages to local relative paths (works from file:// and any static host).
- Rewrites every /wp-content/ and /wp-includes/ asset URL to a local relative path.
- Leaves server-only endpoints (wp-json, admin-ajax) pointing to the live site.
- Removes WordPress-only <head> links (feeds, oEmbed, REST discovery, xmlrpc).
- Writes scripts/assets.txt: every asset path the pages need (fetched by fetch_assets.py).

Usage:  python3 scripts/build.py
"""
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, "scripts", "source-html")
HOST = "bccbengaluru.in"

# source file -> output directory ("" = site root)
PAGES = {
    "home.html": "",
    "about-us.html": "about-us",
    "our-team.html": "our-team",
    "history.html": "history",
    "contact-us.html": "contact-us",
    "blog.html": "blog",
    "category-history.html": "category/history",
    "category-blog.html": "category/blog",
    "voting-awareness-campaign.html": "voting-awareness-campaign",
    "eid-summit-iftar-meet.html": "eid-summit-iftar-meet",
    "membership-campaign.html": "membership-campaign",
    "bearys-the-guardian-of-coast.html": "bearys-the-guardian-of-coast",
    "the-brave-hearts-beary-warriors-and-freedom-fighters.html": "the-brave-hearts-beary-warriors-and-freedom-fighters",
    "the-visionaries-education-social-reform.html": "the-visionaries-education-social-reform",
    "the-litterateurs-llanguage-culture.html": "the-litterateurs-llanguage-culture",
    "highlights-of-historical-beary-koota-2026.html": "highlights-of-historical-beary-koota-2026",
}

# live URL path -> local page directory
ROUTES = {"": ""}
for d in PAGES.values():
    ROUTES[d] = d
ROUTES["%f0%9f%8c%99-eid-summit-iftar-meet"] = "eid-summit-iftar-meet"
ROUTES["%F0%9F%8C%99-eid-summit-iftar-meet"] = "eid-summit-iftar-meet"

URL_RE = re.compile(
    r"https?:(?P<s>\\?/)(?:\\?/)" + re.escape(HOST) + r"(?P<path>(?:\\?/[^\"'\s)<>,\\]*)*)"
)

HEAD_DROP = [
    re.compile(r"<link rel=\"alternate\"[^>]*>\n?"),
    re.compile(r"<link rel=\"https://api\.w\.org/\"[^>]*>\n?"),
    re.compile(r"<link rel=\"EditURI\"[^>]*>\n?"),
    re.compile(r"<link rel='shortlink'[^>]*>\n?"),
    re.compile(r"<script type=\"speculationrules\">.*?</script>\n?", re.S),
]

assets = set()


def rewrite(html, prefix):
    unmapped = set()

    def sub(m):
        esc = m.group("s") == "\\/"
        path = m.group("path").replace("\\/", "/")
        bare, _, query = path.partition("?")
        bare, _, frag = bare.partition("#")
        if bare.startswith("/wp-content/") or bare.startswith("/wp-includes/"):
            if bare.endswith("/") or "." not in bare.rsplit("/", 1)[-1]:  # a base URL / directory
                local = prefix + bare.lstrip("/")
            else:
                assets.add(bare.lstrip("/"))
                local = prefix + bare.lstrip("/")
                # keep the cache-busting query for webpack chunk loaders, drop ?ver=
                if query and not query.startswith("ver="):
                    local += "?" + query
        else:
            key = bare.strip("/")
            if key in ROUTES and not query:
                d = ROUTES[key]
                local = prefix + (d + "/" if d else "") + "index.html"
                if frag:
                    local += "#" + frag
            else:
                unmapped.add(bare)
                return m.group(0)  # server endpoint: keep the live URL
        return local.replace("/", "\\/") if esc else local

    html = URL_RE.sub(sub, html)
    return html, unmapped


def main():
    all_unmapped = set()
    for src, out_dir in PAGES.items():
        html = open(os.path.join(SRC, src), encoding="utf-8").read()
        for rx in HEAD_DROP:
            html = rx.sub("", html)
        html = re.sub(r"\n<!-- Page cached by LiteSpeed Cache[^>]*-->\s*$", "\n", html)
        depth = out_dir.count("/") + 1 if out_dir else 0
        prefix = "../" * depth if depth else "./"
        html, unmapped = rewrite(html, prefix)
        all_unmapped |= unmapped
        dest = os.path.join(ROOT, out_dir, "index.html")
        os.makedirs(os.path.dirname(dest), exist_ok=True)
        with open(dest, "w", encoding="utf-8") as f:
            f.write(html)
        print("wrote", os.path.relpath(dest, ROOT))

    with open(os.path.join(ROOT, "scripts", "assets.txt"), "w") as f:
        f.write("\n".join(sorted(assets)) + "\n")
    print(len(assets), "assets listed in scripts/assets.txt")
    print("left pointing at the live site:", *sorted(all_unmapped), sep="\n  ")


if __name__ == "__main__":
    main()
