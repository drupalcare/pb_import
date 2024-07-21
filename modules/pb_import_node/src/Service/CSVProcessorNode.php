<?php

namespace Drupal\pb_import_node\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\Entity\File;

/**
 *
 */
class CSVProcessorNode {

  protected FileSystemInterface $fileSystem;
  protected LoggerChannelInterface $logger;
  protected $creator;

  /**
   *
   */
  public function __construct(FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_factory, $creator) {
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('pb_import_node');
    $this->creator = $creator;
  }

  /**
   *
   */
  public function process(File $csv_file, $folder_name, $content_type, $vocabulary_name) {
    $processed = 0;
    $skipped = 0;

    $required_headers = [
      'csv_image_url',
      'csv_node_title',
      'csv_image_title',
      'csv_image_alt',
      'csv_node_tag',
      'csv_node_body',
    ];

    $path = $csv_file->getFileUri();
    $real_path = $this->fileSystem->realpath($path);

    $this->logger->info('Processing CSV file at @path', ['@path' => $real_path]);

    if (($handle = fopen($real_path, 'r')) !== FALSE) {
      $header = fgetcsv($handle, 1000, ',');
      // Trim whitespace from headers.
      $header = array_map('trim', $header);

      // Validate headers.
      $missing_headers = array_diff($required_headers, $header);
      $extra_headers = array_diff($header, $required_headers);

      if (!empty($missing_headers) || !empty($extra_headers)) {
        $error_message = 'CSV header validation failed. ';
        if (!empty($missing_headers)) {
          $error_message .= 'Missing headers: ' . implode(', ', $missing_headers) . '. ';
        }
        if (!empty($extra_headers)) {
          $error_message .= 'Extra headers: ' . implode(', ', $extra_headers) . '.';
        }
        $this->logger->error($error_message);
        return ['status' => 'error', 'message' => $error_message, 'processed' => $processed, 'skipped' => $skipped];
      }

      while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        // Check for row length.
        if (count($data) != count($header)) {
          $this->logger->warning('Skipping malformed row: @row', ['@row' => print_r($data, TRUE)]);
          $skipped++;
          continue;
        }

        // Trim whitespace from values.
        $data = array_map('trim', $data);
        // Combine header and data.
        $data = array_combine($header, $data);

        try {
          $result = $this->creator->create($data, $folder_name, $content_type, $vocabulary_name);
          if ($result) {
            $processed++;
          }
          else {
            $skipped++;
          }
        }
        catch (\Exception $e) {
          $this->logger->error('Error processing row: @row. Error: @error', [
            '@row' => implode(', ', $data),
            '@error' => $e->getMessage(),
          ]);
          $skipped++;
        }
      }
      fclose($handle);
    }
    else {
      $error_message = 'Failed to open CSV file at @path';
      $this->logger->error($error_message, ['@path' => $real_path]);
      return ['status' => 'error', 'message' => $error_message, 'processed' => $processed, 'skipped' => $skipped];
    }

    $status_message = "Nodes processed: $processed, Nodes skipped: $skipped";
    return ['status' => 'success', 'message' => $status_message, 'processed' => $processed, 'skipped' => $skipped];
  }

}
