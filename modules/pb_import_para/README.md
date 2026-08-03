# PB Import Paragraph

Imports a CSV file into Paragraphs Bundles paragraph trees.

## What it builds

One import creates a three-level tree:

1. a **parent** paragraph — `slideshow_bundle`, `accordion_bundle` or
   `tabs_bundle`, holding the title you type into the form;
2. one **section** paragraph per CSV row — `slideshow_section_bundle`,
   `accordion_section_bundle` or `tab_section_bundle`, titled from the row;
3. one **target** paragraph per section — `pb_target_bundle`, carrying the
   row's body, its image and its taxonomy terms.

Requires **Paragraphs Bundles 2.x**. The bundle and field names it writes to
are unchanged from 1.x, so a site upgrading from PB 1.x keeps every paragraph
this module has ever created.

## CSV format

Columns, in any order:

| Column | Meaning |
| --- | --- |
| `csv_image_url` | Image filename, looked up in the folder named on the form. Leave empty for no image. |
| `csv_image_alt` | Alt text. Ignored when there is no image. |
| `csv_image_title` | Image title attribute. Ignored when there is no image. |
| `csv_target_title` | Section title. |
| `csv_target_tag` | Terms, separated by `\|`. Created if missing. |
| `csv_target_body` | Body HTML, saved as `full_html`. |

`para-with-images.csv` and `para-without-images.csv` in the project root are
working examples of both shapes.

## Images

Images are **not** downloaded. Put the files in a folder under the public
files directory first — `sites/default/files/images`, say — then give the
form that folder name. Rows whose `csv_image_url` is empty get no image field
at all.

## Usage

Go to **Content → Paragraphs → PB Import Paragraph**
(`/admin/content/paragraphs/pb-import-para`), choose the paragraph type,
upload the CSV, name the image folder, and submit.
