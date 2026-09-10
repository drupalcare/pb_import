<?php

declare(strict_types=1);

namespace Drupal\pb_import_node\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;

/**
 * Service to process CSV files for importing nodes.
 */
class CSVProcessorNode {

  /**
   * Required CSV headers.
   */
  private const REQUIRED_HEADERS = [
    'csv_image_url',
    'csv_node_title',
    'csv_image_alt',
    'csv_image_title',
    'csv_node_tag',
    'csv_node_body',
  ];

  /**
   * Constructs a CSVProcessorNode object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\pb_import_node\Service\NodeCreator $creator
   *   The node creator service.
   */
  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly LoggerChannelInterface $logger,
    private readonly NodeCreator $creator,
  ) {}

  /**
   * Processes the CSV file.
   *
   * @param \Drupal\file\FileInterface $csvFile
   *   The CSV file entity.
   * @param string $folderName
   *   The folder name for image files.
   * @param string $contentType
   *   The content type.
   * @param string $vocabularyName
   *   The vocabulary name.
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
    FileInterface $csvFile,
    string $folderName,
    string $contentType,
    string $vocabularyName,
  ): array {
    $real_path = $this->fileSystem->realpath($csvFile->getFileUri());

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
      $folderName,
      $contentType,
      $vocabularyName
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

    // PHP 8.4 deprecates calling fgetcsv() without $escape, because the default
    // is changing. Passing '' (no escape character) rather than the historic
    // '\\' is deliberate: '' is RFC 4180, which is what a CSV export from
    // Excel, Sheets or a database actually produces, and it is the value PHP is
    // moving to. The old default also CORRUPTS ordinary data -- a field ending
    // in a backslash swallows the closing quote and merges the next column into
    // it, which for an import module means silently wrong content rather than
    // an error. None of this module's shipped CSVs contain a backslash, so the
    // sample data parses identically either way.
    $header = fgetcsv($handle, 0, ',', '"', '');

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

    while (($data = fgetcsv($handle, 0, ',', '"', '')) !== FALSE) {
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
   * Processes CSV rows and creates nodes.
   *
   * @param array $rows
   *   CSV rows.
   * @param string $folderName
   *   Folder name.
   * @param string $contentType
   *   Content type.
   * @param string $vocabularyName
   *   Vocabulary name.
   *
   * @return array
   *   Processing result.
   */
  private function processRows(
    array $rows,
    string $folderName,
    string $contentType,
    string $vocabularyName,
  ): array {
    $processed = 0;
    $skipped = 0;
    $skipped_rows = [];

    foreach ($rows as $index => $row) {
      $row_number = $index + 2;

      $image_url = $row['csv_image_url'] ?? '';
      $node_title = $row['csv_node_title'] ?? '';
      $node_body = $row['csv_node_body'] ?? '';

      // Node must have a title and either image or body.
      if ($node_title === '' || ($image_url === '' && $node_body === '')) {
        $this->logger->warning('Skipping row @row_number: missing title or both image and body', [
          '@row_number' => $row_number,
        ]);
        $skipped++;
        $skipped_rows[] = $row_number;
        continue;
      }

      // Validate image if provided.
      if ($image_url !== '' && !$this->isImageUrlValid($image_url, $folderName)) {
        $this->logger->warning('Skipping row @row_number: invalid image URL @url', [
          '@row_number' => $row_number,
          '@url' => $image_url,
        ]);
        $skipped++;
        $skipped_rows[] = $row_number;
        continue;
      }

      // If image is empty, clear alt and title.
      if ($image_url === '') {
        $row['csv_image_alt'] = '';
        $row['csv_image_title'] = '';
      }

      try {
        $result = $this->creator->create($row, $folderName, $contentType, $vocabularyName);
        if ($result) {
          $processed++;
        }
        else {
          $this->logger->warning('Failed to create node for row @row_number', [
            '@row_number' => $row_number,
          ]);
          $skipped++;
          $skipped_rows[] = $row_number;
        }
      }
      catch (\Exception $e) {
        $this->logger->error('Error processing row @row_number: @error', [
          '@row_number' => $row_number,
          '@error' => $e->getMessage(),
        ]);
        $skipped++;
        $skipped_rows[] = $row_number;
      }
    }

    return [
      'status' => 'success',
      'message' => 'CSV processed successfully',
      'processed' => $processed,
      'skipped' => $skipped,
      'skipped_rows' => $skipped_rows,
    ];
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
