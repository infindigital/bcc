#!/usr/bin/env python3
"""Hand-written styles for page sections that have no counterpart on the home page.

The original Elementor stylesheets for these pages were lost; these rules recreate them with the
site's own design tokens (global colours/typography from post-11.css) and the home page's patterns.
Writes scripts/manual-css/post-<doc>.css, which scripts/clone_css.py appends to each page's CSS.

Usage: python3 scripts/manual_css.py && python3 scripts/clone_css.py
"""
import os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "scripts", "manual-css")

# Global tokens (Elementor kit, post-11.css)
PRIMARY = "var(--e-global-color-primary)"        # #433F72
SECONDARY = "var(--e-global-color-secondary)"    # #181818
TEXT = "var(--e-global-color-text)"              # #6F6F6F
ACCENT = "var(--e-global-color-accent)"          # #B77B35
WHITE = "var(--e-global-color-308e809)"          # #FFFFFF
BORDER = "var(--e-global-color-a954db2)"         # #B6B3D5
BG_LIGHT = "var(--e-global-color-651faef)"       # #E9EBF9
BG2 = "var(--e-global-color-b25f141)"            # #7242FA
GOLD = "#ECA927"


def typo(gid):
    t = f"--e-global-typography-{gid}"
    return (f"font-family:var({t}-font-family),Sans-serif;font-size:var({t}-font-size);"
            f"font-weight:var({t}-font-weight);line-height:var({t}-line-height);"
            f"letter-spacing:var({t}-letter-spacing);text-transform:var({t}-text-transform);")


H1, H2, H3, H4, H5 = "583e54c", "5d167aa", "d3dae9a", "83682a1", "7044a64"
HERO, BOLD, BODY, SMALL, MEDIUM, BUTTON = "3715edf", "65f4081", "b2ce6af", "69c152f", "text", "accent"


def sel(doc, *ids, suffix=""):
    return ",".join(f".elementor-{doc} .elementor-element.elementor-element-{i}{suffix}" for i in ids)


def banner(doc, outer, inner, title, crumbs):
    return f"""
/* Page banner */
{sel(doc, outer)}{{--display:flex;--flex-direction:column;--justify-content:center;--align-items:center;--content-width:1280px;--padding-top:15em;--padding-bottom:8em;--padding-left:1em;--padding-right:1em;--overflow:hidden;background-color:{SECONDARY};background-image:linear-gradient(165deg,rgba(24,24,24,.92) 20%,rgba(114,66,250,.9) 100%),url("../../../uploads/2026/01/hero.jpg");background-position:center center;background-size:cover;background-repeat:no-repeat;}}
{sel(doc, outer, suffix=" > .e-con-inner > .elementor-shape-bottom")}{{z-index:2;pointer-events:none;}}
{sel(doc, outer, suffix=" > .e-con-inner > .elementor-shape-bottom svg")}{{width:calc(115% + 1.3px);height:90px;transform:translateX(-50%) rotateY(180deg);}}
{sel(doc, outer, suffix=" > .e-con-inner > .elementor-shape-bottom .elementor-shape-fill")}{{fill:{WHITE};}}
{sel(doc, inner)}{{--display:flex;--flex-direction:column;--align-items:center;--justify-content:center;--gap:1em;--row-gap:1em;--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;text-align:center;}}
{sel(doc, title, suffix=" .elementor-heading-title")}{{{typo(HERO)}color:{WHITE};text-align:center;}}
{sel(doc, crumbs, suffix=" .elementor-icon-list-items")}{{justify-content:center;}}
{sel(doc, crumbs, suffix=" .elementor-icon-list-item")}{{{typo(BOLD)}}}
{sel(doc, crumbs, suffix=" .elementor-icon-list-text")},{sel(doc, crumbs, suffix=" .elementor-icon-list-item > a")}{{color:{WHITE};}}
{sel(doc, crumbs, suffix=" .elementor-icon-list-item > a:hover .elementor-icon-list-text")}{{color:{GOLD};}}
{sel(doc, crumbs, suffix=" .elementor-icon-list-icon i")}{{color:{GOLD};font-size:18px;}}
{sel(doc, crumbs, suffix=" .elementor-icon-list-icon")}{{margin:0 .4em;}}
@media(max-width:1024px){{{sel(doc, outer)}{{--padding-top:12em;--padding-bottom:6em;}}{sel(doc, outer, suffix=" > .e-con-inner > .elementor-shape-bottom svg")}{{height:60px;}}}}
@media(max-width:767px){{{sel(doc, outer)}{{--padding-top:10em;--padding-bottom:5em;}}{sel(doc, outer, suffix=" > .e-con-inner > .elementor-shape-bottom svg")}{{height:40px;}}}}
"""


def section_boxed(doc, ids, pad_top="6em", pad_bottom="6em"):
    return f"{sel(doc, *ids)}{{--content-width:1280px;--padding-top:{pad_top};--padding-bottom:{pad_bottom};--padding-left:1em;--padding-right:1em;}}\n"


def bearys_text(doc, heading, text, box, button=None):
    """The 'BEARYS' text column that sits on the dark gradient band (About, History)."""
    css = f"""
/* BEARYS story on the dark band */
{sel(doc, heading, suffix=" .elementor-heading-title")}{{{typo(H2)}color:{WHITE};}}
{sel(doc, text)}{{color:rgba(255,255,255,.85);font-style:italic;{typo(MEDIUM)}}}
{sel(doc, box, suffix=" .elementor-icon-box-wrapper")}{{text-align:left;border-left:3px solid {GOLD};padding-left:1.2em;}}
{sel(doc, box, suffix=" .elementor-icon-box-title")}{{{typo(BOLD)}color:{GOLD};letter-spacing:1px;margin-bottom:.6em;}}
{sel(doc, box, suffix=" .elementor-icon-box-description")}{{{typo(BODY)}color:rgba(255,255,255,.8);}}
"""
    if button:
        css += f"""{sel(doc, button, suffix=" .elementor-button")}{{{typo(BUTTON)}background-color:{ACCENT};color:{WHITE};border-radius:3px;padding:1.1em 2.2em;}}
{sel(doc, button, suffix=" .elementor-button:hover")}{{background-color:{BG2};color:{WHITE};}}
"""
    return css


def heading(doc, hid, centered=False, tag_typo=H2, color=SECONDARY, upper=False):
    extra = "text-align:center;" if centered else ""
    extra += "text-transform:uppercase;" if upper else ""
    return f"{sel(doc, hid, suffix=' .elementor-heading-title')}{{{typo(tag_typo)}color:{color};{extra}}}\n"


def bcc_story(doc, outer, row, intro, intro_h, intro_t, aims_outer, aims, aims_h, aims_boxes, grid, cards):
    """'The Bearys Central Committee', 'Aims & Objectives', 'Origin' and 'Key Activities' (About, History)."""
    boxes = ",".join(sel(doc, b) for b in aims_boxes)
    box_sel = lambda s: ",".join(f".elementor-{doc} .elementor-element.elementor-element-{b}{s}" for b in aims_boxes)
    card_sel = lambda s: ",".join(f".elementor-{doc} .elementor-element.elementor-element-{c}{s}" for c in cards)
    return f"""
/* The Bearys Central Committee / Aims & Objectives / Origin / Key Activities */
{sel(doc, outer)}{{--display:flex;--flex-direction:column;--content-width:1280px;--gap:3em;--row-gap:3em;--padding-top:6em;--padding-bottom:6em;--padding-left:1em;--padding-right:1em;}}
{sel(doc, row)}{{--display:flex;--flex-direction:row;--flex-wrap:wrap;--align-items:stretch;--gap:3em;--row-gap:3em;--column-gap:3em;--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;}}
{sel(doc, intro)}{{--width:100%;--display:flex;--flex-direction:column;--gap:1em;--row-gap:1em;--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;}}
{heading(doc, intro_h, tag_typo=H3, color=PRIMARY, upper=True)}{sel(doc, intro_t)}{{{typo(BODY)}color:{TEXT};}}
{sel(doc, aims_outer)}{{--width:100%;--padding-top:3.5em;--padding-bottom:3.5em;--padding-left:3.5em;--padding-right:3.5em;background-color:{BG_LIGHT};border-radius:15px;}}
{sel(doc, aims)}{{--display:flex;--flex-direction:row;--flex-wrap:wrap;--gap:2em;--row-gap:2em;--column-gap:3em;--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;}}
{sel(doc, aims_h)}{{width:100%;}}
{heading(doc, aims_h, tag_typo=H2)}{boxes}{{width:calc(50% - 1.5em);}}
{box_sel(" .elementor-icon-box-wrapper")}{{display:flex;align-items:flex-start;gap:1.2em;text-align:left;}}
{box_sel(" .elementor-icon-box-icon")}{{margin:0;}}
{box_sel(".elementor-view-stacked .elementor-icon")}{{background-color:{PRIMARY};color:{WHITE};fill:{WHITE};font-size:22px;padding:.75em;border-radius:10px;}}
{box_sel(" .elementor-icon-box-title")}{{{typo(H5)}color:{SECONDARY};margin:0 0 .4em;}}
{box_sel(" .elementor-icon-box-description")}{{{typo(BODY)}color:{TEXT};margin:0;}}
{sel(doc, grid)}{{--display:grid;--e-con-grid-template-columns:repeat(2,1fr);--gap:2em;--row-gap:2em;--column-gap:2em;--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;grid-template-columns:repeat(2,1fr);}}
{card_sel("")}{{--padding-top:2.5em;--padding-bottom:2.5em;--padding-left:2.5em;--padding-right:2.5em;background-color:{WHITE};border:1px solid {BORDER};border-radius:15px;box-shadow:0 10px 40px -5px rgba(0,0,0,.08);}}
{card_sel(" .elementor-icon-box-wrapper")}{{text-align:left;}}
{card_sel(" .elementor-icon-box-title")}{{{typo(H4)}color:{PRIMARY};margin:0 0 .6em;}}
{card_sel(" .elementor-icon-box-description")}{{{typo(BODY)}color:{TEXT};margin:0;}}
@media(max-width:1024px){{{boxes}{{width:100%;}}{sel(doc, grid)}{{grid-template-columns:1fr;--e-con-grid-template-columns:1fr;}}}}
@media(max-width:767px){{{sel(doc, aims_outer)}{{--padding-top:2em;--padding-bottom:2em;--padding-left:1.5em;--padding-right:1.5em;}}{sel(doc, outer)}{{--padding-top:4em;--padding-bottom:4em;}}}}
"""


PAGES = {}

# About Us (post 94)
PAGES["94"] = (
    banner("94", "43c85fcb", "3f912b94", "4dc77976", "6dafdc82")
    + bearys_text("94", "fcb1011", "7089688", "337e5d1", button="eab37d5")
    + heading("94", "b15358a", centered=True)
    + bcc_story("94", "c4301ca", "9ac4cd8", "3dd2b2c", "15b3f0c", "e9fc044",
                "1bad353", "7173a0a", "e9f9c56", ["d97dc02", "d693ce4", "8cad71a", "6df1ab7"],
                "bbef5d7", ["e8466bb", "53720c6"])
)

# Our Team (post 102)
PAGES["102"] = banner("102", "2c4f503d", "3f329b26", "2c4a78cd", "6dd538ce")

# History (post 429)
PAGES["429"] = (
    banner("429", "e0e64d1", "8eca10d", "df968f6", "23d4d85")
    + bearys_text("429", "bf26018", "b1c6f8d", "a59070e")
    + heading("429", "c745d7d", centered=True)
    + bcc_story("429", "e19197a", "cf760a7", "9116bfc", "91f4cc9", "5bd30b3",
                "79b26d2", "af097af", "bc5a433", ["bb734fb", "3dcb527", "b17dd09", "9a5a34b"],
                "eb4411f", ["364805c", "e3860f2"])
    + f"""
/* Beary Koota - a grand celebration of identity */
{sel("429", "6ee2acc")}{{--display:flex;--flex-direction:row;--flex-wrap:wrap;--align-items:center;--gap:3em;--row-gap:3em;--column-gap:3em;--padding-top:0;--padding-bottom:3em;--padding-left:0;--padding-right:0;}}
{sel("429", "7884880")}{{--width:55%;--display:flex;--flex-direction:column;--gap:1em;--row-gap:1em;--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;}}
{sel("429", "7485aab")}{{--width:calc(45% - 3em);--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;}}
{sel("429", "fe04b32", suffix=" img")}{{width:100%;border-radius:15px;box-shadow:0 10px 40px -5px rgba(0,0,0,.15);}}
{heading("429", "8f52a23", tag_typo=H3, color=PRIMARY, upper=True)}{sel("429", "9f349ae")}{{{typo(BODY)}color:{TEXT};}}
{sel("429", "9f349ae", suffix=" ul")}{{padding-left:1.2em;margin:.6em 0;}}
@media(max-width:1024px){{{sel("429", "7884880", "7485aab")}{{--width:100%;}}}}
"""
)

# Contact Us (posts 114 and the form 119)
c = "114"
PAGES[c] = banner(c, "53a712b5", "546e516", "266feba8", "669eba00") + f"""
/* Contact: info card + message form */
{sel(c, "7eed83d")}{{--content-width:1280px;--padding-top:6em;--padding-bottom:6em;--padding-left:1em;--padding-right:1em;}}
{sel(c, "6c28c0e7")}{{--display:flex;--flex-direction:row;--flex-wrap:wrap;--align-items:stretch;--gap:3em;--row-gap:3em;--column-gap:3em;--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;}}
{sel(c, "4eca1509")}{{--width:38%;--display:flex;--flex-direction:column;--gap:1.6em;--row-gap:1.6em;--padding-top:3em;--padding-bottom:3em;--padding-left:2.5em;--padding-right:2.5em;background-color:{PRIMARY};background-image:linear-gradient(165deg,{SECONDARY} 0%,{BG2} 140%);border-radius:15px;}}
{sel(c, "4cf21a98")}{{--width:calc(62% - 3em);--display:flex;--flex-direction:column;--gap:1em;--row-gap:1em;--padding-top:3em;--padding-bottom:3em;--padding-left:3em;--padding-right:3em;background-color:{WHITE};border:1px solid {BG_LIGHT};border-radius:15px;box-shadow:0 10px 40px -5px rgba(0,0,0,.1);}}
{sel(c, "75c4ddbe", suffix=" .elementor-heading-title")}{{{typo(H3)}color:{WHITE};}}
{sel(c, "3009a6bf", "460d234a", "3a75ed0c")}{{width:100%;max-width:100%;}}
{sel(c, "3009a6bf", "460d234a", "3a75ed0c", suffix=" .elementor-icon-box-wrapper")}{{display:flex;align-items:center;gap:1em;text-align:left;}}
{sel(c, "3009a6bf", "460d234a", "3a75ed0c", suffix=" .elementor-icon-box-icon")}{{margin:0;}}
{sel(c, "3009a6bf", "460d234a", "3a75ed0c", suffix=" .elementor-icon")}{{background-color:rgba(255,255,255,.12);color:{GOLD};fill:{GOLD};font-size:22px;padding:.7em;border-radius:10px;}}
{sel(c, "3009a6bf", "460d234a", "3a75ed0c", suffix=" .elementor-icon-box-title")}{{{typo(BOLD)}color:{WHITE};margin:0 0 .25em;}}
{sel(c, "3009a6bf", "460d234a", "3a75ed0c", suffix=" .elementor-icon-box-description")},{sel(c, "3009a6bf", "460d234a", "3a75ed0c", suffix=" .elementor-icon-box-description a")}{{{typo(BODY)}color:rgba(255,255,255,.85);margin:0;}}
{sel(c, "7a5a62a0")}{{--content-width:100%;--display:flex;--flex-direction:column;--gap:.8em;--row-gap:.8em;--padding-top:1.4em;--padding-bottom:0;--padding-left:0;--padding-right:0;border-top:1px solid rgba(255,255,255,.18);}}
{sel(c, "b9af94b", suffix=" .elementor-heading-title")}{{{typo(H5)}color:{WHITE};}}
{sel(c, "37132b70")}{{--grid-template-columns:repeat(0,auto);--icon-size:16px;--grid-column-gap:10px;--grid-row-gap:0;}}
{sel(c, "37132b70", suffix=" .elementor-social-icon")}{{background-color:rgba(255,255,255,.12);--icon-padding:.7em;border-radius:8px;}}
{sel(c, "37132b70", suffix=" .elementor-social-icon i")},{sel(c, "37132b70", suffix=" .elementor-social-icon svg")}{{color:{WHITE};fill:{WHITE};}}
{sel(c, "37132b70", suffix=" .elementor-social-icon:hover")}{{background-color:{GOLD};}}
{sel(c, "1d22a9ae", suffix=" .elementor-heading-title")}{{{typo(H3)}color:{SECONDARY};}}
{sel(c, "4de4b1e2")}{{{typo(BODY)}color:{TEXT};}}
{sel(c, "7824e843")}{{--divider-border-style:solid;--divider-color:{BG_LIGHT};--divider-border-width:1px;}}
{sel(c, "7824e843", suffix=" .elementor-divider-separator")}{{width:100%;}}
{sel(c, "7824e843", suffix=" .elementor-divider")}{{padding-block-start:.5em;padding-block-end:.5em;}}
{sel(c, "6ceb9d53")}{{--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;}}
{sel(c, "20cb15bb", suffix=" iframe")}{{height:450px;filter:grayscale(.2);}}
/* MetForm fields (form 119, styled to match the theme) */
.elementor-{c} .metform-form-content .mf-input-wrapper{{display:flex;flex-direction:column;gap:.45em;}}
.elementor-{c} .metform-form-content .elementor-119 .e-con{{--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;--gap:1.2em;--row-gap:1.2em;--column-gap:1.2em;}}
.elementor-{c} .metform-form-content .elementor-119 > .e-con > .e-con{{--flex-direction:row;--flex-wrap:wrap;}}
.elementor-{c} .metform-form-content .elementor-widget-mf-text,.elementor-{c} .metform-form-content .elementor-widget-mf-email,.elementor-{c} .metform-form-content .elementor-widget-mf-telephone{{width:calc(50% - .6em);}}
.elementor-{c} .metform-form-content .elementor-widget-mf-textarea,.elementor-{c} .metform-form-content .elementor-widget-mf-button{{width:100%;}}
.elementor-{c} .mf-input-label{{{typo(BOLD)}color:{SECONDARY};margin:0;}}
.elementor-{c} .mf-input{{{typo(BODY)}width:100%;color:{SECONDARY};background-color:{BG_LIGHT};border:1px solid transparent;border-radius:8px;padding:.95em 1.1em;transition:border-color .2s,background-color .2s;}}
.elementor-{c} .mf-input:focus{{outline:0;border-color:{PRIMARY};background-color:{WHITE};}}
.elementor-{c} .mf-input::placeholder{{color:#9a98b5;}}
.elementor-{c} .mf-textarea{{min-height:150px;}}
.elementor-{c} .metform-btn{{{typo(BUTTON)}width:100%;color:{WHITE};background-color:{PRIMARY};background-image:linear-gradient(165deg,{PRIMARY} 20%,{BG2} 100%);border:0;border-radius:8px;padding:1.15em 2em;cursor:pointer;}}
.elementor-{c} .metform-btn:hover{{background-image:none;background-color:{ACCENT};}}
@media(max-width:1024px){{{sel(c, "4eca1509", "4cf21a98")}{{--width:100%;}}}}
@media(max-width:767px){{.elementor-{c} .metform-form-content .elementor-widget-mf-text,.elementor-{c} .metform-form-content .elementor-widget-mf-email,.elementor-{c} .metform-form-content .elementor-widget-mf-telephone{{width:100%;}}{sel(c, "4cf21a98", "4eca1509")}{{--padding-left:1.5em;--padding-right:1.5em;}}}}
"""

# Blog page (post 517): banner + grid section spacing
PAGES["517"] = banner("517", "7cf24dc5", "70dd5a2", "b98afca", "3ef2b25b") + f"""
{sel("517", "723ff87d")}{{--content-width:1280px;--padding-top:5em;--padding-bottom:6em;--padding-left:1em;--padding-right:1em;}}
"""

# Single post (Elementor template 366)
s = "366"
PAGES[s] = banner(s, "39e195f2", "e941ec3", "6b891c9e", "6b891c9e") + f"""
/* Single post: category label above the title */
{sel(s, "6b891c9e", suffix=" .elementor-heading-title")}{{{typo(BOLD)}font-size:clamp(.8rem,.75rem + .2vw,.9rem);text-transform:uppercase;letter-spacing:1.5px;color:{GOLD};}}
{sel(s, "6b891c9e", suffix=" .elementor-heading-title a")}{{color:{GOLD};}}
{sel(s, "5cc02300", suffix=" .elementor-heading-title")}{{{typo(H1)}color:{WHITE};text-align:center;max-width:900px;margin:0 auto;}}
/* Content + sidebar */
{sel(s, "1a4e7a6")}{{--display:flex;--flex-direction:row;--flex-wrap:wrap;--align-items:flex-start;--content-width:1280px;--gap:3em;--row-gap:3em;--column-gap:3em;--padding-top:5em;--padding-bottom:6em;--padding-left:1em;--padding-right:1em;}}
{sel(s, "10d56c98")}{{--width:calc(68% - 1.5em);--display:flex;--flex-direction:column;--gap:2em;--row-gap:2em;--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;}}
{sel(s, "7d232ccc")}{{--width:calc(32% - 1.5em);--display:flex;--flex-direction:column;--gap:1.2em;--row-gap:1.2em;--padding-top:2.2em;--padding-bottom:2.2em;--padding-left:2em;--padding-right:2em;background-color:{BG_LIGHT};border-radius:15px;}}
{sel(s, "a4cf51a", suffix=" img")}{{width:100%;border-radius:15px;}}
{sel(s, "f209a00")}{{{typo(BODY)}color:{TEXT};}}
{sel(s, "f209a00", suffix=" h2")},{sel(s, "f209a00", suffix=" h3")},{sel(s, "f209a00", suffix=" strong")}{{color:{SECONDARY};}}
{sel(s, "f209a00", suffix=" ul")}{{padding-left:1.2em;}}
{sel(s, "f209a00", suffix=" .wp-block-gallery")}{{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1em;margin:1.5em 0;}}
{sel(s, "f209a00", suffix=" .wp-block-gallery figure")}{{width:100% !important;margin:0 !important;}}
{sel(s, "f209a00", suffix=" .wp-block-gallery img")},{sel(s, "f209a00", suffix=" figure img")}{{width:100%;height:100%;object-fit:cover;aspect-ratio:3/2;border-radius:10px;}}
{sel(s, "611c47f2")}{{--display:flex;--flex-direction:row;--align-items:center;--justify-content:space-between;--content-width:100%;--padding-top:1.4em;--padding-bottom:1.4em;--padding-left:0;--padding-right:0;border-top:1px solid {BG_LIGHT};border-bottom:1px solid {BG_LIGHT};}}
{sel(s, "571d9f40", suffix=" .elementor-heading-title")}{{{typo(H5)}color:{SECONDARY};}}
{sel(s, "24d6b318")}{{--grid-template-columns:repeat(0,auto);--icon-size:15px;--grid-column-gap:8px;--grid-row-gap:0;}}
{sel(s, "24d6b318", suffix=" .elementor-social-icon")}{{background-color:{BG_LIGHT};--icon-padding:.75em;}}
{sel(s, "24d6b318", suffix=" .elementor-social-icon i")},{sel(s, "24d6b318", suffix=" .elementor-social-icon svg")}{{color:{PRIMARY};fill:{PRIMARY};}}
{sel(s, "24d6b318", suffix=" .elementor-social-icon:hover")}{{background-color:{PRIMARY};}}
{sel(s, "24d6b318", suffix=" .elementor-social-icon:hover i")},{sel(s, "24d6b318", suffix=" .elementor-social-icon:hover svg")}{{color:{WHITE};fill:{WHITE};}}
{sel(s, "65bc8650", "6be89d67", "4e51b961")}{{--divider-border-style:solid;--divider-color:{BORDER};--divider-border-width:1px;}}
{sel(s, "65bc8650", "6be89d67", "4e51b961", suffix=" .elementor-divider-separator")}{{width:100%;}}
{sel(s, "65bc8650", suffix=" .elementor-divider__text")}{{{typo(H4)}color:{SECONDARY};margin:0 1em 0 0;}}
{sel(s, "41003d4f", "7b41ff9", suffix=" .elementor-heading-title")}{{{typo(H5)}color:{SECONDARY};}}
/* Related posts ("You may also like") */
{sel(s, "7a8f077f", suffix=" .post-items")}{{display:flex;flex-direction:column;gap:1.5em;}}
{sel(s, "7a8f077f", suffix=" .elementskit-blog-block-post")}{{background-color:{WHITE};border:1px solid {BG_LIGHT};border-radius:15px;overflow:hidden;box-shadow:0 10px 40px -5px rgba(0,0,0,.08);}}
{sel(s, "7a8f077f", suffix=" .elementskit-blog-block-post .row")}{{display:flex;align-items:center;}}
{sel(s, "7a8f077f", suffix=" .ekit-col-6")}{{flex:0 0 50%;max-width:50%;}}
{sel(s, "7a8f077f", suffix=" .elementskit-entry-thumb img")}{{width:100%;height:220px;object-fit:cover;display:block;}}
{sel(s, "7a8f077f", suffix=" .elementskit-post-body")}{{padding:1.5em 2em;}}
{sel(s, "7a8f077f", suffix=" .entry-title")}{{{typo(H5)}margin:0 0 .6em;}}
{sel(s, "7a8f077f", suffix=" .entry-title a")}{{color:{SECONDARY};}}
{sel(s, "7a8f077f", suffix=" .entry-title a:hover")}{{color:{BG2};}}
{sel(s, "7a8f077f", suffix=" .elementskit-post-footer p")}{{{typo(SMALL)}color:{TEXT};margin:0;}}
/* Sidebar: latest news and categories */
{sel(s, "285b03e7", suffix=" .elementor-icon-list-item")}{{padding:.6em 0;border-bottom:1px solid rgba(67,63,114,.12);}}
{sel(s, "285b03e7", suffix=" .elementor-icon-list-item a")}{{display:flex;align-items:center;gap:.9em;}}
{sel(s, "285b03e7", suffix=" .elementskit-post-list-img img")},{sel(s, "285b03e7", suffix=" img")}{{width:64px;height:64px;object-fit:cover;border-radius:8px;}}
{sel(s, "285b03e7", suffix=" .elementor-icon-list-text")}{{{typo(BOLD)}color:{SECONDARY};font-size:.95em;}}
{sel(s, "285b03e7", suffix=" .meta-lists")},{sel(s, "285b03e7", suffix=" .meta-date")},{sel(s, "285b03e7", suffix=" .meta-category")}{{{typo(SMALL)}color:{TEXT};}}
{sel(s, "149a7434", suffix=" a")}{{{typo(BOLD)}color:{SECONDARY};}}
{sel(s, "149a7434", suffix=" a:hover")}{{color:{BG2};}}
@media(max-width:1024px){{{sel(s, "10d56c98", "7d232ccc")}{{--width:100%;}}}}
@media(max-width:767px){{{sel(s, "7a8f077f", suffix=" .ekit-col-6")}{{flex:0 0 100%;max-width:100%;}}{sel(s, "7a8f077f", suffix=" .elementskit-blog-block-post .row")}{{flex-wrap:wrap;}}}}
"""


def main():
    os.makedirs(OUT, exist_ok=True)
    for doc, css in PAGES.items():
        with open(os.path.join(OUT, f"post-{doc}.css"), "w", encoding="utf-8") as f:
            f.write(css.strip() + "\n")
        print(f"manual-css/post-{doc}.css {len(css)} bytes")


if __name__ == "__main__":
    main()
