# PHP AbuseIPDB API
*A custom AbuseIPDB API used to protect your websites, written in PHP.*

## Environment
The project has been developed in the following environment:
- OS: Debian (WSL)
- PHP Version: 8.4.26

## Configuration
Before using your API, you should configure it. To do this, stay in the main directory and run:
```bash
cp example.env.php env.php
```
This will create the .env file used in the entire project. The minimum changes you have to do to it is to simply get your AbuseIPDB API key and put it in `API_KEY_ABUSEIPDB`.

## Starting the server
Starting a PHP server depends on your situation, it can be one of the following two:

### Installing PHP
Before testing or working with the API, please make sure to have installed the `php` package. To confirm, run:
```bash
php -v
```
If you see something like this, it means you have it installed:
```bash
user@PC-Name:Path/To/Directory$ php -v
PHP 8.4.26 (cli) (built: Sep 24 2026 17:16:18) (NTS)
Copyright (c) The PHP Group
Built by Debian
Zend Engine v4.4.26, Copyright (c) Zend Technologies
    with Zend OPcache v8.4.26, Copyright (c), by Zend Technologie
```
Otherwise, install it with the following command (taken from [the official guide](https://www.php.net/downloads.php?os=linux&osvariant=debian-downloads&version=default)):
```bash
# Update the package lists.
sudo apt update

# Install PHP.
sudo apt install -y php
```

### Development
*Use this API in a development environment.*

To start your development server, `cd` into the `src` folder and then run
```bash
php -S localhost:8000 -t src/
```
You should see something like
```bash
user@PC-Name:Path/To/Directory$ php -S localhost:8000 -t src/
[Thu Oct 15 07:44:20 2026] PHP 8.4.26 Development Server (http://localhost:8000) started
[Thu Oct 15 07:44:25 2026] 127.0.0.1:53148 Accepted
[Thu Oct 15 07:44:25 2026] 127.0.0.1:53148 [405]: GET /api/check.php
[Thu Oct 15 07:44:25 2026] 127.0.0.1:53148 Closing
[Thu Oct 15 07:44:25 2026] 127.0.0.1:53158 Accepted
```

### Production
*Use this API in a production environment.*

TODO