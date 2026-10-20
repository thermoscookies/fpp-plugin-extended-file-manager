# Extended File Manager

An FPP 10.2 plugin that provides a separate, folder-aware video browser. It supports subfolders, multiselect, and appending selected videos to an existing FPP playlist with relative `mediaName` values such as `Halloween/example.mp4`.

## Scope

- Browses FPP's configured `videoDirectory` only.
- Shows video files in the current folder and navigable subfolders.
- Rejects absolute paths, `.`/`..` traversal, backslashes, symlinks, non-video files, and paths outside the resolved video directory.
- Adds entries to the selected playlist's `mainPlaylist`, preserving all other playlist data and skipping duplicate `mediaName` entries.
- Does not create, delete, rename, or upload media, and has no daemon or external service.

The playlist write is explicit: it happens only after the operator presses “Add selected to playlist.” Existing FPP playlist JSON is read under an exclusive lock and rewritten with appended entries. FPP's playlist format and relative media behavior are documented in the official FPP configuration documentation.

## Development checks

```sh
php -l api.php
php -l lib/MediaBrowser.php
php -l content.php
php tests/test_media_browser.php
python3 -m json.tool pluginInfo.json >/dev/null
```

The tests use temporary directories and never touch a real FPP media or playlist directory.

## Deployment review checklist

1. Confirm the GitHub owner/repository URLs in `pluginInfo.json` before listing the plugin.
2. Install locally through FPP's Plugin Manager on a test FPP 10.2 device.
3. Create a test playlist and verify that a selected subfolder video appears as a relative `mediaName` and plays.
4. Verify existing playlist entries and metadata remain intact.
5. Review the privacy disclosure and test both light/dark themes on desktop and touch-sized screens.

This repository does not install to a Raspberry Pi, push to GitHub, or modify an existing FPP playlist during development.

## Attribution and license

This plugin started from the FalconChristmas FPP plugin template. The original template attribution and GPLv2 license are retained; see [LICENSE](LICENSE) and the included FPP plugin guidance documents.

