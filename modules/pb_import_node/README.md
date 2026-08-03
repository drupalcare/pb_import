# PB Import Node

Imports a CSV file into nodes of a content type you name on the form.

Each row becomes one node, with an image, a body and taxonomy terms. The
content type and vocabulary are typed into the form, so the same importer can
fill any content type whose fields follow the `pb_import_node_*` pattern this
module installs.

Unlike its sibling, this module creates **no paragraphs** and does not require
Paragraphs Bundles.

## CSV format

| Column | Meaning |
| --- | --- |
| `csv_image_url` | Image filename, looked up in the folder named on the form. Leave empty for no image. |
| `csv_image_alt` | Alt text. |
| `csv_image_title` | Image title attribute. |
| `csv_node_title` | Node title. |
| `csv_node_tag` | Terms, separated by `\|`. Created if missing. |
| `csv_node_body` | Body HTML, saved as `full_html`. |

`content-with-images.csv` and `content-without-images.csv` in the project root
are working examples.

## Images

Images are **not** downloaded. Place them under the public files directory
first and give the form the folder name. A row with no image is imported
without one.

## Usage

Go to **Content → Paragraphs → PB Import Node**
(`/admin/content/paragraphs/pb-import-node`), name the content type and
vocabulary, upload the CSV, and submit.
