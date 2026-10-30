# Pop-up

A WordPress plugin that shows an image pop-up (for example a campaign banner) with settings under **Settings → Pop-up**. The admin interface is in Lithuanian.

## Settings

| Setting | Options |
| --- | --- |
| Enabled | on / off |
| Image | from the media library, or any http(s) URL |
| Alternative text | for screen readers; the site name when empty |
| Link | optional; open in a new tab or not |
| Pages | front page only, all pages, or selected pages |
| Frequency | once per browser session, once every N days, or on every page view |
| Delay | 0–120 seconds after the page loads |
| Display period | start and end date (inclusive, site time zone), both optional |
| Devices | all, desktop and tablet only (wider than 767 px), phones only |
| Look | backdrop darkness, maximum width, close on backdrop click |

Changing the image or the link shows the pop-up again to visitors who already saw it; so does the "show again" box. Administrators can preview the saved pop-up on any page with `?wppu_preview=1`, whatever the other settings say.

The front end loads only where the pop-up may appear. Dates, device and "already seen" are checked in the browser, so full-page caching does not break them. "Already seen" is kept in `sessionStorage` or `localStorage`; the plugin sets no cookies.

## Requirements

- WordPress 6.4 or newer
- PHP 8.0 or newer

## Release updates

The plugin checks the latest published, non-prerelease release in this public repository and offers it on the normal WordPress update screens. No token is needed.

WordPress installs the downloaded `wp-pop-up.zip` only when its SHA-256 matches the `digest` that GitHub's release API reports for that asset; a release asset without a digest is not offered, and a mismatch stops the update. The download may only redirect to GitHub's release-asset hosts.

A token is optional. A fine-grained GitHub personal access token with **Contents: Read** access to this repository raises the GitHub API rate limit. Add it to wp-config.php, before the “That's all, stop editing” line:

    define( 'WP_POP_UP_GITHUB_TOKEN', 'github_pat_...' );

The token is sent only to api.github.com, never to the download redirect, and is not stored in the database.

## Development

    php tests/settings.php
    php tests/updater.php
    python3 .github/playground/smoke.py --php 8.4

The last command boots a throwaway WordPress in [WordPress Playground](https://wordpress.github.io/wordpress-playground/) (needs Node 24 and Python 3; on Windows set `PYTHONUTF8=1`). CI and the security checks are described in [docs/cybersecurity.md](docs/cybersecurity.md).

Browser behaviour (open/close, Esc, focus trap, frequency, pages, devices, dates, preview, media picker) is covered by a Playwright script. Run it against a local WordPress with the plugin active and an `admin` / `password` administrator, for example a Playground site started **without** the blueprint `login` step (that step logs every browser in, so "visitors" would be administrators). It changes the plugin's settings:

    NODE_PATH="$(npm root -g)" node tests/e2e/popup.cjs http://127.0.0.1:9400

## Publishing a release

1. Update the plugin header version and the `VERSION` constant in `wp-pop-up.php`.
2. Update the stable tag and changelog in `readme.txt`.
3. Create and publish a GitHub release whose tag matches the version, such as `v1.0.1`.
4. The **Build plugin release** workflow runs the security gate on that commit, then attaches `wp-pop-up.zip` (with minified JavaScript and CSS). WordPress offers the release after this asset is attached.

## License

GPL-2.0-or-later.
