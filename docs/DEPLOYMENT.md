# Deployment guide

This guide describes a production deployment of the 1.0.0 stable release on
Linux with Apache and PHP-FPM. The application itself is independent of the
web server, but the examples below use Apache.

## Requirements

- Linux with Apache 2.4 and PHP-FPM.
- PHP 8.1 or newer.
- The PHP `openssl` support required for HTTPS stream requests.
- Outbound HTTPS access to `api.abuseipdb.com`.
- A valid AbuseIPDB API key.

The project has no Composer dependencies and does not use cURL.

## Install Debian 13 packages

Debian 13 (Trixie) provides PHP 8.4 in its standard repositories. Run the
following commands on a fresh container:

```bash
# Refresh the package index.
sudo apt update

# Install Apache and the PHP-FPM runtime.
# php8.4-cli: provides the php command and runs the PHPUnit PHAR.
# php8.4-fpm: executes PHP requests received from Apache.
# php8.4-mbstring and php8.4-xml: extensions required by PHPUnit.
# phpunit: installs Debian's PHPUnit command as an additional test runner.
# certbot and python3-certbot-apache: obtain and configure Apache TLS certificates.
# git: downloads the application source.
# curl and ca-certificates: support smoke tests and trusted HTTPS requests.
sudo apt install -y \
    apache2 \
    php8.4-cli \
    php8.4-fpm \
    php8.4-mbstring \
    php8.4-xml \
    phpunit \
    certbot \
    python3-certbot-apache \
    git \
    curl \
    ca-certificates

# Enable Apache and PHP-FPM at boot and start them immediately.
sudo systemctl enable --now apache2 php8.4-fpm
```

The repository includes `tools/phpunit-10.phar`, which is the pinned test
runner used by this project. The `phpunit` Debian package is also installed
for a system-level PHPUnit command; `php8.4-mbstring` and `php8.4-xml` provide
the extensions required by the pinned PHAR.

## Filesystem layout

Keep the repository root outside the public document root. The endpoint files
are under `src/api/`, while the endpoint includes the shared PHP files from
`src/includes/`.

```text
/var/www/php-abuseipdb/
├── env.php
├── cache/
│   └── ips.json
├── logs/
├── src/
│   ├── api/
│   │   └── abuseipdb.php
│   └── includes/
├── tests/
└── tools/
```

The Apache `DocumentRoot` should be `src/api/`. This prevents direct access
to `env.php`, cache data, logs, tests, and include files. The application
currently loads `env.php` from the repository root; do not move it without
also changing the include paths in the PHP source.

## Installation

```bash
# Create the parent directory for the application.
sudo mkdir -p /var/www

# Move into the web server's application area.
cd /var/www

# Download the repository without exposing its root as a document root.
sudo git clone https://github.com/cqt470/php-abuseipdb-api.git php-abuseipdb
cd /var/www/php-abuseipdb

# Select the stable release tag.
sudo git checkout v1.0.0-stable

# Copy the configuration template; edit the resulting file before use.
sudo cp example.env.php env.php

# Create runtime storage and initialize the JSON cache.
sudo mkdir -p cache logs
printf '[]' | sudo tee cache/ips.json > /dev/null

# Give the deployment group ownership of the application tree.
sudo chown -R root:www-data /var/www/php-abuseipdb

# Allow PHP-FPM to write cache and log data.
sudo chown www-data:www-data cache logs cache/ips.json
sudo chmod 0750 cache logs
sudo chmod 0640 cache/ips.json

# Make the configuration readable by PHP-FPM but not writable by it.
sudo chmod 0640 env.php
```

Replace every placeholder in `env.php`. In particular, set
`API_KEY_ABUSEIPDB`, `API_KEY_CUSTOM`, `SALT_SHA256`, and
`URL_DISCORD_WEBHOOK` if webhooks are enabled. Set `ENVIRONMENT` to a value
other than `dev` before production use.

The cache and log directories must be writable by the PHP-FPM user. The
configuration file must be readable by PHP-FPM but must not be writable by it.

## Apache and PHP-FPM

Create a virtual host similar to the following, replacing the hostname and
PHP-FPM socket for the server:

```apache
<VirtualHost *:80>
    ServerName abuseipdb-proxy.example.com
    Redirect permanent / https://abuseipdb-proxy.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName abuseipdb-proxy.example.com

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/abuseipdb-proxy.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/abuseipdb-proxy.example.com/privkey.pem
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

    DocumentRoot /var/www/php-abuseipdb/src/api

    <Directory /var/www/php-abuseipdb/src/api>
        Options -Indexes
        AllowOverride None
        Require all granted
        <FilesMatch "\.php$">
            SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost"
        </FilesMatch>
    </Directory>

    <Directory /var/www/php-abuseipdb>
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/php-abuseipdb-error.log
    CustomLog ${APACHE_LOG_DIR}/php-abuseipdb-access.log combined
</VirtualHost>
```

Enable the required modules and site:

```bash
# Enable Apache modules for PHP-FPM proxying, response headers, and TLS.
sudo a2enmod proxy proxy_fcgi headers ssl

# Enable the virtual host created above.
sudo a2ensite php-abuseipdb

# Validate Apache configuration before reloading it.
sudo apache2ctl configtest

# Apply the new virtual-host configuration without stopping Apache.
sudo systemctl reload apache2
```

The endpoint URL is:

```text
https://abuseipdb-proxy.example.com/abuseipdb.php
```

## Production configuration

Use the constants from [`example.env.php`](../example.env.php). Do not use
different names such as `ABUSEIPDB_API_KEY` or `PROXY_TOKEN`; those constants
are not read by this project.

Recommended production settings:

```php
define("ENVIRONMENT", "production");
define("CORS_ALLOW_ORIGIN", "https://app.example.com");
define("CORS_ALLOW_HEADERS", "Content-Type, Authorization");
define("TRUSTED_PROXIES", ["203.0.113.10/32"]);
```

`CORS_ALLOW_ORIGIN` is emitted as one literal response header by the current
code. Configure one exact origin; comma-separated origin lists and wildcard
patterns are not interpreted as allow-lists. The default `*` is suitable only
for development.

Configure `TRUSTED_PROXIES` only with the actual reverse-proxy or CDN ranges.
The application trusts `HTTP_CF_CONNECTING_IP` and
`HTTP_X_FORWARDED_FOR` only when `REMOTE_ADDR` belongs to that list (or the
built-in Cloudflare ranges when the constant is not defined).

## HTTPS and permissions

HTTPS is required because requests carry the custom token and the server
communicates with AbuseIPDB using the official API key. Obtain and renew a
certificate with the distribution's supported ACME tooling, for example:

```bash
# Request a TLS certificate and configure Apache HTTPS support.
sudo certbot --apache -d abuseipdb-proxy.example.com
```

Do not expose the repository root, `env.php`, `cache/`, or `logs/` through
Apache aliases or symlinks. IP addresses can occur in cache entries, logs, and
Discord payloads, so protect these files according to the applicable privacy
requirements.

## Logging and rotation

`Logger` writes files such as `logs/auth.log` and `logs/api.log` using
`FILE_APPEND | LOCK_EX`. The application does not rotate or delete logs.
Configure host-level rotation, for example:

```text
/var/www/php-abuseipdb/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0640 www-data www-data
}
```

## Smoke test

The endpoint requires `POST`, JSON, an action, and a token. The following
checks use the real interface and avoid printing secrets:

```bash
# Store the public endpoint URL used by the smoke tests.
HOST="https://abuseipdb-proxy.example.com/abuseipdb.php"

# Missing token: the API should return HTTP 401.
curl -sS -o /dev/null -w "%{http_code}\n" \
  -X POST -H "Content-Type: application/json" \
  -d '{"action":"check","ip":"8.8.8.8"}' "$HOST"

# Use the value configured as API_KEY_CUSTOM in env.php.
TOKEN="REPLACE_WITH_API_KEY_CUSTOM"

# Send an authenticated check request and print its JSON response.
curl -sS -i -X POST \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"action":"check","ip":"8.8.8.8"}' "$HOST"
```

The valid request should return a JSON response with the project's
`message`, `success`, and optional `parameters` envelope. A real upstream
check consumes the configured AbuseIPDB quota unless the IP is already in
the cache.

Also verify that requesting `/env.php`, `/cache/ips.json`, and
`/logs/api.log` does not expose files. With `src/api/` as the document root,
these paths should normally return 404.

## Upgrades and rollback

Keep `env.php`, `cache/`, and `logs/` outside Git-tracked changes. For an
upgrade, fetch the new stable tag, inspect the release notes, and reload
Apache after updating the working tree:

```bash
# Enter the deployed repository.
cd /var/www/php-abuseipdb

# Download the latest tag list from the remote repository.
sudo git fetch --tags

# Select the new stable release.
sudo git checkout v1.0.1-stable

# Validate Apache configuration before applying the upgrade.
sudo apache2ctl configtest

# Reload Apache so it serves the upgraded source.
sudo systemctl reload apache2
```

Rollback to the previous verified tag and reload Apache if the smoke test
fails:

```bash
# Select the last verified stable release.
sudo git checkout v1.0.0-stable

# Validate and apply the rollback configuration.
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## Out of scope

- nginx-specific configuration
- Windows/IIS and shared hosting
- application-level rate limiting
- automatic retries for upstream failures
- a front-controller router; `/abuseipdb.php` remains the stable interface
