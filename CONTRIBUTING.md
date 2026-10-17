# Contributing

Thanks for contributing to `php-abuseipdb-api`.

The project is currently in alpha, so small focused changes are preferred over broad refactors.

## Pull request workflow

- Create a branch from `master`.
- Keep commits focused and descriptive.
- Document what changed and how it was validated.
- Open a PR targeting `master`.

## Code and docs expectations

- Follow PSR-12 for PHP files.
- Keep markdown documentation in English.
- Keep endpoint docs aligned with actual source behavior.
- Do not commit `env.php` or `cache/ips.json`.

## Testing expectations

There is no full PHPUnit suite yet. Validate changes with the existing project flow and include manual verification steps in your PR.

## Priority areas

Contributions are especially useful in these areas:

- Cloudflare IP trust hardening in `Security::get_ip()`.
- Router/front-controller migration.
- Additional automated testing.
