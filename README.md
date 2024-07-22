# PB Import

**PB Import** is a Drupal module designed to streamline the management and
processing of paragraphs and nodes within your Drupal site. This module
provides a suite of tools to handle the import and management of content from
CSV files, ensuring a smooth content management experience for administrators.

## Features

- Import content nodes and paragraphs from CSV files.
- Manage different paragraph types: slideshow, accordion, and tabs.
- Create and manage nodes with specific content types and vocabularies.
- Seamless integration with the paragraph and node management workflows.

## Submodules

### PB Import Node

This submodule facilitates the import and management of content nodes from CSV
files. It provides an efficient way to create and manage nodes, particularly
useful for bulk content operations.

**Features:**

- Import content nodes from CSV files.
- Specify image folder, content type, and vocabulary name.
- Validate and process CSV data to create nodes.

**Components:**

1. **PBImportNodeForm**
   - Handles the form for uploading CSV files and specifying import settings.
2. **CSVProcessorNode**
   - Processes the uploaded CSV files and handles node creation.
3. **NodeCreator**
   - Handles the creation of nodes based on the processed CSV data.

### PB Import Paragraphs

This submodule facilitates the creation and management of paragraphs within
Drupal. It supports creating paragraphs of different types—slideshow,
accordion, and tabs—from CSV files.

**Features:**

- Import paragraphs from CSV files.
- Create and manage different paragraph types: slideshow, accordion, tabs.
- Specify image folder, parent title, and paragraph type.

**Components:**

1. **PBImportParaForm**
   - Handles the form for uploading CSV files and specifying settings for
     paragraph creation.
2. **CSVProcessorPara**
   - Processes the uploaded CSV files and handles paragraph creation.
3. **ParagraphCreator**
   - Handles the creation of paragraphs based on the processed CSV data.

## Installation

1. Download and extract the PB Import module into your Drupal installation's
   `modules` directory.
2. Enable the module and its submodules through the Drupal admin interface or
   using Drush:
    ```sh
    drush en pb_import pb_import_node pb_import_paragraphs -y
    ```

## Configuration

1. Navigate to the PB Import settings page:
    - For nodes: `/admin/content/paragraphs/paragraphs-list`
    - For paragraphs: `/admin/content/paragraphs/register-uploaded-files`
2. Upload the appropriate CSV file and specify the required settings such as
   image folder, content type, vocabulary name, and paragraph type.
3. Submit the form to start the import process.

## Usage

1. **PB Import Node:**
   - Upload a CSV file with fields such as image URL, node title, image alt
     text, image title, node tags, and node body.
   - Specify the image folder, content type, and vocabulary name.
   - The nodes will be created and managed based on the CSV data.

2. **PB Import Paragraphs:**
   - Upload a CSV file with fields such as image URL, image alt text, image
     title, target title, target tag, and target body.
   - Specify the image folder, parent title, and paragraph type (slideshow,
     accordion, tabs).
   - The paragraphs will be created and managed based on the CSV data.

## Maintainers

- [Alaa Haddad](https://www.drupal.org/u/flashwebcenter)

## License

This project is licensed under the [GNU General Public License, version 2 or
later](http://www.gnu.org/licenses/gpl-2.0.html).
