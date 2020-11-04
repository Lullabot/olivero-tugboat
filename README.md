
# Tugboat Drupal Dev Branch Builder (with database and content import) for Olivero

This repository pulls in the latest Drupal `9.1.x` HEAD and then builds three previews:

1. Standard install with database import
   - Pulls  in a gzipped db from Dropbox containing content and enabled modules.
   - Pulls in a zip file of the Drupal's `sites/default/files` directory.
2. Standard install (setting the default theme to Olivero)
3. Minimal install (setting the default theme to Olivero)

The preloaded content preview can be found at [https://lb.cm/olivero](https://lb.cm/olivero).

# Visual diffs

Tugboat will generate visual diffs against the base preview. To do this, you'll need to commit your changes to a new branch, push it, and then tell Tugboat to build through the Tugboat admin UI.

## How to update the database, and files (assumes MacOS)

* The tugboat database and files directory can be placed in [Dropbox](https://www.dropbox.com/work/Lullabot/Front-End%20Development/Olivero/Tugboat%20Files) by a user with the appropriate permissions.
* To generate a database dump:
  * `drush sql-dump > olivero-db.sql`
  * `gzip olivero-db.sql`
  * Then upload the file to the Dropbox folder above.
  * Modify the `PRELOADED_DB_DUMP` [Tugboat environment setting](https://docs.tugboat.qa/setting-up-services/how-to-set-up-services/custom-environment-variables/)
* To generate the files zip file
  * `cd` into `sites/default/files`
  * run `zip path/to/zipfile.zip -r *`
  * Upload the file into the Dropbox folder above.
  * Modify the `PRELOADED_FILES_ZIP` [Tugboat environment setting](https://docs.tugboat.qa/setting-up-services/how-to-set-up-services/custom-environment-variables/)
* To add modules
  * Add new lines similar to `composer require drupal/webform^6` into line 100(ish) at within this repository's `.tugboat/config.yml` file.
  * Ensure that the modules are enabled in the database (we're not importing config).

For more information on Tugboat, visit [https://tugboat.qa/](Tugboat.qa). Tugboat's pretty awesome 😍!
