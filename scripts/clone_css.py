#!/usr/bin/env python3
"""Rebuild missing Elementor page stylesheets by reusing the home page's (post-15.css) rules.

Many inner-page sections were copied from the home page in Elementor, so they have the same
widgets, text and settings but new element IDs. This script matches elements between a page and
the home page by structure and content, then copies the home page's CSS rules for each matched
element with the IDs rewritten. Unmatched elements are listed so they can be styled by hand
(scripts/manual-css/post-<id>.css is appended if present).

Usage: python3 scripts/clone_css.py
"""
import os
import re
import sys

import tinycss2
from bs4 import BeautifulSoup

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
THEME = os.path.join(ROOT, "wordpress-theme", "bcc")
CSS_DIR = os.path.join(THEME, "assets", "wp-content", "uploads", "elementor", "css")
# Rebuilt files go here; copy them into CSS_DIR only if the original Elementor files are lost again.
OUT_DIR = os.path.join(ROOT, "scripts", "rebuilt-css")
MANUAL = os.path.join(ROOT, "scripts", "manual-css")
HOME_DOC = "15"

# Manual corrections: page element -> home element whose styles it should take (None = never match,
# it is styled in scripts/manual_css.py instead).
OVERRIDES = {
    # History: the four initiative cards use the home page's "01. Beary Koota" card design
    "dd5c177": "2bf5ec53",
    "d3d0715": "34b03502", "7dd8896": "34b03502", "e46bd68": "34b03502", "5964048": "34b03502",
    "a9e0b6c": "1de0e9af", "21fadac": "1de0e9af", "c816aab": "1de0e9af", "cc684fc": "1de0e9af",
    "1ebae5b": "62de8e77", "cd369c0": "62de8e77", "0625823": "62de8e77", "e98b88e": "62de8e77",
    # History: Beary Koota intro row is styled by hand
    "6ee2acc": None, "7884880": None, "7485aab": None, "fe04b32": None,
    # Blog: banner is styled by hand; the post grid uses the home page's article grid
    "7cf24dc5": None, "70dd5a2": None, "b98afca": None, "1591a002": "f2b17d6",
}

PAGES = {  # view -> Elementor document id
    "page-about-us": "94",
    "page-our-team": "102",
    "page-history": "429",
    "page-contact-us": "114",
    "blog": "517",
    "single": "366",
}


def norm(text):
    return re.sub(r"\s+", " ", text or "").strip().lower()


def doc_root(html, doc_id):
    soup = BeautifulSoup(html, "html.parser")
    return soup.find(attrs={"data-elementor-id": doc_id})


def elements(root):
    """Yield (element, depth) for every Elementor element below root, in document order."""
    return root.find_all(attrs={"data-id": True})


def own_children(el):
    out = []
    for c in el.find_all(attrs={"data-id": True}):
        parent = c.find_parent(attrs={"data-id": True})
        if parent is el:
            out.append(c)
    return out


def signature(el, memo):
    key = id(el)
    if key in memo:
        return memo[key]
    kind = el.get("data-widget_type") or el.get("data-element_type") or "?"
    kids = own_children(el)
    if kids:
        sig = (kind, tuple(signature(k, memo) for k in kids))
    else:
        imgs = tuple(sorted(re.sub(r"-\d+x\d+(?=\.\w+$)", "", i.get("src", "")).rsplit("/", 1)[-1] for i in el.find_all("img")))
        sig = (kind, norm(el.get_text(" ")), imgs)
    memo[key] = sig
    return sig


def loose(el):
    """Content-only key for widgets: type + text (used when the parent structure differs)."""
    kind = el.get("data-widget_type") or el.get("data-element_type") or "?"
    return (kind, norm(el.get_text(" "))[:200])


def build_map(page_root, home_root):
    memo_p, memo_h = {}, {}
    home_by_sig = {}
    home_by_loose = {}
    for h in elements(home_root):
        home_by_sig.setdefault(signature(h, memo_h), []).append(h)
        if not own_children(h):
            home_by_loose.setdefault(loose(h), []).append(h)

    page_ids = {e["data-id"] for e in elements(page_root)}
    mapping = {k: v for k, v in OVERRIDES.items() if v and k in page_ids}
    blocked = {k for k, v in OVERRIDES.items() if v is None and k in page_ids}

    def map_subtree(p, h):
        if p["data-id"] not in blocked:
            mapping.setdefault(p["data-id"], h["data-id"])
        for pc, hc in zip(own_children(p), own_children(h)):
            map_subtree(pc, hc)

    # 1) whole matching subtrees, largest first (document order visits parents before children)
    for p in elements(page_root):
        if p["data-id"] in mapping or p["data-id"] in blocked:
            continue
        cands = home_by_sig.get(signature(p, memo_p))
        if cands:
            map_subtree(p, cands[0])
    # 2) leaf widgets with identical type + text
    for p in elements(page_root):
        if p["data-id"] in mapping or p["data-id"] in blocked or own_children(p):
            continue
        cands = home_by_loose.get(loose(p))
        if cands:
            mapping[p["data-id"]] = cands[0]["data-id"]
    # 3) containers: if the matched children of an unmatched container all sit in one home
    #    container, use that container's styles (repeat so it climbs level by level)
    changed = True
    while changed:
        changed = False
        for p in elements(page_root):
            if p["data-id"] in mapping or p["data-id"] in blocked:
                continue
            kids = own_children(p)
            if not kids:
                continue
            parents = []
            for k in kids:
                hid = mapping.get(k["data-id"])
                if hid:
                    h = home_root.find(attrs={"data-id": hid})
                    hp = h.find_parent(attrs={"data-id": True}) if h else None
                    if hp is not None:
                        parents.append(hp)
            if parents and len(parents) * 2 >= len(kids) and all(x is parents[0] for x in parents):
                mapping[p["data-id"]] = parents[0]["data-id"]
                changed = True
        # 4) positional: inside a matched container, an unmatched child takes the styles of the
        #    home child at the same position when both are the same kind of element
        for p in elements(page_root):
            hid = mapping.get(p["data-id"])
            if not hid:
                continue
            h = home_root.find(attrs={"data-id": hid})
            pk, hk = own_children(p), own_children(h)
            for i, c in enumerate(pk):
                if c["data-id"] in mapping or c["data-id"] in blocked:
                    continue
                kind = c.get("data-widget_type") or c.get("data-element_type")
                same = [x for x in hk if (x.get("data-widget_type") or x.get("data-element_type")) == kind]
                pos = hk[i] if i < len(hk) and (hk[i].get("data-widget_type") or hk[i].get("data-element_type")) == kind else (same[0] if len(same) == 1 or (same and kind != "container") else None)
                if pos is not None:
                    mapping[c["data-id"]] = pos["data-id"]
                    changed = True
    return mapping


def split_selectors(prelude):
    """Split a selector list on top-level commas (not the ones inside :is(), :not(), etc.)."""
    parts, depth, cur = [], 0, ""
    for ch in prelude:
        if ch in "([":
            depth += 1
        elif ch in ")]":
            depth -= 1
        if ch == "," and depth == 0:
            parts.append(cur)
            cur = ""
        else:
            cur += ch
    parts.append(cur)
    return parts


def serialize(node):
    return tinycss2.serialize([node])


def clone_rules(home_css, mapping, page_doc):
    """Return CSS text with every rule that targets a mapped home element, rewritten for the page."""
    rev = {}
    for pid, hid in mapping.items():
        rev.setdefault(hid, []).append(pid)
    id_re = re.compile(r"elementor-element-([0-9a-f]{6,8})\b")

    def rewrite_rule(rule):
        prelude = tinycss2.serialize(rule.prelude)
        if f".elementor-{HOME_DOC}" not in prelude:
            return ""
        out = []
        for sel in split_selectors(prelude):
            ids = id_re.findall(sel)
            if not ids or not all(i in rev for i in ids):
                continue
            variants = [sel]
            for hid in set(ids):
                variants = [v.replace(f"elementor-element-{hid}", f"elementor-element-{pid}") for v in variants for pid in rev[hid]]
            out += [v.replace(f".elementor-{HOME_DOC}", f".elementor-{page_doc}") for v in variants]
        if not out:
            return ""
        return ",".join(s.strip() for s in out) + "{" + tinycss2.serialize(rule.content) + "}"

    def walk(nodes):
        parts = []
        for n in nodes:
            if n.type == "qualified-rule":
                parts.append(rewrite_rule(n))
            elif n.type == "at-rule" and n.content is not None and n.lower_at_keyword in ("media", "supports", "container"):
                inner = walk(tinycss2.parse_rule_list(n.content, skip_whitespace=True, skip_comments=True))
                if inner:
                    parts.append("@" + n.at_keyword + tinycss2.serialize(n.prelude) + "{" + inner + "}")
        return "".join(p for p in parts if p)

    return walk(tinycss2.parse_stylesheet(home_css, skip_whitespace=True, skip_comments=True))


def localize(css):
    """Point absolute bccbengaluru.in asset URLs at the bundled copies (relative to uploads/elementor/css/)."""
    return re.sub(r"https?:(?:\\?/){2}bccbengaluru\.in/wp-content/", "../../../", css)


def main():
    for name in os.listdir(CSS_DIR):  # bundled originals
        path = os.path.join(CSS_DIR, name)
        text = open(path, encoding="utf-8").read()
        fixed = localize(text)
        if fixed != text:
            open(path, "w", encoding="utf-8").write(fixed)

    home_html = open(os.path.join(THEME, "views", "front-page.html"), encoding="utf-8").read()
    home_root = doc_root(home_html, HOME_DOC)
    home_css = open(os.path.join(CSS_DIR, f"post-{HOME_DOC}.css"), encoding="utf-8").read()

    for view, doc in PAGES.items():
        html = open(os.path.join(THEME, "views", view + ".html"), encoding="utf-8").read()
        root = doc_root(html, doc)
        mapping = build_map(root, home_root)
        all_ids = [e["data-id"] for e in elements(root)]
        css = f"/* Rebuilt from post-{HOME_DOC}.css for sections copied from the home page (scripts/clone_css.py). */\n"
        css += clone_rules(home_css, mapping, doc)
        manual = os.path.join(MANUAL, f"post-{doc}.css")
        if os.path.exists(manual):
            css += "\n/* Hand-written styles (scripts/manual-css). */\n" + open(manual, encoding="utf-8").read()
        os.makedirs(OUT_DIR, exist_ok=True)
        with open(os.path.join(OUT_DIR, f"post-{doc}.css"), "w", encoding="utf-8") as f:
            f.write(localize(css))
        unmapped = [i for i in all_ids if i not in mapping]
        print(f"{view} (post-{doc}): {len(mapping)}/{len(all_ids)} elements matched, {len(css)} bytes")
        if "-v" in sys.argv:
            for e in elements(root):
                if e["data-id"] in unmapped:
                    print("   ", e["data-id"], e.get("data-widget_type") or e.get("data-element_type"), norm(e.get_text(" "))[:70])


if __name__ == "__main__":
    main()
