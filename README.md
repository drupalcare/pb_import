# PB Import

[![Drupal 11](https://img.shields.io/badge/Drupal-11.3+-blue.svg)](https://www.drupal.org/project/pb_import)
[![Drupal 12](https://img.shields.io/badge/Drupal-12-blue.svg)](https://www.drupal.org/project/pb_import)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Submodules](#submodules)
  - [PB Import Node](#pb-import-node)
  - [PB Import Paragraphs](#pb-import-paragraphs)
- [Configuration](#configuration)
- [CSV File Format](#csv-file-format)
- [Usage Examples](#usage-examples)
- [Troubleshooting](#troubleshooting)
- [API Documentation](#api-documentation)
- [Maintainers](#maintainers)
- [Contributing](#contributing)
- [License](#license)

## Overview

**PB Import** is a comprehensive Drupal module designed to streamline bulk
content operations through CSV file imports. It provides enterprise-grade
tools for importing and managing both nodes and paragraphs, with built-in
validation, error handling, and support for complex paragraph bundles.

This module is ideal for:
- Migrating content from legacy systems
- Bulk content creation and updates
- Automated content workflows
- Creating structured paragraph-based layouts

## Features

### Core Features
- **CSV-based Import**: Import nodes and paragraphs from well-structured CSV
  files
- **File Management**: Automatic file registration and management with
  validation
- **Taxonomy Integration**: Automatic term creation and assignment
- **Image Handling**: Support for image fields with alt text and titles
- **Security**: Path traversal prevention and input sanitization
- **Error Handling**: Comprehensive logging and user-friendly error messages
- **Type Safety**: Full PHP 8+ strict typing and Drupal 11.3+/12 compliance

### Paragraph Types Supported
- **Slideshow**: Image carousels with captions
- **Accordion**: Collapsible content sections
- **Tabs**: Tabbed content containers

## Requirements

### Drupal Core
- Drupal 11.3 or higher, or Drupal 12

### Paragraphs Bundles
- **PB Import 2.x requires Paragraphs Bundles 2.x.** The paragraph
  bundles and field names it writes to are unchanged between PB 1.x and
  2.x, so the importer itself needed no rewrite — but 2.x is built and
  tested against PB 2.x and Drupal 11.3+/12.
- Staying on Drupal 10, or on Paragraphs Bundles 1.x? Use the **1.0.x**
  branch of this module.

### Required Modules
- **block**: Core content blocks
- **field**: Field API
- **file**: File management
- **node**: Content types and nodes
- **taxonomy**: Taxonomy terms and vocabularies
- **entity_reference_revisions**: Paragraph reference support
- **paragraphs**: Paragraph entities

### Recommended Modules
For paragraph imports, one or more of the following:
- **paragraph_bundle_accordion**: Accordion paragraph type
- **paragraph_bundle_slideshow**: Slideshow paragraph type
- **paragraph_bundle_tabs**: Tabs paragraph type

### PHP Requirements
- PHP 8.1 or higher
- Extensions: `mbstring`, `gd` or `imagick` (for image processing)

## Installation

### Using Composer (Recommended)

```bash
composer require drupal/pb_import
```

### Manual Installation

1. Download the module from 
   [Drupal.org](https://www.drupal.org/project/pb_import)
2. Extract to `modules/contrib/pb_import` or `modules/custom/pb_import`
3. Enable the module:

```bash
# Enable all submodules
drush en pb_import pb_import_node pb_import_para -y

# Or enable individually as needed
drush en pb_import -y
drush en pb_import_node -y    # For node imports
drush en pb_import_para -y    # For paragraph imports
```

### Post-Installation

1. Clear all caches:
   ```bash
   drush cr
   ```

2. Verify services are registered:
   ```bash
   drush ev "print_r(\Drupal::hasService('pb_import.file_manager'));"
   ```

## Submodules

### PB Import Node

Import and manage content nodes from CSV files with support for images,
taxonomy terms, and rich text fields.

#### Features
- CSV-based node creation
- Image field population with alt text and titles
- Automatic taxonomy term creation and assignment
- Configurable content types
- Custom vocabulary support
- Bulk operations support

#### CSV Structure for Nodes
```csv
csv_image_url,csv_node_title,csv_image_alt,csv_image_title,csv_node_tag,csv_node_body
image.jpg,Node Title,Alt text,Title text,Tag1|Tag2,<p>Body content</p>
```

#### Components

**PBImportNodeForm**
- Handles CSV upload and validation
- Configures content type and vocabulary
- Manages file upload security

**CSVProcessorNode**
- Validates CSV structure
- Processes rows with error tracking
- Handles malformed data gracefully

**NodeCreator**
- Creates node entities
- Manages field population
- Handles taxonomy term creation

#### Usage Paths
- Import interface: `/admin/content/paragraphs/pb-import-node`
- Node listing: `/admin/content`

---

### PB Import Paragraphs

Create and manage complex paragraph structures including slideshows,
accordions, and tabs from CSV files.

#### Features
- Multi-level paragraph creation (parent/section/target)
- Support for three paragraph bundle types
- Flexible image handling
- HTML content support
- Taxonomy integration per paragraph type

#### Paragraph Type Dependencies

| Type      | Required Module                    | Vocabulary        |
|-----------|-----------------------------------|-------------------|
| Slideshow | `paragraph_bundle_slideshow`      | `pb_slideshow_tag`|
| Accordion | `paragraph_bundle_accordion`      | `pb_accordion_tag`|
| Tabs      | `paragraph_bundle_tabs`           | `pb_tabs_tag`     |

#### CSV Structure for Paragraphs
```csv
csv_image_url,csv_image_alt,csv_image_title,csv_target_title,csv_target_tag,csv_target_body
image.jpg,Alt text,Title,Section Title,Tag1|Tag2,<p>Content</p>
```

#### Components

**PBImportParaForm**
- Dynamic form based on available paragraph bundles
- AJAX-powered type selection
- Module dependency checking

**CSVProcessorPara**
- Hierarchical paragraph creation
- Parent/section/target relationship management
- Advanced validation logic

**ParagraphCreator**
- Creates paragraph entities
- Manages entity references
- Handles revision tracking

#### Usage Paths
- Import interface: `/admin/content/paragraphs/pb-import-para`
- Paragraph management: `/admin/content/paragraphs/paragraphs-list`

## Configuration

### Initial Setup

1. **Register Uploaded Files**
   - Navigate to: `/admin/content/paragraphs/register-uploaded-files`
   - Enter relative path to your images (e.g., `gallery/2024`)
   - Click "Register Files"
   - Files are now available for CSV import

2. **Configure Content Types** (for node imports)
   - Use default `pb_import_node` content type, or
   - Create custom content type with matching fields:
     - `pb_import_node_image` (Image)
     - `pb_import_node_body` (Text, long, formatted)
     - `pb_import_node_tag` (Entity reference to taxonomy)

3. **Configure Vocabularies**
   - Use default vocabularies, or
   - Specify custom vocabulary machine name during import

### File Organization

```
sites/default/files/
├── gallery/
│   ├── 2024/
│   │   ├── image1.jpg
│   │   ├── image2.png
│   │   └── ...
│   └── archive/
│       └── old-images/
└── temporary/
    └── (CSV uploads)
```

### Permissions

Required permissions for importing:
- `administer site configuration`
- `administer content`
- `administer paragraphs`
- `create [content_type] content`
- `edit own [content_type] content`

## CSV File Format

### Best Practices

1. **Encoding**: UTF-8 without BOM
2. **Line Endings**: Unix (LF) or Windows (CRLF)
3. **Delimiter**: Comma (`,`)
4. **Quotes**: Double quotes for fields containing commas
5. **Headers**: Must match exactly (case-sensitive)

### Field Guidelines

**Images**
- File names only (no paths): `image.jpg` ✅
- Supported formats: jpg, jpeg, png, gif, webp
- Must exist in registered folder

**Tags**
- Multiple tags: Use pipe separator `Tag1|Tag2|Tag3`
- Auto-created if not existing
- Case-sensitive

**Body Content**
- HTML allowed (will use `full_html` format)
- Escape quotes: `"This is a ""quoted"" word"`
- Long content: Wrap in quotes

### Sample CSV Files

#### Node Import (content-with-images.csv)
```csv
csv_image_url,csv_node_title,csv_image_alt,csv_image_title,csv_node_tag,csv_node_body
hero.jpg,Welcome Page,Hero image,Main hero,Homepage|Featured,"<p>Welcome to our site!</p>"
,About Us,,,About|Company,"<p>Learn about our company.</p>"
```

#### Paragraph Import (para-with-images.csv)
```csv
csv_image_url,csv_image_alt,csv_image_title,csv_target_title,csv_target_tag,csv_target_body
slide1.jpg,First slide,Slide 1,Introduction,Featured,"<p>Intro text</p>"
slide2.jpg,Second slide,Slide 2,Features,Featured,"<p>Features text</p>"
```

## Usage Examples

### Example 1: Import Nodes with Images

1. Prepare your CSV file with image references
2. Upload images to `sites/default/files/products/2024/`
3. Register files:
   - Go to `/admin/content/paragraphs/register-uploaded-files`
   - Enter: `products/2024`
   - Click "Register Files"
4. Import nodes:
   - Go to `/admin/content/paragraphs/pb-import-node`
   - Upload CSV file
   - Image Folder: `products/2024`
   - Content Type: `pb_import_node`
   - Vocabulary: `pb_import_node`
   - Click "Import"

### Example 2: Create Slideshow Paragraphs

1. Enable required module:
   ```bash
   drush en paragraph_bundle_slideshow -y
   ```

2. Prepare CSV with slideshow content

3. Register images (if any):
   ```bash
   # Images in sites/default/files/slideshow/
   ```

4. Import paragraphs:
   - Go to `/admin/content/paragraphs/pb-import-para`
   - Select: "Slideshow"
   - Upload CSV
   - Image Folder: `slideshow`
   - Parent Title: `Homepage Slideshow`
   - Click "Import"

5. Attach to node:
   - Edit your content
   - Add the created paragraph to paragraph reference field

### Example 3: Import Without Images

CSV with body content only:
```csv
csv_image_url,csv_node_title,csv_image_alt,csv_image_title,csv_node_tag,csv_node_body
,FAQ 1,,,FAQ,"<p>Question and answer here.</p>"
,FAQ 2,,,FAQ,"<p>Another Q&A here.</p>"
```

Leave image folder blank during import.

## Troubleshooting

### Common Issues

**Issue**: "File not found" errors during import
- **Solution**: Ensure files are registered using the Register Uploaded Files
  form before importing
- **Check**: File names in CSV match exactly (case-sensitive)
- **Verify**: Files exist at `sites/default/files/[your-path]/`

**Issue**: "No paragraph bundle modules are enabled"
- **Solution**: Enable required paragraph bundle module:
  ```bash
  drush en paragraph_bundle_slideshow -y
  drush cr
  ```

**Issue**: CSV headers validation fails
- **Solution**: Verify CSV headers match exactly:
  - Nodes: `csv_image_url,csv_node_title,csv_image_alt,csv_image_title,
    csv_node_tag,csv_node_body`
  - Paragraphs: `csv_image_url,csv_image_alt,csv_image_title,
    csv_target_title,csv_target_tag,csv_target_body`

**Issue**: Path traversal error with image folder
- **Solution**: Use only alphanumeric characters, hyphens, underscores, and
  forward slashes
- **Invalid**: `../../../etc/passwd`
- **Valid**: `gallery/2024/products`

**Issue**: Memory limit exceeded on large imports
- **Solution**: Increase PHP memory limit:
  ```php
  // In settings.php
  ini_set('memory_limit', '512M');
  ```

**Issue**: Import succeeds but images don't display
- **Solution**: Check file permissions:
  ```bash
  chmod 755 sites/default/files
  find sites/default/files -type d -exec chmod 755 {} \;
  find sites/default/files -type f -exec chmod 644 {} \;
  ```

### Debug Mode

Enable detailed logging:

```php
// In settings.php
$config['system.logging']['error_level'] = 'verbose';
```

Check logs:
```bash
drush watchdog:show --type=pb_import_node
drush watchdog:show --type=pb_import_para
```

## API Documentation

### Services

#### FileManager
```php
$file_manager = \Drupal::service('pb_import.file_manager');
$file_id = $file_manager->getFileId('gallery/2024', 'image.jpg');
```

#### TermManager
```php
$term_manager = \Drupal::service('pb_import.term_manager');
$term = $term_manager->getOrCreateTerm('New Tag', 'tags');
```

#### CSVProcessorNode
```php
$processor = \Drupal::service('pb_import_node.csv_processor.node');
$result = $processor->process($file, 'folder', 'content_type', 'vocab');
```

### Hooks

This module does not currently provide hooks, but you can alter forms using
standard Drupal form alter hooks:

```php
/**
 * Implements hook_form_FORM_ID_alter().
 */
function mymodule_form_pb_import_node_form_alter(&$form, $form_state, $form_id) {
  // Customize the import form
  $form['content_type']['#default_value'] = 'article';
}
```

### Events

Subscribe to entity creation events to react to imports:

```php
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ImportSubscriber implements EventSubscriberInterface {
  public function onNodeInsert(NodeInterface $node) {
    if ($node->bundle() === 'pb_import_node') {
      // React to imported nodes
    }
  }
}
```

## Maintainers

### Current Maintainers
- **Alaa Haddad** - [flashwebcenter](https://www.drupal.org/u/flashwebcenter)
  - [Website](https://www.alaahaddad.com)
  - [Company](https://www.flashwebcenter.com)

### Supporting Organizations
- [Flash Web Center](https://www.flashwebcenter.com) - Development and
  maintenance

## Contributing

We welcome contributions! Please follow these guidelines:

### Reporting Issues
- Use the [issue queue](https://www.drupal.org/project/issues/pb_import)
- Include steps to reproduce
- Attach sample CSV files (sanitized)
- Specify Drupal and PHP versions

### Submitting Patches
1. Create an issue first
2. Follow [Drupal coding standards](https://www.drupal.org/docs/develop/standards)
3. Include tests if possible
4. Update documentation

### Code Standards
- PHP 8.1+ with strict typing
- PSR-4 autoloading
- Drupal 11.3+/12 API compliance
- PHPStan level 6 or higher

### Testing
```bash
# Run PHP CodeSniffer
phpcs --standard=Drupal,DrupalPractice modules/custom/pb_import

# Run PHPStan
phpstan analyse modules/custom/pb_import
```

## License

This project is licensed under the **GNU General Public License v2.0 or
later**.

- [Full License Text](https://www.gnu.org/licenses/gpl-2.0.html)
- [Drupal Licensing FAQ](https://www.drupal.org/about/licensing)

```
Copyright (C) 2024 Alaa Haddad

This program is free software; you can redistribute it and/or modify it
under the terms of the GNU General Public License as published by the Free
Software Foundation; either version 2 of the License, or (at your option)
any later version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
more details.
```

---

**Project Links**
- [Drupal.org Project Page](https://www.drupal.org/project/pb_import)
- [Documentation](https://www.drupal.org/docs/contributed-modules/pb-import)
- [Issue Queue](https://www.drupal.org/project/issues/pb_import)
- [Git Repository](https://git.drupalcode.org/project/pb_import)

**Version**: 2.0.x-dev  
**Last Updated**: 2026-08-03  
**Drupal Compatibility**: 11.3+, 12.x  
**Paragraphs Bundles Compatibility**: 2.x
