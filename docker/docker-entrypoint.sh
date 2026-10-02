#!/bin/bash
set -e

CONFIG_FILE="/var/www/html/config.inc.php"
TEMPLATE_FILE="/var/www/html/config.TEMPLATE.inc.php"

if [ ! -f "$CONFIG_FILE" ] && [ -f "$TEMPLATE_FILE" ]; then
    cp "$TEMPLATE_FILE" "$CONFIG_FILE"
fi

if [ -f "$CONFIG_FILE" ]; then
    sed -i "s|^driver\s*=.*|driver = mysqli|" "$CONFIG_FILE"
    sed -i "s|^host\s*=.*|host = ${OJS_DB_HOST:-db}|" "$CONFIG_FILE"
    sed -i "s|^username\s*=.*|username = ${OJS_DB_USER:-ojsuser}|" "$CONFIG_FILE"
    sed -i "s|^password\s*=.*|password = ${OJS_DB_PASSWORD:-ojspassword}|" "$CONFIG_FILE"
    sed -i "s|^name\s*=.*|name = ${OJS_DB_NAME:-ojs}|" "$CONFIG_FILE"
    sed -i "s|^collation\s*=.*|collation = utf8mb4_unicode_ci|" "$CONFIG_FILE"

    sed -i "s|^files_dir\s*=.*|files_dir = /var/www/files|" "$CONFIG_FILE"
    sed -i "s|^public_files_dir\s*=.*|public_files_dir = public|" "$CONFIG_FILE"

    sed -i "s|^installed\s*=.*|installed = On|" "$CONFIG_FILE"
    sed -i "s|^base_url\s*=.*|base_url = \"${OJS_BASE_URL:-http://localhost}\"|" "$CONFIG_FILE"

    if grep -qE '^app_key\s*=' "$CONFIG_FILE"; then
        if grep -qE '^app_key\s*=[[:space:]]*$' "$CONFIG_FILE"; then
            APP_KEY="$(php -r 'echo base64_encode(random_bytes(32));')"
            sed -i "s|^app_key\s*=.*|app_key = \"base64:$APP_KEY\"|" "$CONFIG_FILE"
        fi
    else
        APP_KEY="$(php -r 'echo base64_encode(random_bytes(32));')"
        sed -i "/^\[general\]/a app_key = \"base64:$APP_KEY\"" "$CONFIG_FILE"
    fi
fi

mkdir -p /var/www/files /var/www/html/cache /var/www/html/public
chown -R www-data:www-data /var/www/files /var/www/html/cache /var/www/html/public

exec "$@"