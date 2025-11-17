<?php

declare(strict_types=1);

namespace Drupal\pb_import_para\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Service to process CSV files for importing paragraphs.
 */
class CSVProcessorPara {

  /**
   * Required CSV headers.
   */
  private const REQUIRED_HEADERS = [
    'csv_image_url',
    'csv_image_alt',
    'csv_image_title',
    'csv_target_title',
    'csv_target_tag',
    'csv_target_body',
  ];

  /**
   * Constructs a CSVProcessorPara object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\pb_import_para\Service\ParagraphCreator $paragraphCreator
   *   The paragraph creator service.
   */
  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly LoggerChannelInterface $logger,
    private readonly ParagraphCreator $paragraphCreator,
  ) {}

  /**
   * Processes the CSV file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   * @param string $parentTitle
   *   The title for the parent paragraph.
   * @param string $parentParagraphType
   *   The type of the parent paragraph.
   * @param string $parentEntityReferenceField
   *   The entity reference field of the parent paragraph.
   * @param string $sectionParagraphType
   *   The type of the section paragraph.
   * @param string $sectionEntityReferenceField
   *   The entity reference field of the section paragraph.
   * @param string $targetBundle
   *   The target bundle.
   * @param string $vocabularyName
   *   The vocabulary name.
   * @param string $imageFolderRelativePath
   *   The folder name for image files.
   * @param string $sectionTitleField
   *   The title field of the section paragraph.
   *
   * @return array
   *   The result of the CSV processing with keys:
   *   - status: 'success' or 'error'
   *   - message: Status message
   *   - processed: Number of rows processed
   *   - skipped: Number of rows skipped
   *   - skipped_rows: Array of skipped row numbers
   */
  public function process(
    FileInterface $file,
    string $parentTitle,
    string $parentParagraphType,
    string $parentEntityReferenceField,
    string $sectionParagraphType,
    string $sectionEntityReferenceField,
    string $targetBundle,
    string $vocabularyName,
    string $imageFolderRelativePath,
    string $sectionTitleField,
  ): array {
    $real_path = $this->fileSystem->realpath($file->getFileUri());

    if ($real_path === FALSE) {
      return $this->errorResult('Failed to resolve file path.');
    }

    $this->logger->info('Processing CSV file at @path', ['@path' => $real_path]);

    $parse_result = $this->parseCsv($real_path);

    if ($parse_result['status'] === 'error') {
      return $parse_result;
    }

    return $this->processRows(
      $parse_result['rows'],
      $parentTitle,
      $parentParagraphType,
      $parentEntityReferenceField,
      $sectionParagraphType,
      $sectionEntityReferenceField,
      $targetBundle,
      $vocabularyName,
      $imageFolderRelativePath,
      $sectionTitleField
    );
  }

  /**
   * Parses CSV file and returns rows.
   *
   * @param string $filePath
   *   The file path.
   *
   * @return array
   *   Array with 'status', 'rows', and optionally 'message'.
   */
  private function parseCsv(string $filePath): array {
    $handle = fopen($filePath, 'r');

    if ($handle === FALSE) {
      return $this->errorResult('Failed to open CSV file at ' . $filePath);
    }

    $header = fgetcsv($handle, 0, ',');

    if ($header === FALSE) {
      fclose($handle);
      return $this->errorResult('Failed to read CSV header.');
    }

    $header = array_map('trim', $header);

    $validation_result = $this->validateHeaders($header);
    if ($validation_result['status'] === 'error') {
      fclose($handle);
      return $validation_result;
    }

    $rows = [];
    $row_number = 1;

    while (($data = fgetcsv($handle, 0, ',')) !== FALSE) {
      $row_number++;

      if (count($data) !== count($header)) {
        $this->logger->warning('Skipping malformed row @row_number', [
          '@row_number' => $row_number,
        ]);
        continue;
      }

      // Only trim - do NOT use html_entity_decode (XSS risk).
      $data = array_map('trim', $data);
      $rows[] = array_combine($header, $data);
    }

    fclose($handle);

    return [
      'status' => 'success',
      'rows' => $rows,
    ];
  }

  /**
   * Processes CSV rows and creates paragraphs.
   *
   * @param array $rows
   *   CSV rows.
   * @param string $parentTitle
   *   Parent title.
   * @param string $parentParagraphType
   *   Parent paragraph type.
   * @param string $parentEntityReferenceField
   *   Parent entity reference field.
   * @param string $sectionParagraphType
   *   Section paragraph type.
   * @param string $sectionEntityReferenceField
   *   Section entity reference field.
   * @param string $targetBundle
   *   Target bundle.
   * @param string $vocabularyName
   *   Vocabulary name.
   * @param string $imageFolderRelativePath
   *   Image folder path.
   * @param string $sectionTitleField
   *   Section title field.
   *
   * @return array
   *   Processing result.
   */
  private function processRows(
    array $rows,
    string $parentTitle,
    string $parentParagraphType,
    string $parentEntityReferenceField,
    string $sectionParagraphType,
    string $sectionEntityReferenceField,
    string $targetBundle,
    string $vocabularyName,
    string $imageFolderRelativePath,
    string $sectionTitleField,
  ): array {
    $processed = 0;
    $skipped = 0;
    $skipped_rows = [];
    $section_paragraphs = [];

    foreach ($rows as $index => $row) {
      $row_number = $index + 2;

      $image_file = $row['csv_image_url'] ?? '';
      $body = $row['csv_target_body'] ?? '';

      if ($image_file === '' && $body === '') {
        $this->logger->warning('Skipping row @row_number: both image and body are empty.', [
          '@row_number' => $row_number,
        ]);
        $skipped++;
        $skipped_rows[] = $row_number;
        continue;
      }

      if ($image_file !== '' && !$this->isImageUrlValid($image_file, $imageFolderRelativePath)) {
        $this->logger->warning('Skipping row @row_number: invalid image URL @url', [
          '@row_number' => $row_number,
          '@url' => $image_file,
        ]);
        $skipped++;
        $skipped_rows[] = $row_number;
        continue;
      }

      $section_paragraph = $this->createSectionParagraph(
        $row,
        $parentTitle,
        $sectionParagraphType,
        $sectionEntityReferenceField,
        $targetBundle,
        $vocabularyName,
        $imageFolderRelativePath,
        $sectionTitleField
      );

      if ($section_paragraph instanceof ParagraphInterface) {
        $section_paragraphs[] = [
          'target_id' => $section_paragraph->id(),
          'target_revision_id' => $section_paragraph->getRevisionId(),
        ];
        $processed++;
      }
      else {
        $this->logger->error('Failed to create section paragraph for row @row_number', [
          '@row_number' => $row_number,
        ]);
        $skipped++;
        $skipped_rows[] = $row_number;
      }
    }

    if (empty($section_paragraphs)) {
      return $this->errorResult(
        'No section paragraphs were created. Ensure at least one row has valid image or body content.',
        $processed,
        $skipped,
        $skipped_rows
      );
    }

    $parent_paragraph = $this->createParentParagraph(
      $parentTitle,
      $parentParagraphType,
      $parentEntityReferenceField,
      $section_paragraphs,
      $imageFolderRelativePath,
      $vocabularyName
    );

    if (!$parent_paragraph instanceof ParagraphInterface) {
      return $this->errorResult(
        'Failed to create parent paragraph.',
        $processed,
        $skipped,
        $skipped_rows
      );
    }

    $this->logger->info('Created parent paragraph "@title" with @count sections.', [
      '@title' => $parentTitle,
      '@count' => count($section_paragraphs),
    ]);

    return [
      'status' => 'success',
      'message' => 'CSV processed successfully',
      'processed' => $processed,
      'skipped' => $skipped,
      'skipped_rows' => $skipped_rows,
    ];
  }

  /**
   * Creates a section paragraph.
   *
   * @param array $row
   *   CSV row data.
   * @param string $parentTitle
   *   Parent title.
   * @param string $sectionParagraphType
   *   Section type.
   * @param string $sectionEntityReferenceField
   *   Section reference field.
   * @param string $targetBundle
   *   Target bundle.
   * @param string $vocabularyName
   *   Vocabulary name.
   * @param string $imageFolderRelativePath
   *   Image folder path.
   * @param string $sectionTitleField
   *   Section title field.
   *
   * @return \Drupal\paragraphs\ParagraphInterface|null
   *   Created paragraph or NULL.
   */
  private function createSectionParagraph(
    array $row,
    string $parentTitle,
    string $sectionParagraphType,
    string $sectionEntityReferenceField,
    string $targetBundle,
    string $vocabularyName,
    string $imageFolderRelativePath,
    string $sectionTitleField,
  ): ?ParagraphInterface {
    $image_file = $row['csv_image_url'] ?? '';
    $image_alt = $image_file === '' ? '' : ($row['csv_image_alt'] ?? '');
    $image_title = $image_file === '' ? '' : ($row['csv_image_title'] ?? '');

    $child_data = [
      'pb_target_image' => $image_file,
      'csv_image_alt' => $image_alt,
      'csv_image_title' => $image_title,
      'pb_target_tag' => $row['csv_target_tag'] ?? '',
      'pb_target_body' => $row['csv_target_body'] ?? '',
    ];

    $child_paragraph = $this->paragraphCreator->createParagraph(
      $child_data,
      $imageFolderRelativePath,
      $targetBundle,
      $vocabularyName
    );

    if (!$child_paragraph instanceof ParagraphInterface) {
      return NULL;
    }

    $section_data = [
      'pb_content_title' => $parentTitle,
      $sectionTitleField => $row['csv_target_title'] ?? '',
      $sectionEntityReferenceField => [
        [
          'target_id' => $child_paragraph->id(),
          'target_revision_id' => $child_paragraph->getRevisionId(),
        ],
      ],
    ];

    return $this->paragraphCreator->createParagraph(
      $section_data,
      $imageFolderRelativePath,
      $sectionParagraphType,
      $vocabularyName
    );
  }

  /**
   * Creates parent paragraph.
   *
   * @param string $parentTitle
   *   Parent title.
   * @param string $parentParagraphType
   *   Parent type.
   * @param string $parentEntityReferenceField
   *   Parent reference field.
   * @param array $sectionParagraphs
   *   Section paragraphs.
   * @param string $imageFolderRelativePath
   *   Image folder path.
   * @param string $vocabularyName
   *   Vocabulary name.
   *
   * @return \Drupal\paragraphs\ParagraphInterface|null
   *   Created paragraph or NULL.
   */
  private function createParentParagraph(
    string $parentTitle,
    string $parentParagraphType,
    string $parentEntityReferenceField,
    array $sectionParagraphs,
    string $imageFolderRelativePath,
    string $vocabularyName,
  ): ?ParagraphInterface {
    $parent_data = [
      'pb_content_title' => $parentTitle,
      $parentEntityReferenceField => $sectionParagraphs,
    ];

    return $this->paragraphCreator->createParagraph(
      $parent_data,
      $imageFolderRelativePath,
      $parentParagraphType,
      $vocabularyName,
      TRUE
    );
  }

  /**
   * Validates image URL.
   *
   * @param string $url
   *   Image filename.
   * @param string $folder
   *   Folder path.
   *
   * @return bool
   *   TRUE if valid.
   */
  private function isImageUrlValid(string $url, string $folder): bool {
    $folder = rtrim($folder, '/') . '/';
    $file_uri = 'public://' . $folder . $url;
    $file_path = $this->fileSystem->realpath($file_uri);

    if ($file_path === FALSE || !file_exists($file_path)) {
      $this->logger->warning('File does not exist: @file_uri', [
        '@file_uri' => $file_uri,
      ]);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Validates CSV headers.
   *
   * @param array $header
   *   CSV headers.
   *
   * @return array
   *   Validation result.
   */
  private function validateHeaders(array $header): array {
    $missing_headers = array_diff(self::REQUIRED_HEADERS, $header);
    $extra_headers = array_diff($header, self::REQUIRED_HEADERS);

    if (empty($missing_headers) && empty($extra_headers)) {
      return ['status' => 'success'];
    }

    $error_message = 'CSV header validation failed. ';
    if (!empty($missing_headers)) {
      $error_message .= 'Missing headers: ' . implode(', ', $missing_headers) . '. ';
    }
    if (!empty($extra_headers)) {
      $error_message .= 'Extra headers: ' . implode(', ', $extra_headers) . '.';
    }

    $this->logger->error($error_message);
    return ['status' => 'error', 'message' => $error_message];
  }

  /**
   * Creates error result array.
   *
   * @param string $message
   *   Error message.
   * @param int $processed
   *   Processed count.
   * @param int $skipped
   *   Skipped count.
   * @param array $skippedRows
   *   Skipped row numbers.
   *
   * @return array
   *   Error result.
   */
  private function errorResult(
    string $message,
    int $processed = 0,
    int $skipped = 0,
    array $skippedRows = [],
  ): array {
    return [
      'status' => 'error',
      'message' => $message,
      'processed' => $processed,
      'skipped' => $skipped,
      'skipped_rows' => $skippedRows,
    ];
  }

}
