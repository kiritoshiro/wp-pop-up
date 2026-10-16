# CI and security checks

Project type: **WordPress plugin**. Default branch: `main`. Same setup as the other adventistai WordPress plugins.

## Security gate

`.github/workflows/security-gate.yml` is the only workflow that triggers the checks: on pull requests and pushes to `main`, weekly, and manually. The checks are reusable workflows that run only through it. Its final job, **All security checks passed**, fails unless every check succeeded; a cancelled or unexpectedly skipped check counts as a failure. The release workflow calls the same gate on the release commit, so a ZIP is built only when every check passes on exactly that commit.

A green gate means every scanner ran to completion and reported nothing it blocks on. It does not mean the code is secure.

## What runs

- **Cybersecurity baseline** (`cybersecurity.yml`), one job:
  - Semgrep CE (`p/php`, `p/javascript`; profile in `.github/security/profile.json`);
  - TruffleHog secret scan of the PR range or fetched history, without verification; output shows only detector, file and line;
  - zizmor (workflow security) and actionlint (workflow syntax);
  - Trivy (dependencies and configuration, HIGH and CRITICAL);
  - PHPCS with the WordPress security sniffs (`.github/security/phpcs.xml`; tools isolated in `.github/security/composer.json`);
  - unit tests: `tests/settings.php` (sanitizing, where the pop-up loads, date window) and `tests/updater.php`.
- **CodeQL** (`codeql.yml`): JavaScript.
- **WordPress checks** (`wordpress-checks.yml`):
  - WordPress [Plugin Check](https://github.com/WordPress/plugin-check-action) in a throwaway WordPress (wp-env): general, security, performance and accessibility. Errors fail; warnings are annotations. `plugin_repo` is left out because the plugin is self-hosted and has its own updater. The slug `wp-pop-up` is the text domain.
  - Playground smoke test (`.github/playground/smoke.py`) on the oldest supported pair (PHP 8.0, WordPress 6.4) and the newest (PHP 8.4, latest WordPress). It activates the plugin, shows the pop-up on all pages, checks that a page loads its script, style and settings, logs in, and loads the front end, the preview and every wp-admin menu page (including Settings → Pop-up). It fails on activation errors, non-200 pages, PHP errors on a page, and any PHP error, warning, notice or deprecation from this plugin in `debug.log`. Settings: `.github/playground/config.json`.

## Not in the gate

- **Links** (`links.yml`): lychee checks links in Markdown and `readme.txt` on PRs that change them, monthly and on demand. A third-party site being down should not block a merge. adventistai.lt is excluded in `.lychee.toml` (Cloudflare answers GitHub runners with 403).
- **Dependabot**: GitHub Actions and the isolated Composer tools, monthly, at most two open PRs each.

Never ship `.github/security` or its tooling in the plugin package; the release workflow copies only the plugin files.
