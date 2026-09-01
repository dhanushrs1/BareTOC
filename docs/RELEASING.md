# Releasing BareTOC

BareTOC releases are published in two places: GitHub provides the public source release, and the SiteFueler Update API provides the package consumed by WordPress dashboards.

## 1. Prepare the release

1. Create a release branch from the protected `main` branch.
2. Set the same version in the `Version` header and `BARETOC_VERSION` constant in `baretoc.php`.
3. Set the matching `Stable tag` in `readme.txt` and update both changelogs.
4. Run:

   ```bash
   composer validate --strict --no-check-publish
   composer check
   ```

5. Open a pull request, wait for every required PHP check, and merge it.

## 2. Create the GitHub release

Create an annotated tag matching the plugin version, including the `v` prefix, and push it:

```bash
git tag -a v1.3.0 -m "BareTOC 1.3.0"
git push origin v1.3.0
```

The release workflow verifies that the tag matches the plugin header, reruns the quality suite, and creates an installable ZIP with the canonical `baretoc/` root.

## 3. Publish to the update service

1. Download the ZIP attached to the corresponding GitHub release.
2. Sign in to the private SiteFueler release manager from an allowed IP address.
3. Upload the ZIP and verify the inspection results:
   - slug: `baretoc`
   - main file: `baretoc/baretoc.php`
   - Update URI: `https://api.sitefueler.com/updater/baretoc/`
   - version: identical to the Git tag without `v`
   - minimum WordPress: `6.2` or the current declared requirement
   - minimum PHP: `7.4` or the current declared requirement
4. Enter the tested WordPress version and release changelog.
5. Publish the release. Published versions are immutable; fix a bad release with a higher version.

## 4. Verify delivery

Confirm that the public metadata endpoint returns the new stable release and a signed, expiring package URL:

```bash
curl "https://api.sitefueler.com/updater/index.php?action=check&slug=baretoc&channel=stable"
```

On a staging WordPress site running the previous updater-capable BareTOC version:

1. Open **Dashboard → Updates** and click **Check again**.
2. Confirm that BareTOC shows the expected version, compatibility details, and changelog.
3. Install the update and verify that the plugin remains active in the `baretoc` directory.
4. Confirm the table of contents, settings, smooth scrolling, and optional CSS still work.

Version 1.3.0 is the updater bootstrap release. Installations older than 1.3.0 must receive that version through a manual ZIP update before dashboard delivery can work.

## Private update access

The production API currently supports public update metadata. If access keys are enabled later, define `BARETOC_UPDATE_ACCESS_KEY` outside the plugin source, normally in `wp-config.php`, or supply it with the `baretoc_update_access_key` filter. Never commit a production access key.
