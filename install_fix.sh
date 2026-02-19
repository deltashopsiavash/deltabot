#!/usr/bin/env bash
set -euo pipefail

WEBROOT="/var/www/html"
echo "[*] Installing deltabot into $WEBROOT"

if [ ! -d "$WEBROOT" ]; then
  echo "Webroot not found: $WEBROOT"
  exit 1
fi

# If this script is run from inside extracted package directory
PKG_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Copy deltabot folder
sudo rm -rf "$WEBROOT/deltabot"
sudo cp -a "$PKG_DIR/deltabot" "$WEBROOT/deltabot"

# Copy backup runner to webroot
if [ -f "$DEST_DIR/backupnutif.php" ]; then
  sudo cp -a "$DEST_DIR/backupnutif.php" "$WEBROOT/backupnutif.php"
elif [ -f "$PKG_DIR/backupnutif.php" ]; then
  sudo cp -a "$PKG_DIR/backupnutif.php" "$WEBROOT/backupnutif.php"
else
  echo "backupnutif.php not found in package!"
fi

# Permissions
sudo chown -R www-data:www-data "$WEBROOT/deltabot"
if [ -f "$WEBROOT/backupnutif.php" ]; then
  sudo chown www-data:www-data "$WEBROOT/backupnutif.php" || true
  sudo chmod 644 "$WEBROOT/backupnutif.php" || true
fi


sudo systemctl restart apache2 || true

echo "[+] Done."
echo "Check:"
echo "  ls -la $WEBROOT/backupnutif.php $WEBROOT/deltabot/baseInfo.php"
echo "  curl -i http://127.0.0.1/backupnutif.php | head"