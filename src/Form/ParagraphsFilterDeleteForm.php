<?php

namespace Drupal\pb_import\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a form to delete selected paragraphs.
 */
class ParagraphsFilterDeleteForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new ParagraphsFilterDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paragraphs_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the selected type from the request.
    $request = $this->requestStack->getCurrentRequest();
    $selected_type = $request->query->get('type');

    // Get total number of paragraphs for display message.
    $total_paragraphs = $this->getTotalParagraphCount($selected_type);
    $paragraphs = $this->getParagraphs($selected_type);
    $displayed_paragraphs = count($paragraphs);

    // Display message at the top.
    $form['summary'] = [
      '#markup' => $this->t('Displaying @count out of @total paragraphs.', [
        '@count' => $displayed_paragraphs,
        '@total' => $total_paragraphs,
      ]),
      '#prefix' => '<div class="paragraph-summary">',
      '#suffix' => '</div>',
    ];

    // Add the filter form.
    $form['filter'] = [
      '#type' => 'container',
      'type' => [
        '#type' => 'select',
        '#title' => $this->t('Filter by type'),
        '#options' => $this->getParagraphTypes(),
        '#default_value' => $selected_type,
      ],
      'actions' => [
        '#type' => 'actions',
        'filter' => [
          '#type' => 'submit',
          '#value' => $this->t('Filter'),
          '#submit' => ['::filterForm'],
        ],
        'reset' => [
          '#type' => 'submit',
          '#value' => $this->t('Reset'),
          '#submit' => ['::resetForm'],
        ],
      ],
    ];

    // Define the table header without sorting.
    $header = [
      'id' => $this->t('ID'),
      'type' => $this->t('Type'),
      'label' => $this->t('Label'),
      'parent' => $this->t('Parent'),
      'edit' => $this->t('Edit'),
    ];

    // Include the table in the form for the selected paragraphs.
    $form['paragraphs_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $paragraphs,
      '#empty' => $this->t('No paragraphs found.'),
      '#attributes' => ['id' => 'paragraphs-table'],
    ];

    // Provide the submit button for deleting selected paragraphs.
    $form['actions']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected'),
      '#button_type' => 'danger',
      '#attributes' => [
        'onclick' => 'if(!confirm("Are you sure you want to delete the selected paragraphs?")){return false;}',
      ],
    ];

    // Add the pager.
    $form['pager'] = [
      '#type' => 'pager',
    ];

    return $form;
  }

  /**
   * Fetches total paragraph count.
   *
   * @param string|null $type
   *   The paragraph type to filter by.
   *
   * @return int
   *   The total number of paragraphs.
   */
  protected function getTotalParagraphCount($type = NULL) {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $query = $paragraph_storage->getQuery()
      ->accessCheck(TRUE);

    if ($type) {
      $query->condition('type', $type);
    }

    return $query->count()->execute();
  }

  /**
   * Fetches paragraphs for the table.
   *
   * @param string|null $type
   *   The paragraph type to filter by.
   *
   * @return array
   *   The array of paragraphs.
   */
  protected function getParagraphs($type = NULL) {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $query = $paragraph_storage->getQuery()
      ->accessCheck(TRUE)
      ->pager(50);

    if ($type) {
      $query->condition('type', $type);
    }

    $paragraph_ids = $query->execute();
    $paragraphs = $paragraph_storage->loadMultiple($paragraph_ids);

    $items = [];
    foreach ($paragraphs as $paragraph) {
      $parent_entity = $paragraph->getParentEntity();
      $parent_label = $parent_entity ? $parent_entity->label() : $this->t('No Parent');

      $edit_url = Url::fromRoute('pb_import.paragraph_edit', ['paragraph' => $paragraph->id()]);
      $edit_link = Link::fromTextAndUrl($this->t('Edit'), $edit_url)->toString();

      $items[$paragraph->id()] = [
        'id' => $paragraph->id(),
        'type' => $paragraph->bundle(),
        'label' => $paragraph->label(),
        'parent' => $parent_label,
        'edit' => [
          'data' => [
            '#markup' => $edit_link,
          ],
        ],
      ];
    }

    return $items;
  }

  /**
   * Fetches available paragraph types.
   *
   * @return array
   *   The array of paragraph types.
   */
  protected function getParagraphTypes() {
    $types = $this->entityTypeManager->getStorage('paragraphs_type')->loadMultiple();
    $options = ['' => $this->t('- All -')];
    foreach ($types as $type) {
      $options[$type->id()] = $type->label();
    }
    return $options;
  }

  /**
   * Filter form submission handler.
   */
  public function filterForm(array &$form, FormStateInterface $form_state) {
    $query = [];
    $type = $form_state->getValue('type');
    if ($type) {
      $query['type'] = $type;
    }
    $form_state->setRedirect('pb_import.paragraphs_list', [], ['query' => $query]);
  }

  /**
   * Reset form submission handler.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('pb_import.paragraphs_list');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the selected paragraphs from the form state.
    $selected_paragraphs = array_filter($form_state->getValue('paragraphs_table', []));

    if (!empty($selected_paragraphs)) {
      $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
      foreach ($selected_paragraphs as $paragraph_id) {
        $paragraph = $paragraph_storage->load($paragraph_id);
        if ($paragraph) {
          $paragraph->delete();
        }
      }
      $this->messenger->addMessage($this->t('Selected paragraphs have been deleted.'));
    }
    else {
      $this->messenger->addMessage($this->t('No paragraphs selected for deletion.'), MessengerInterface::TYPE_WARNING);
    }

    // Redirect back to the list page after deletion.
    $form_state->setRedirect('pb_import.paragraphs_list');
  }

}
