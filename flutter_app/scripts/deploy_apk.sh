#!/usr/bin/env bash
# Build the Flutter release APK and publish it to the public web folder.
#
# Does three things:
#   1. Runs `flutter build apk --release`.
#   2. Copies the resulting APK to   httpdocs/public/downloads/qordy-app.apk
#      (keeping the previous build as   qordy-app.prev.apk   for rollback).
#   3. Writes   qordy-app.json   next to it, so the mobile app can read the
#      latest version / build / sha1 and decide whether to prompt for an
#      update.
#
# Usage:
#   ./scripts/deploy_apk.sh            # build + deploy
#   ./scripts/deploy_apk.sh --no-build # deploy pre-built apk only

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DEPLOY_DIR="/var/www/vhosts/qordy.com/httpdocs/public/downloads"
APK_NAME="qordy-app.apk"
MANIFEST_NAME="qordy-app.json"
OWNER="qordy.com_jckqwoy6r4j:psacln"

do_build=1
for arg in "$@"; do
  case "$arg" in
    --no-build) do_build=0 ;;
    -h|--help)
      grep '^#' "$0" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *) echo "Unknown arg: $arg" >&2; exit 1 ;;
  esac
done

cd "$APP_DIR"

# --- read version + build number from pubspec.yaml -------------------------
VERSION_LINE=$(grep -E '^version:' pubspec.yaml | head -1 | awk '{print $2}')
VERSION_NAME="${VERSION_LINE%%+*}"
VERSION_CODE="${VERSION_LINE##*+}"
if [ "$VERSION_NAME" = "$VERSION_CODE" ]; then
  VERSION_CODE="1"
fi

echo "==> pubspec version: $VERSION_NAME (build $VERSION_CODE)"

# --- build -----------------------------------------------------------------
if [ "$do_build" -eq 1 ]; then
  echo "==> flutter build apk --release"
  flutter build apk --release
fi

SRC="$APP_DIR/build/app/outputs/flutter-apk/app-release.apk"
if [ ! -f "$SRC" ]; then
  echo "Build output not found at $SRC" >&2
  exit 1
fi

mkdir -p "$DEPLOY_DIR"
DST="$DEPLOY_DIR/$APK_NAME"
PREV="$DEPLOY_DIR/${APK_NAME%.apk}.prev.apk"

if [ -f "$DST" ]; then
  echo "==> backing up previous build to $PREV"
  cp -f "$DST" "$PREV"
fi

echo "==> publishing new build to $DST"
cp -f "$SRC" "$DST"

SHA1=$(sha1sum "$DST" | awk '{print $1}')
SIZE=$(stat -c%s "$DST")
RELEASED_AT=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

cat > "$DEPLOY_DIR/$MANIFEST_NAME" <<JSON
{
  "version": "$VERSION_NAME",
  "build": $VERSION_CODE,
  "url": "/downloads/$APK_NAME",
  "sha1": "$SHA1",
  "size": $SIZE,
  "releasedAt": "$RELEASED_AT",
  "minBuild": 1,
  "required": false,
  "notes": ""
}
JSON

# --- permissions -----------------------------------------------------------
chown "$OWNER" "$DST" "$DEPLOY_DIR/$MANIFEST_NAME" 2>/dev/null || true
[ -f "$PREV" ] && chown "$OWNER" "$PREV" 2>/dev/null || true
chmod 644 "$DST" "$DEPLOY_DIR/$MANIFEST_NAME"
[ -f "$PREV" ] && chmod 644 "$PREV"

# Landing page reads live metadata from this manifest (no npm rebuild needed).
# Flutter [UpdateService] uses the same file for in-app update prompts.

echo ""
echo "==> deployed:"
echo "    apk:      $DST ($(du -h "$DST" | cut -f1))"
echo "    manifest: $DEPLOY_DIR/$MANIFEST_NAME"
echo "    sha1:     $SHA1"
echo "    version:  $VERSION_NAME+$VERSION_CODE"
