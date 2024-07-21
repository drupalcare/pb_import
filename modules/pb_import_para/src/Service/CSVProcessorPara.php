<?php

namespace Drupal\pb_import_para\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\Entity\File;

/**
 * Service to process CSV files for importing paragraphs.
 */
class CSVProcessorPara {

  protected FileSystemInterface $fileSystem;
  protected LoggerChannelInterface $logger;
  protected ParagraphCreator $paragraphCreator;

  protected array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

  /**
   * CSVProcessorPara constructor.
   */
  public function __construct(FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_factory, ParagraphCreator $paragraph_creator) {
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('pb_import_para');
    $this->paragraphCreator = $paragraph_creator;
  }

  /**
   * Processes the CSV file.
   */
  public function process(File $file, string $parent_title, string $parent_paragraph_type, string $parent_entity_reference_field, string $section_paragraph_type, string $section_entity_reference_field, string $target_bundle, string $vocabulary_name, string $folder_name, string $section_title_field) {
    $processed = 0;
    $skipped = 0;

    $path = $file->getFileUri();
    $real_path = $this->fileSystem->realpath($path);

    $this->logger->info('Processing CSV file at @path', ['@path' => $real_path]);

    $required_headers = [
      'csv_image_url',
      'csv_image_alt',
      'csv_image_title',
      'csv_target_title',
      'csv_target_tag',
      'csv_target_body',
    ];

    $rows = [];
    if (($handle = fopen($real_path, 'r')) !== FALSE) {
      $header = fgetcsv($handle, 1000, ',');
      // Trim whitespace from headers.
      $header = array_map('trim', $header);
      $this->logger->debug('CSV Header: @header', ['@header' => print_r($header, TRUE)]);

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
        $this->logger->debug('CSV Row: @data', ['@data' => print_r($data, TRUE)]);
        $rows[] = array_combine($header, $data);
      }
      fclose($handle);
    }
    else {
      $error_message = 'Failed to open CSV file at @path';
      $this->logger->error($error_message, ['@path' => $real_path]);
      return ['status' => 'error', 'message' => $error_message, 'processed' => $processed, 'skipped' => $skipped];
    }

    $this->logger->info('Processed CSV rows: @rows', ['@rows' => print_r($rows, TRUE)]);

    $section_paragraphs = [];
    foreach ($rows as $row) {
      $image_file = $row['csv_image_url'] ?? '';
      $title = $row['csv_target_title'] ?? '';
      $body = $row['csv_target_body'] ?? '';

      // Check if at least one of the fields (Title, Image, Body) is not empty.
      if (empty($image_file) && empty($title) && empty($body)) {
        $this->logger->warning('Skipping row due to empty Title, Image, and Body.');
        $skipped++;
        continue;
      }

      $child_data = [
        'pb_target_image' => $image_file,
        'csv_image_alt' => $row['csv_image_alt'] ?? '',
        'csv_image_title' => $row['csv_image_title'] ?? '',
        'pb_target_tag' => $row['csv_target_tag'] ?? '',
        'pb_target_body' => $body,
      ];
      $child_paragraph = $this->paragraphCreator->createParagraph($child_data, $folder_name, $target_bundle, $vocabulary_name);
      if ($child_paragraph) {
        $section_data = [
          // Always use the parent title from the form.
          'pb_content_title' => $parent_title ?? '',
          // Dynamically set the section title field based on the paragraph type.
          $section_title_field => $title,
          $section_entity_reference_field => [
            ['target_id' => $child_paragraph->id(), 'target_revision_id' => $child_paragraph->getRevisionId()],
          ],
        ];
        $section_paragraph = $this->paragraphCreator->createParagraph($section_data, $folder_name, $section_paragraph_type, $vocabulary_name);
        if ($section_paragraph) {
          $section_paragraphs[] = ['target_id' => $section_paragraph->id(), 'target_revision_id' => $section_paragraph->getRevisionId()];
          $processed++;
        }
        else {
          $skipped++;
        }
      }
      else {
        $skipped++;
      }
    }

    if (empty($section_paragraphs)) {
      $error_message = 'No section paragraphs were created. Cannot create parent paragraph.';
      $this->logger->error($error_message);
      return ['status' => 'error', 'message' => $error_message, 'processed' => $processed, 'skipped' => $skipped];
    }

    $parent_data = [
      'pb_content_title' => $parent_title ?? '',
      $parent_entity_reference_field => $section_paragraphs,
    ];

    $this->logger->info('Creating parent paragraph with data: @data', ['@data' => print_r($parent_data, TRUE)]);
    $parent_paragraph = $this->paragraphCreator->createParagraph($parent_data, $folder_name, $parent_paragraph_type, $vocabulary_name, TRUE);

    if ($parent_paragraph) {
      $this->logger->info('Parent paragraph created successfully with ID: @parent_paragraph_id', ['@parent_paragraph_id' => $parent_paragraph->id()]);
    }
    else {
      $this->logger->error('Failed to create the parent paragraph.');
    }

    $this->logger->info('Created parent paragraph "@parent_title" with @count sections.', [
      '@parent_title' => $parent_title,
      '@count' => count($section_paragraphs),
    ]);

    return ['status' => 'success', 'message' => 'CSV processed successfully', 'processed' => $processed, 'skipped' => $skipped];
  }

  /**
   *
   */
  protected function validateFileExtension($file) {
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    return in_array(strtolower($extension), $this->allowedExtensions);
  }

}
