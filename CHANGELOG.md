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
- **The paragraphs list and the delete form returned HTTP 500 on any site with
  assertions enabled.** Two `use` statements named classes that do not exist —
  `Drupal\paragraphs\ParagraphStorageInterface` (the Paragraphs module ships
  `ParagraphStorageSchema`, not a storage interface) and
  `Drupal\paragraphs_type\ParagraphsTypeInterface` (the real one is
  `Drupal\paragraphs\ParagraphsTypeInterface`). `instanceof` against an
  undefined class is simply `FALSE`, so each `assert()` beneath them threw an
  `AssertionError`. Production hides this because `zend.assertions=-1` compiles
  asserts out; every development environment hit it.
- **The module could not run on Drupal 12.**
  `FileSystemInterface::EXISTS_RENAME` was deprecated in Drupal 10.3 and is
  removed in Drupal 12; four uses across the two import forms are now
  `FileExists::Rename`.

### Changed
- `core_version_requirement` is now `^11.3 || ^12` on all three modules, and
  `drupal/core` matches it in every `composer.json`. Drupal 10 support ends
  with the 1.0.x branch.
- **`drupal/paragraphs_bundles` is declared in composer for the first time.**
  `pb_import_para` has always depended on it in `info.yml`, but composer never
  knew, so `composer require drupal/pb_import` would not pull it in. It is now
  required at `^2.0`, which also stops composer resolving PB 1.x alongside this
  branch.

### Added
- `phpstan.neon.dist`, so the deprecation check that found the Drupal 12
  blocker can be re-run rather than remembered.

## [1.0.x]

For Drupal 10 / 11 and Paragraphs Bundles 1.x. Still maintained for sites that
have not upgraded.
