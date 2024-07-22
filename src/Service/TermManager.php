<?php

namespace Drupal\pb_import\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Service for managing taxonomy terms.
 */
class TermManager {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * TermManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('pb_import');
  }

  /**
   * Gets or creates a term in the specified vocabulary.
   *
   * @param string $term_name
   *   The name of the term.
   * @param string $vocabulary
   *   The machine name of the vocabulary.
   *
   * @return \Drupal\taxonomy\Entity\Term|null
   *   The term entity, or NULL if the vocabulary does not exist.
   */
  public function getOrCreateTerm($term_name, $vocabulary) {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $vocabularies = Vocabulary::loadMultiple();

    if (!isset($vocabularies[$vocabulary])) {
      $this->logger->error('Vocabulary @vocabulary does not exist.', ['@vocabulary' => $vocabulary]);
      return NULL;
    }

    $terms = $term_storage->loadByProperties(['vid' => $vocabulary]);

    foreach ($terms as $term) {
      if (strcasecmp($term->getName(), $term_name) === 0) {
        return $term;
      }
    }

    $term = Term::create([
      'vid' => $vocabulary,
      'name' => $term_name,
    ]);
    $term->save();
    $this->logger->info('New term created with ID: @term_id', ['@term_id' => $term->id()]);

    return $term;
  }

}
