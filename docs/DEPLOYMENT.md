# Deployment guide

This guide describes a production deployment of the 1.0.0 stable release on
Debian 13 (Trixie), with Apache and PHP-FPM behind a Cloudflare Tunnel.
Cloudflare terminates public TLS and `cloudflared` forwards traffic to Apache
over loopback. The server does not need a public HTTP or HTTPS listener.

## Requirements

- Debian 13 (Trixie).
- A Cloudflare zone and a hostname managed by that zone.
- A Cloudflare account permitted to create and run a Tunnel.
- PHP 8.1 or newer, Apache 2.4, and PHP-FPM.
- Outbound HTTPS access to Cloudflare and `api.abuseipdb.com`.
- A valid AbuseIPDB API key.

The project has no Composer dependencies and does not use cURL.

## Install Debian 13 packages

Run this on a fresh container:

```bash
# Refresh the package index.
sudo apt update

# Install Apache and the PHP-FPM runtime.
# php8.4-cli: provides the php command and runs the PHPUnit PHAR.
# php8.4-fpm: executes PHP requests received from Apache.
# php8.4-mbstring and php8.4-xml: extensions required by PHPUnit.
# phpunit: installs Debian's PHPUnit command as an additional test runner.
# git: downloads the application source.
# curl and ca-certificates: support smoke tests and trusted HTTPS requests.
sudo apt install -y \
    apache2 \
    php8.4-cli \
    php8.4-fpm \
    php8.4-mbstring \
    php8.4-xml \
    phpunit \
    git \
    curl \
    ca-certificates

# Enable Apache and PHP-FPM at boot and start them immediately.
sudo systemctl enable --now apache2 php8.4-fpm
```

Install `cloudflared` from Cloudflare's package repository:

```bash
# Add Cloudflare's signing key directory.
sudo install -m 0755 -d /usr/share/keyrings

# Download the repository signing key and store it in the keyring.
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg \
    | sudo tee /usr/share/keyrings/cloudflare-main.gpg > /dev/null

# Register Cloudflare's Debian repository for cloudflared.
echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main" \
    | sudo tee /etc/apt/sources.list.d/cloudflared.list > /dev/null

# Refresh package metadata after adding the repository.
sudo apt update

# Install the Cloudflare Tunnel client.
sudo apt install -y cloudflared
```

The repository includes `tools/phpunit-10.phar`, which is the pinned test
runner used by this project. The `phpunit` Debian package is also installed
for a system-level PHPUnit command; `php8.4-mbstring` and `php8.4-xml` provide
the extensions required by the pinned PHAR.

## Filesystem layout

Keep the repository root outside the public document root. Apache serves only
`src/api/` locally; Cloudflare is the only public entry point.

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

The application currently loads `env.php` from the repository root. Do not
move it without also changing the include paths in the PHP source.

## Install the application

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

Replace every placeholder in `env.php`. Set `ENVIRONMENT` to a value other
than `dev` before production use.

## Apache and PHP-FPM

Apache listens only on `127.0.0.1:8080`. This prevents direct Internet access;
`cloudflared` is the only process that should connect to this listener.

Create `/etc/apache2/sites-available/php-abuseipdb.conf`:

```apache
Listen 127.0.0.1:8080

<VirtualHost 127.0.0.1:8080>
    ServerName localhost

    DocumentRoot /var/www/php-abuseipdb/src/api

    <Directory /var/www/php-abuseipdb/src/api>
        Options -Indexes
        AllowOverride None
        Require all granted

        # Send PHP files to the local PHP-FPM 8.4 socket.
        <FilesMatch "\.php$">
            SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost"
        </FilesMatch>
    </Directory>

    # Deny accidental access to files outside the document root.
    <Directory /var/www/php-abuseipdb>
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/php-abuseipdb-error.log
    CustomLog ${APACHE_LOG_DIR}/php-abuseipdb-access.log combined
</VirtualHost>
```

Enable the modules and site:

```bash
# Enable Apache modules for PHP-FPM proxying and response headers.
sudo a2enmod proxy proxy_fcgi headers

# Enable the virtual host created above.
sudo a2ensite php-abuseipdb

# Disable the default site so it cannot expose another listener or document root.
sudo a2dissite 000-default

# Validate Apache configuration before reloading it.
sudo apache2ctl configtest

# Apply the new virtual-host configuration without stopping Apache.
sudo systemctl reload apache2
```

Verify the local origin before configuring Cloudflare:

```bash
# Check that Apache answers locally through PHP-FPM.
curl -i http://127.0.0.1:8080/abuseipdb.php
```

A `405` response is expected because the endpoint requires `POST`; a PHP
error or connection failure means Apache/PHP-FPM is not configured correctly.

## Configure the Cloudflare Tunnel

Authenticate `cloudflared` with an account that can manage the target zone:

```bash
# Open the Cloudflare authorization flow in a browser.
cloudflared tunnel login

# Create a named tunnel and note the generated tunnel UUID.
cloudflared tunnel create php-abuseipdb
```

Create `/etc/cloudflared/config.yml`:

```yaml
tunnel: REPLACE_WITH_TUNNEL_UUID
credentials-file: /root/.cloudflared/REPLACE_WITH_TUNNEL_UUID.json

ingress:
  # Forward the public hostname to Apache over loopback.
  - hostname: abuseipdb.example.com
    service: http://127.0.0.1:8080

  # Reject every hostname not explicitly listed above.
  - service: http_status:404
```

Replace the UUID, credentials path, and hostname. Protect the credentials
file; it grants access to the Tunnel.

Create the DNS route and install the Tunnel as a system service:

```bash
# Create the proxied DNS record for the public hostname.
cloudflared tunnel route dns php-abuseipdb abuseipdb.example.com

# Install cloudflared as a systemd service using /etc/cloudflared/config.yml.
sudo cloudflared service install

# Enable the Tunnel at boot and start it now.
sudo systemctl enable --now cloudflared

# Confirm that the Tunnel service is running.
sudo systemctl status cloudflared --no-pager
```

Public HTTPS is terminated by Cloudflare and forwarded through the encrypted
Tunnel. No Certbot package, local certificate, Apache port 443 listener, or
public inbound port 80 is required.

## Production configuration

Use the constants from [`example.env.php`](../example.env.php). Do not use
different names such as `ABUSEIPDB_API_KEY` or `PROXY_TOKEN`; those constants
are not read by this project.

For a Cloudflare Tunnel, PHP sees `REMOTE_ADDR` as the local `cloudflared`
process. Set the trusted proxy list accordingly:

```php
define("ENVIRONMENT", "production");
define("CORS_ALLOW_ORIGIN", "https://abuseipdb.example.com");
define("CORS_ALLOW_HEADERS", "Content-Type, Authorization");
define("TRUSTED_PROXIES", ["127.0.0.1/32", "::1/128"]);
```

This trusts forwarded client-IP headers only from the local Tunnel connector.
Do not add broad private ranges unless every host in those ranges is a
trusted proxy.

`CORS_ALLOW_ORIGIN` is emitted as one literal response header. Configure one
exact origin; comma-separated origin lists and wildcard patterns are not
interpreted as allow-lists.

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

The endpoint requires `POST`, JSON, an action, and a token:

```bash
# Store the public Cloudflare hostname used by the smoke tests.
HOST="https://abuseipdb.example.com/abuseipdb.php"

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

The valid request should return the project's `message`, `success`, and
optional `parameters` envelope. Verify that `/env.php`, `/cache/ips.json`, and
`/logs/api.log` are not publicly accessible through the Cloudflare hostname.

Check the Tunnel and Apache logs if the public request fails:

```bash
# Show recent Tunnel service messages.
sudo journalctl -u cloudflared -n 50 --no-pager

# Show recent Apache errors.
sudo journalctl -u apache2 -n 50 --no-pager
```

## Upgrades and rollback

Keep `env.php`, `cache/`, and `logs/` outside Git-tracked changes:

```bash
# Enter the deployed repository.
cd /var/www/php-abuseipdb

# Download the latest tag list from the remote repository.
sudo git fetch --tags

# Select the new stable release.
sudo git checkout v1.0.1-stable

# Validate Apache configuration before applying the upgrade.
sudo apache2ctl configtest

# Reload Apache; cloudflared continues using the same local service.
sudo systemctl reload apache2
```

Rollback to the last verified stable tag and reload Apache:

```bash
# Select the last verified stable release.
sudo git checkout v1.0.0-stable

# Validate and apply the rollback configuration.
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## Out of scope

- Certbot or locally managed TLS certificates
- Public Apache ports 80 and 443
- nginx-specific configuration
- Windows/IIS and shared hosting
- application-level rate limiting
- automatic retries for upstream failures
- a front-controller router; `/abuseipdb.php` remains the stable interface
