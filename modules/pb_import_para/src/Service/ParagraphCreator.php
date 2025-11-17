<?php

declare(strict_types=1);

namespace Drupal\pb_import_para\Service;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\pb_import\Service\FileManager;
use Drupal\pb_import\Service\TermManager;

/**
 * Service to create paragraphs from CSV data.
 */
class ParagraphCreator {

  /**
   * Constructs a ParagraphCreator object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\pb_import\Service\FileManager $fileManager
   *   The file manager service.
   * @param \Drupal\pb_import\Service\TermManager $termManager
   *   The term manager service.
   */
  public function __construct(
    private readonly LoggerChannelInterface $logger,
    private readonly FileManager $fileManager,
    private readonly TermManager $termManager,
  ) {}

  /**
   * Creates a paragraph entity.
   *
   * @param array $data
   *   The data for the paragraph.
   * @param string $imageFolderRelativePath
   *   The folder name for image files.
   * @param string $paragraphType
   *   The paragraph type.
   * @param string $vocabularyName
   *   The vocabulary name.
   * @param bool $isParent
   *   (optional) Whether the paragraph is a parent paragraph Defaults to FALSE.
   *
   * @return \Drupal\paragraphs\ParagraphInterface|null
   *   The created paragraph entity, or NULL on failure.
   */
  public function createParagraph(
    array $data,
    string $imageFolderRelativePath,
    string $paragraphType,
    string $vocabularyName,
    bool $isParent = FALSE,
  ): ?ParagraphInterface {
    $paragraph_data = ['type' => $paragraphType];

    // Process data - no manual sanitization, Drupal handles it.
    foreach ($data as $field_name => $value) {
      if (is_array($value)) {
        $paragraph_data[$field_name] = $value;
      }
      else {
        $paragraph_data[$field_name] = trim((string) $value);
      }
    }

    // Handle body field with text format.
    if (isset($data['pb_target_body'])) {
      $paragraph_data['pb_target_body'] = [
        'value' => trim((string) $data['pb_target_body']),
        'format' => 'full_html',
      ];
    }

    // Handle image field.
    if (!$isParent && isset($data['pb_target_image'])) {
      $image_file = trim((string) $data['pb_target_image']);

      if ($image_file !== '') {
        $file_id = $this->fileManager->getFileId($imageFolderRelativePath, $image_file);

        if ($file_id !== NULL) {
          $paragraph_data['pb_target_image'] = [
            'target_id' => $file_id,
            'alt' => trim((string) ($data['csv_image_alt'] ?? '')),
            'title' => trim((string) ($data['csv_image_title'] ?? '')),
          ];
          $this->logger->info('File ID for image @image: @file_id', [
            '@image' => $image_file,
            '@file_id' => $file_id,
          ]);
        }
        else {
          $this->logger->warning('File not found: @file', [
            '@file' => $imageFolderRelativePath . '/' . $image_file,
          ]);
        }
      }
    }

    // Handle taxonomy terms.
    if (isset($data['pb_target_tag'])) {
      $tag_value = trim((string) $data['pb_target_tag']);

      if ($tag_value !== '') {
        $terms = [];
        $term_names = explode('|', $tag_value);

        foreach ($term_names as $term_name) {
          $term_name = trim($term_name);
          if ($term_name === '') {
            continue;
          }

          $term = $this->termManager->getOrCreateTerm($term_name, $vocabularyName);
          if ($term !== NULL) {
            $terms[] = ['target_id' => $term->id()];
          }
          else {
            $this->logger->warning('Failed to create or load term: @term', [
              '@term' => $term_name,
            ]);
          }
        }

        if (!empty($terms)) {
          $paragraph_data['pb_target_tag'] = $terms;
        }
      }
    }

    try {
      /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
      $paragraph = Paragraph::create($paragraph_data);
      $paragraph->save();
      $this->logger->info('Paragraph created with ID: @id', [
        '@id' => $paragraph->id(),
      ]);
      return $paragraph;
    }
    catch (\Exception $e) {
      $this->logger->error('Error creating paragraph: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
