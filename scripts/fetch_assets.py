#!/usr/bin/env python3
"""Download every asset the static pages use (CSS, JS, fonts, images, PDFs) from the live
site into the same paths locally, so the copy works with no connection to WordPress.

Reads scripts/assets.txt (written by build.py) and scripts/assets-extra.txt, then follows
url(...) references inside every downloaded stylesheet (fonts, icons, background images).
Files that already exist are skipped, so it is safe to re-run.

Usage:  python3 scripts/fetch_assets.py [--origin https://bccbengaluru.in]
"""
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ORIGIN = "https://bccbengaluru.in"
if "--origin" in sys.argv:
    ORIGIN = sys.argv[sys.argv.index("--origin") + 1].rstrip("/")

CSS_URL = re.compile(r"url\(\s*['\"]?([^'\")]+)['\"]?\s*\)")


def read_list(name):
    path = os.path.join(ROOT, "scripts", name)
    if not os.path.exists(path):
        return []
    return [l.strip() for l in open(path) if l.strip() and not l.startswith("#")]


def download(rel):
    dest = os.path.join(ROOT, rel)
    if os.path.exists(dest) and os.path.getsize(dest) > 0:
        return dest, False
    url = ORIGIN + "/" + urllib.parse.quote(rel)
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (static-mirror)"})
    for attempt in range(4):
        try:
            with urllib.request.urlopen(req, timeout=60) as r:
                data = r.read()
            os.makedirs(os.path.dirname(dest), exist_ok=True)
            with open(dest, "wb") as f:
                f.write(data)
            return dest, True
        except urllib.error.HTTPError as e:
            if e.code == 404:
                raise
            time.sleep(2 ** attempt)
        except Exception:
            time.sleep(2 ** attempt)
    raise RuntimeError("failed after retries: " + url)


def main():
    queue = read_list("assets.txt") + read_list("assets-extra.txt")
    seen, missing, fetched = set(), [], 0
    while queue:
        rel = queue.pop(0)
        if rel in seen:
            continue
        seen.add(rel)
        try:
            dest, new = download(rel)
            fetched += new
            if new:
                print("ok  ", rel)
        except Exception as e:
            missing.append(rel)
            print("MISS", rel, "-", e)
            continue
        if rel.endswith(".css"):
            css = open(dest, encoding="utf-8", errors="ignore").read()
            base = "/" + os.path.dirname(rel) + "/"
            for ref in CSS_URL.findall(css):
                if ref.startswith(("data:", "#")) or "://" in ref and ORIGIN not in ref:
                    continue
                ref = ref.split("?")[0].split("#")[0]
                if ref.startswith(ORIGIN):
                    ref = ref[len(ORIGIN):]
                target = urllib.parse.urljoin(base, ref).lstrip("/")
                if target.startswith(("wp-content/", "wp-includes/")):
                    queue.append(urllib.parse.unquote(target))
    print(f"\nDone: {fetched} downloaded, {len(seen) - len(missing)} present, {len(missing)} missing.")
    if missing:
        print("Missing (usually optional on-demand files):", *missing, sep="\n  ")


if __name__ == "__main__":
    main()
