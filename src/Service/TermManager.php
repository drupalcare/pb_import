<?php

declare(strict_types=1);

namespace Drupal\pb_import\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\taxonomy\VocabularyStorageInterface;

/**
 * Service for managing taxonomy terms.
 */
class TermManager {

  /**
   * Constructs a TermManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Gets or creates a term in the specified vocabulary.
   *
   * @param string $termName
   *   The name of the term.
   * @param string $vocabulary
   *   The machine name of the vocabulary.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The term entity, or NULL if the vocabulary does not exist.
   */
  public function getOrCreateTerm(string $termName, string $vocabulary): ?TermInterface {
    $termName = trim($termName);

    if ($termName === '') {
      $this->logger->warning('Empty term name provided for vocabulary @vocabulary', [
        '@vocabulary' => $vocabulary,
      ]);
      return NULL;
    }

    /** @var \Drupal\taxonomy\VocabularyStorageInterface $vocabulary_storage */
    $vocabulary_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    assert($vocabulary_storage instanceof VocabularyStorageInterface);

    /** @var \Drupal\taxonomy\VocabularyInterface|null $vocab */
    $vocab = $vocabulary_storage->load($vocabulary);

    if (!$vocab instanceof VocabularyInterface) {
      $this->logger->error('Vocabulary @vocabulary does not exist.', [
        '@vocabulary' => $vocabulary,
      ]);
      return NULL;
    }

    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    assert($term_storage instanceof TermStorageInterface);

    $query = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', $vocabulary)
      ->condition('name', $termName)
      ->range(0, 1);

    $term_ids = $query->execute();

    if (!empty($term_ids)) {
      $term_id = reset($term_ids);
      /** @var \Drupal\taxonomy\TermInterface|null $term */
      $term = $term_storage->load($term_id);
      return $term;
    }

    try {
      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = $term_storage->create([
        'vid' => $vocabulary,
        'name' => $termName,
      ]);
      $term->save();
      $this->logger->info('New term created with ID: @term_id', [
        '@term_id' => $term->id(),
      ]);
      return $term;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create term @term_name in vocabulary @vocabulary: @error', [
        '@term_name' => $termName,
        '@vocabulary' => $vocabulary,
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
