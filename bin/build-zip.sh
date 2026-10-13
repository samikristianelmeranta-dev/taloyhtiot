#!/usr/bin/env bash
# Rakentaa WordPressiin asennettavan zip-paketin: build/taloyhtio-kilpailutus.zip
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p build
rm -f build/taloyhtio-kilpailutus.zip
(cd plugin && zip -qr ../build/taloyhtio-kilpailutus.zip taloyhtio-kilpailutus -x '*.DS_Store')
echo "build/taloyhtio-kilpailutus.zip"
