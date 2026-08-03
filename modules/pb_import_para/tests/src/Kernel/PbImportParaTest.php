<?php

declare(strict_types=1);

namespace Drupal\Tests\pb_import_para\Kernel;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The paragraph importer builds the tree it promises, for every bundle.
 *
 * The module had no tests at all. These cover what a CSV import is actually
 * for — a parent, one section per row, one target per section, with body,
 * terms and image where the row has them — and the case that had no coverage
 * and was silently broken: a row with no image must produce no image item,
 * not a reference to file 0.
 */
#[Group('pb_import')]
class PbImportParaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'image',
    'link',
    'options',
    'taxonomy',
    'node',
    'views',
    'media',
    'media_library',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    'paragraph_bundle_slideshow',
    'paragraph_bundle_accordion',
    'paragraph_bundle_tabs',
    'pb_import',
    'pb_import_para',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'filter', 'field', 'node', 'taxonomy']);

    // `field.field.paragraph.pb_target_bundle.pb_target_body` declares a config
    // dependency on `filter.format.full_html`, and the importer writes bodies
    // in that format. The standard profile ships it; the filter module alone
    // ships only `plain_text`, so without this the optional config installs
    // everything except the body field and the failure reads as "field
    // pb_target_body is unknown".
    FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 1,
    ])->save();

    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * The three configurations the import form offers.
   *
   * @return array<string, array{string, string, string, string, string, string}>
   *   Parent bundle, parent field, section bundle, section field, title field
   *   and vocabulary, exactly as PBImportParaForm declares them.
   */
  public static function configProvider(): array {
    return [
      'slideshow' => [
        'slideshow_bundle',
        'pb_content_slideshow_section',
        'slideshow_section_bundle',
        'pb_content_slideshow_body',
        'pb_content_slideshow_title',
        'pb_slideshow_tag',
      ],
      'accordion' => [
        'accordion_bundle',
        'pb_content_accordion_section',
        'accordion_section_bundle',
        'pb_content_accordion_body',
        'pb_content_accordion_title',
        'pb_accordion_tag',
      ],
      'tabs' => [
        'tabs_bundle',
        'pb_content_tab_section',
        'tab_section_bundle',
        'pb_content_tab_body',
        'pb_content_tab_name',
        'pb_tabs_tag',
      ],
    ];
  }

  /**
   * Writes a two-row CSV and returns it as a managed file.
   *
   * @param bool $withImages
   *   Whether the rows name an image.
   *
   * @return \Drupal\file\FileInterface
   *   The CSV file entity.
   */
  private function csv(bool $withImages): object {
    $image = $withImages ? 'test.png' : '';
    $rows = [
      'csv_image_url,csv_image_alt,csv_image_title,csv_target_title,csv_target_tag,csv_target_body',
      $image . ',Alt one,Title one,First section,Alpha|Beta,"<p>Body one</p>"',
      $image . ',Alt two,Title two,Second section,Beta,"<p>Body two</p>"',
    ];

    return $this->container->get('file.repository')->writeData(
      implode("\n", $rows) . "\n",
      'public://import-' . ($withImages ? 'with' : 'without') . '-images.csv',
      FileExists::Replace,
    );
  }

  /**
   * Every configuration builds parent, sections and targets from the CSV.
   */
  #[DataProvider('configProvider')]
  public function testImportBuildsTheParagraphTree(
    string $parentBundle,
    string $parentField,
    string $sectionBundle,
    string $sectionField,
    string $titleField,
    string $vocabulary,
  ): void {
    $result = $this->container->get('pb_import_para.csv_processor.paragraph')->process(
      $this->csv(FALSE),
      'Imported ' . $parentBundle,
      $parentBundle,
      $parentField,
      $sectionBundle,
      $sectionField,
      'pb_target_bundle',
      $vocabulary,
      '',
      $titleField,
    );

    $this->assertSame('success', $result['status']);
    $this->assertSame(2, $result['processed']);
    $this->assertSame(0, $result['skipped']);

    $parents = $this->paragraphsOfType($parentBundle);
    $this->assertCount(1, $parents, 'One parent paragraph per import.');

    $parent = reset($parents);
    $this->assertSame('Imported ' . $parentBundle, $parent->get('pb_content_title')->value);
    $this->assertSame(2, $parent->get($parentField)->count(), 'One section per CSV row.');

    $section = $parent->get($parentField)->first()->entity;
    $this->assertSame($sectionBundle, $section->bundle());
    $this->assertSame('First section', $section->get($titleField)->value);

    $target = $section->get($sectionField)->first()->entity;
    $this->assertSame('pb_target_bundle', $target->bundle());
    $this->assertStringContainsString('Body one', (string) $target->get('pb_target_body')->value);
    $this->assertSame(2, $target->get('pb_target_tag')->count(), 'Both terms of the first row.');
  }

  /**
   * A row without an image gets no image item, rather than a broken one.
   *
   * An empty CSV column used to reach the entity as `''`, which Drupal stores
   * as an image item with `target_id: 0` — a reference to a file that cannot
   * exist, one per row, plus a warning apiece.
   */
  public function testRowWithoutAnImageStoresNoImage(): void {
    $this->container->get('pb_import_para.csv_processor.paragraph')->process(
      $this->csv(FALSE),
      'No images',
      'slideshow_bundle',
      'pb_content_slideshow_section',
      'slideshow_section_bundle',
      'pb_content_slideshow_body',
      'pb_target_bundle',
      'pb_slideshow_tag',
      '',
      'pb_content_slideshow_title',
    );

    $targets = $this->paragraphsOfType('pb_target_bundle');
    $this->assertNotEmpty($targets);
    foreach ($targets as $target) {
      $this->assertSame(
        [],
        $target->get('pb_target_image')->getValue(),
        'A row with no image leaves the image field empty.',
      );
    }
  }

  /**
   * A row naming a file that exists gets that file, with alt and title.
   */
  public function testRowWithAnImageStoresTheFile(): void {
    $fs = $this->container->get('file_system');
    $dir = 'public://images';
    $fs->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
    // A one-pixel PNG is enough: the importer resolves by URI, not by content.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $this->container->get('file.repository')->writeData($png, $dir . '/test.png', FileExists::Replace);

    $this->container->get('pb_import_para.csv_processor.paragraph')->process(
      $this->csv(TRUE),
      'With images',
      'slideshow_bundle',
      'pb_content_slideshow_section',
      'slideshow_section_bundle',
      'pb_content_slideshow_body',
      'pb_target_bundle',
      'pb_slideshow_tag',
      'images',
      'pb_content_slideshow_title',
    );

    $targets = $this->paragraphsOfType('pb_target_bundle');
    $this->assertNotEmpty($targets);
    $target = reset($targets);
    $value = $target->get('pb_target_image')->getValue();
    $this->assertNotEmpty($value, 'The image field is populated.');
    $this->assertNotEmpty($value[0]['target_id']);
    $this->assertSame('Alt one', $value[0]['alt']);
    $this->assertSame('Title one', $value[0]['title']);
  }

  /**
   * CSV-only columns never reach the entity as field values.
   */
  public function testCsvHelperColumnsAreNotStored(): void {
    $this->container->get('pb_import_para.csv_processor.paragraph')->process(
      $this->csv(FALSE),
      'Helper columns',
      'slideshow_bundle',
      'pb_content_slideshow_section',
      'slideshow_section_bundle',
      'pb_content_slideshow_body',
      'pb_target_bundle',
      'pb_slideshow_tag',
      '',
      'pb_content_slideshow_title',
    );

    $target = reset($this->paragraphsOfType('pb_target_bundle'));
    foreach (['csv_image_alt', 'csv_image_title'] as $column) {
      $this->assertFalse($target->hasField($column), "$column is a CSV column, not a field.");
    }
  }

  /**
   * Loads every paragraph of one bundle.
   *
   * @param string $bundle
   *   Paragraph bundle.
   *
   * @return array<int, \Drupal\paragraphs\ParagraphInterface>
   *   The paragraphs.
   */
  private function paragraphsOfType(string $bundle): array {
    $ids = $this->container->get('entity_type.manager')
      ->getStorage('paragraph')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->execute();

    return Paragraph::loadMultiple($ids);
  }

}
