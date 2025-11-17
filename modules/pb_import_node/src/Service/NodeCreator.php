<?php

declare(strict_types=1);

namespace Drupal\pb_import_node\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\pb_import\Service\FileManager;
use Drupal\pb_import\Service\TermManager;

/**
 * Service to create nodes from CSV data.
 */
class NodeCreator {

  /**
   * Constructs a NodeCreator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\pb_import\Service\TermManager $termManager
   *   The term manager service.
   * @param \Drupal\pb_import\Service\FileManager $fileManager
   *   The file manager service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelInterface $logger,
    private readonly TermManager $termManager,
    private readonly FileManager $fileManager,
  ) {}

  /**
   * Creates a node.
   *
   * @param array $data
   *   The data for the node.
   * @param string $folderName
   *   The folder name for image files.
   * @param string $contentType
   *   The content type.
   * @param string $vocabularyName
   *   The vocabulary name.
   *
   * @return bool
   *   TRUE if the node was created successfully, FALSE otherwise.
   */
  public function create(
    array $data,
    string $folderName,
    string $contentType,
    string $vocabularyName,
  ): bool {
    $image_name = trim((string) ($data['csv_image_url'] ?? ''));
    $node_title = trim((string) ($data['csv_node_title'] ?? ''));
    $node_body = trim((string) ($data['csv_node_body'] ?? ''));

    // Skip rows without a title or without either image or body.
    if ($node_title === '' || ($image_name === '' && $node_body === '')) {
      $this->logger->warning('Skipped row due to missing title and either image or body');
      return FALSE;
    }

    $image_title = trim((string) ($data['csv_image_title'] ?? ''));
    $image_alt = trim((string) ($data['csv_image_alt'] ?? ''));
    $tag_value = trim((string) ($data['csv_node_tag'] ?? ''));

    $terms = [];
    if ($tag_value !== '') {
      $taxonomy_term_names = explode('|', $tag_value);

      foreach ($taxonomy_term_names as $taxonomy_term_name) {
        $taxonomy_term_name = trim($taxonomy_term_name);
        if ($taxonomy_term_name === '') {
          continue;
        }

        $term = $this->termManager->getOrCreateTerm($taxonomy_term_name, $vocabularyName);
        if ($term !== NULL) {
          $terms[] = ['target_id' => $term->id()];
        }
        else {
          $this->logger->error('Failed to process term: @term', [
            '@term' => $taxonomy_term_name,
          ]);
          return FALSE;
        }
      }
    }

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    assert($node_storage instanceof NodeStorageInterface);

    $node_data = [
      'type' => $contentType,
      'title' => $node_title,
      'pb_import_node_tag' => $terms,
    ];

    // Handle image if provided.
    if ($image_name !== '') {
      $file_id = $this->fileManager->getFileId($folderName, $image_name);
      if ($file_id === NULL) {
        $this->logger->warning('File not found: @file', [
          '@file' => $folderName . '/' . $image_name,
        ]);
        return FALSE;
      }

      $node_data['pb_import_node_image'] = [
        'target_id' => $file_id,
        'alt' => $image_alt,
        'title' => $image_title,
      ];
    }

    // Handle body if provided.
    if ($node_body !== '') {
      $node_data['pb_import_node_body'] = [
        'value' => $node_body,
        'format' => 'full_html',
      ];
    }

    try {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $node_storage->create($node_data);
      assert($node instanceof NodeInterface);
      $node->save();
      $this->logger->info('Node created with ID: @node_id', [
        '@node_id' => $node->id(),
      ]);
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create node: @message', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

}
