# Changelog

All notable changes to `pb_import` are recorded here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.0.0] — unreleased

Matches **Paragraphs Bundles 2.x** on Drupal **11.3+ / 12**.

**The importer itself did not need rewriting.** PB 2.x kept every paragraph
bundle machine name and every field machine name that this module writes to —
`slideshow_bundle`, `accordion_bundle`, `tabs_bundle` and their section
bundles, plus `pb_content_title`, `pb_content_*_section`, `pb_content_*_body`
and `pb_content_*_title`. All ten bundles and all twelve fields were checked
against what PB 2.x ships, and a real 30-row CSV import was run against a live
PB 2.x site: 30 rows processed, 0 skipped, and the imported slideshow renders
correctly through PB 2.x's templates.

What did need fixing was this module's own compatibility with the Drupal
versions PB 2.x targets.

### Fixed
- **A row with no image left a broken image reference on every paragraph it
  created.** `ParagraphCreator` copies each CSV column into the entity values
  before the image block runs, so an empty `csv_image_url` arrived as `''` and
  Drupal stored it as an image item with `target_id: 0` — a reference to a file
  that cannot exist. Importing the shipped `para-without-images.csv` produced 30
  such items and 60 "Missing file with ID 0" warnings per run. The image field
  is now cleared before the resolved file is set, matching what the node
  importer has always done. The two CSV-only columns `csv_image_alt` and
  `csv_image_title` are no longer copied into the entity either; they are
  columns, not fields.
- **The module could not run on Drupal 12.**
  `FileSystemInterface::EXISTS_RENAME` was deprecated in Drupal 10.3 and is
  removed in Drupal 12; four uses across the two import forms are now
  `FileExists::Rename`.

### Removed
- **The Paragraphs List moved to Paragraphs Bundles; same address.**
  `/admin/content/paragraphs/paragraphs-list`, the paragraph edit form and the
  delete route are served by `paragraphs_bundles` 2.x now, and the save-then-back
  redirect with them; nothing of them is left in this module (a Kernel test
  keeps it that way). The list has its own permission there, **`access
  paragraphs list`**, replacing `administer site configuration` for the list and
  the undefined `administer paragraphs` for edit. The "PB Import" menu parent now
  opens Register Uploaded Files.

### Changed
- **`pb_import` now depends on `paragraphs_bundles` in `pb_import.info.yml`**,
  not only in composer: a site that has the importer has the list that reviews
  what it imported.
- `core_version_requirement` is now `^11.3 || ^12` on all three modules, and
  `drupal/core` matches it in every `composer.json`. Drupal 10 support ends
  with the 1.0.x branch.
- **`drupal/paragraphs_bundles` is declared in composer for the first time.**
  `pb_import_para` has always depended on it in `info.yml`, but composer never
  knew, so `composer require drupal/pb_import` would not pull it in. It is now
  required at `^2.0@dev`, which also stops composer resolving PB 1.x alongside
  this branch.

  The `@dev` flag is **temporary and must be removed before 2.0.0 is tagged.**
  Paragraphs Bundles 2.0.0 does not exist yet — only its `2.0.x-dev` branch
  release does — so a plain `^2.0` cannot be satisfied under this file's
  `minimum-stability: stable`, and it is what made the first CI run of this
  branch fail at the `composer` job before any other job could start. `@dev` is
  a per-package stability flag, so it relaxes nothing else, and because
  `prefer-stable` is true it will pick 2.0.0 the moment that tag exists.
  Verified both ways against `packages.drupal.org`: `^2.0` reproduces the CI
  error exactly, `^2.0@dev` resolves and locks `dev-2.0.x`.

### Added
- `phpstan.neon.dist`, so the deprecation check that found the Drupal 12
  blocker can be re-run rather than remembered.

## [1.0.x]

For Drupal 10 / 11 and Paragraphs Bundles 1.x. Still maintained for sites that
have not upgraded.
