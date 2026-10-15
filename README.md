# PHP AbuseIPDB API
*A custom AbuseIPDB API used to protect your websites, written in PHP.*

## Environment
The project has been developed in the following environment:
- OS: Debian (WSL)
- PHP Version: 8.4.26

## Testing
To test your API or application, you can check the examples i created in [test/](test/).

To start your server, `cd` into the `src` folder and then run
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