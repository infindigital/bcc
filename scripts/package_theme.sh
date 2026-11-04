#!/usr/bin/env bash
# Build the installable theme zip: dist/bcc-theme.zip
# 1. regenerates the theme views from scripts/source-html
# 2. downloads the original assets into wordpress-theme/bcc/assets (skips files already there)
# 3. zips wordpress-theme/bcc as bcc/ (the folder name WordPress expects)
set -euo pipefail
cd "$(dirname "$0")/.."
python3 scripts/build_theme.py
python3 scripts/fetch_assets.py --dest wordpress-theme/bcc/assets
mkdir -p dist
rm -f dist/bcc-theme.zip
(cd wordpress-theme && zip -qr ../dist/bcc-theme.zip bcc -x '*/.DS_Store')
echo "Created dist/bcc-theme.zip ($(du -h dist/bcc-theme.zip | cut -f1))"
