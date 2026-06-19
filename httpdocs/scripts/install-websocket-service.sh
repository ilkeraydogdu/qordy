#!/bin/bash
# QORDY WebSocket systemd servisini kurar
# Çalıştırma: sudo ./install-websocket-service.sh

set -e
SERVICE_NAME="qordy-websocket"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVICE_FILE="${SCRIPT_DIR}/${SERVICE_NAME}.service"

if [ ! -f "$SERVICE_FILE" ]; then
    echo "Hata: $SERVICE_FILE bulunamadı"
    exit 1
fi

echo "WebSocket servisi kuruluyor..."
cp "$SERVICE_FILE" /etc/systemd/system/
systemctl daemon-reload
systemctl enable $SERVICE_NAME
systemctl restart $SERVICE_NAME

echo ""
echo "Kurulum tamamlandı."
echo "  Durum:  systemctl status $SERVICE_NAME"
echo "  Log:    tail -f /var/www/vhosts/qordy.com/logs/websocket-server.log"
echo "  Durdur: systemctl stop $SERVICE_NAME"
echo "  Başlat: systemctl start $SERVICE_NAME"
