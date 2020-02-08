#!/usr/bin/env bash
set -e

echo "[*] Bisq Markets API installation script"

##### change as necessary for your system

SYSTEMD_SERVICE_HOME=/etc/systemd/system
SYSTEMD_ENV_HOME=/etc/default

ROOT_USER=root
ROOT_GROUP=root
ROOT_HOME=~root

BISQ_USER=bisq
BISQ_HOME=~bisq

MARKETS_REPO_URL=https://github.com/bisq-network/bisq-markets
MARKETS_REPO_NAME=bisq-markets
MARKETS_REPO_TAG=master

MARKETS_DEBIAN_PKG="apache2 libapache2-mod-php7.2 php7.2-opcache php-apcu"

#####

echo "[*] Enabling Bisq Statsnode configuration in Bisq service"
sudo -H -i -u "${ROOT_USER}" sed -i -e 's!BISQ_DAO_FULLNODE=.*!BISQ_DAO_FULLNODE=false!' "${SYSTEMD_ENV_HOME}/bisq.env"
sudo -H -i -u "${ROOT_USER}" sed -i -e 's!BISQ_DUMP_BLOCKCHAIN=.*!BISQ_DUMP_BLOCKCHAIN=false!' "${SYSTEMD_ENV_HOME}/bisq.env"
sudo -H -i -u "${ROOT_USER}" sed -i -e 's!BISQ_DUMP_STATISTICS=.*!BISQ_DUMP_STATISTICS=true!' "${SYSTEMD_ENV_HOME}/bisq.env"
sudo -H -i -u "${ROOT_USER}" sed -i -e 's!BISQ_ENTRYPOINT=.*!BISQ_ENTRYPOINT=bisq-statsnode!' "${SYSTEMD_ENV_HOME}/bisq.env"
sudo -H -i -u "${ROOT_USER}" service bisq restart

echo "[*] Cloning Bisq Markets API repo"
sudo -H -i -u "${BISQ_USER}" git config --global advice.detachedHead false
sudo -H -i -u "${BISQ_USER}" git clone --branch "${MARKETS_REPO_TAG}" "${MARKETS_REPO_URL}" "${BISQ_HOME}/${MARKETS_REPO_NAME}"
sudo -H -i -u "${BISQ_USER}" cp "${BISQ_HOME}/${MARKETS_REPO_NAME}/settings.json.example" "${BISQ_HOME}/${MARKETS_REPO_NAME}/settings.json"

echo "[*] Installing Bisq Markets API debian packages"
sudo -H -i -u "${ROOT_USER}" DEBIAN_FRONTEND=noninteractive apt-get update -q
sudo -H -i -u "${ROOT_USER}" DEBIAN_FRONTEND=noninteractive apt-get install -qq -y ${MARKETS_DEBIAN_PKG}

echo "[*] Installing Bisq Markets API webroot symlink"
sudo -H -i -u "${ROOT_USER}" mv /var/www/html /var/www/html.old
sudo -H -i -u "${ROOT_USER}" ln -s "${BISQ_HOME}/bisq-markets" /var/www/html

echo '[*] Done!'

exit 0
