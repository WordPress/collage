# Release Collage

The plugin header in `collage.php` and the `Stable tag` in `readme.txt` must match.
Update both and add the changelog entry before tagging. For version 0.2.3:

```sh
git tag -a v0.2.3 -m "Collage 0.2.3"
git push origin v0.2.3
```

GitHub Actions runs the WordPress integration and upload tests, builds the ZIP,
runs Plugin Check on the extracted ZIP, and boots the Playground examples.
Only the tag job publishes a release, using the ZIP from the passing build and
its SHA-256 checksum. Use the attached `collage.zip`, not the source archive.
After publishing, the release job starts the demo deployment. It copies the
latest release ZIP and verifies its checksum instead of rebuilding the plugin.

The release tag identifies the exact source. Its README has the build
instructions. When changing the bundled readers or what ships, check
each new or changed third-party file's terms against `THIRD-PARTY.txt`. Inspect
the built ZIP for the required license texts and notices before tagging. The
demo uses the same ZIP build and publishes the license beside its standalone
examples.

## Submit to WordPress.org

1. Download the release's `collage.zip`. It includes `collage.php`, `readme.txt`,
   the GPL license, third-party notices, and the bundled readers.
2. Sign in as `adamziel` at [Add your Plugin](https://wordpress.org/plugins/developers/add/)
   and upload that ZIP for review. Request `collage` as the slug if offered.
   The directory team assigns the final slug.
3. After approval, extract the ZIP and copy the contents of its `collage/`
   folder into the SVN checkout's `trunk/`. Tag the same files as `tags/0.2.3/`.
   Keep the directory readme's stable tag in sync with the release.
4. Copy the icons, banners, numbered screenshots, and `blueprints/` directory from
   `.wordpress-org/` into the SVN checkout's top-level `assets/` directory.
   Use **Test Preview** in the listing's Advanced view before making Preview
   public. The directory preview installs the directory build; it does not use
   the GitHub ZIP.

See [Using Subversion](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
and [Plugin Previews](https://developer.wordpress.org/plugins/wordpress-org/previews-and-blueprints/).

## Notes for reviewers

- Collage converts documents on the WordPress server with bundled PHP readers.
  It makes no calls to a conversion service and does not require a remote
  account. The Playground demos are optional links.
- Imports require WordPress post-editing permissions. The REST routes use the
  WordPress REST nonce. Page creation and image uploads have separate permission
  checks.
- Previews are sandboxed. A preview stores its result for up to an hour and
  does not publish or create media. Saving creates a draft and any supported
  embedded image attachments.
- The original reader revision and license terms are in `THIRD-PARTY.txt`.
  The repository contains the reader source in `lib/` and the full build scripts. The examples are original to this plugin.
