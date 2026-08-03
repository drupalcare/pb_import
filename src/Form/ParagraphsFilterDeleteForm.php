<?php

declare(strict_types=1);

namespace Drupal\pb_import\Form;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to delete selected paragraphs.
 */
class ParagraphsFilterDeleteForm extends FormBase {

  /**
   * Constructs a new ParagraphsFilterDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pb_import_paragraphs_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->getRequest();
    $selected_type = $request->query->get('type');
    $selected_type = is_string($selected_type) ? $selected_type : NULL;

    $total_paragraphs = $this->getTotalParagraphCount($selected_type);
    $paragraphs = $this->getParagraphs($selected_type);
    $displayed_paragraphs = count($paragraphs);

    $form['summary'] = [
      '#markup' => $this->t('Displaying @count out of @total paragraphs.', [
        '@count' => $displayed_paragraphs,
        '@total' => $total_paragraphs,
      ]),
      '#prefix' => '<div class="paragraph-summary">',
      '#suffix' => '</div>',
    ];

    $form['filter'] = [
      '#type' => 'container',
    ];

    $form['filter']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter by type'),
      '#options' => $this->getParagraphTypes(),
      '#default_value' => $selected_type,
    ];

    $form['filter']['actions'] = [
      '#type' => 'actions',
    ];

    $form['filter']['actions']['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#submit' => ['::filterForm'],
    ];

    $form['filter']['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => ['::resetForm'],
    ];

    $header = [
      'id' => $this->t('ID'),
      'type' => $this->t('Type'),
      'label' => $this->t('Label'),
      'parent' => $this->t('Parent'),
      'edit' => $this->t('Edit'),
    ];

    $form['paragraphs_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $paragraphs,
      '#empty' => $this->t('No paragraphs found.'),
      '#attributes' => ['id' => 'paragraphs-table'],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected'),
      '#button_type' => 'danger',
      '#attributes' => [
        'onclick' => 'if(!confirm("Are you sure you want to delete the selected paragraphs?")){return false;}',
      ],
    ];

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
  protected function getTotalParagraphCount(?string $type = NULL): int {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    assert($paragraph_storage instanceof EntityStorageInterface);

    $query = $paragraph_storage->getQuery()
      ->accessCheck(TRUE)
      ->count();

    if ($type !== NULL && $type !== '') {
      $query->condition('type', $type);
    }

    $count = $query->execute();
    return is_int($count) ? $count : 0;
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
  protected function getParagraphs(?string $type = NULL): array {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    assert($paragraph_storage instanceof EntityStorageInterface);

    $query = $paragraph_storage->getQuery()
      ->accessCheck(TRUE)
      ->pager(50);

    if ($type !== NULL && $type !== '') {
      $query->condition('type', $type);
    }

    $paragraph_ids = $query->execute();
    $paragraphs = $paragraph_storage->loadMultiple($paragraph_ids);

    $items = [];
    foreach ($paragraphs as $paragraph) {
      assert($paragraph instanceof ParagraphInterface);
      $parent_entity = $paragraph->getParentEntity();
      $parent_label = $parent_entity !== NULL ? $parent_entity->label() : $this->t('No Parent');

      $edit_url = Url::fromRoute('pb_import.paragraph_edit', [
        'paragraph' => $paragraph->id(),
      ]);
      $edit_link = Link::fromTextAndUrl($this->t('Edit'), $edit_url);

      $items[$paragraph->id()] = [
        'id' => $paragraph->id(),
        'type' => $paragraph->bundle(),
        'label' => $paragraph->label() ?? $this->t('No label'),
        'parent' => $parent_label,
        'edit' => [
          'data' => $edit_link,
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
  protected function getParagraphTypes(): array {
    $types_storage = $this->entityTypeManager->getStorage('paragraphs_type');
    $types = $types_storage->loadMultiple();

    $options = ['' => $this->t('- All -')];
    foreach ($types as $type) {
      assert($type instanceof ParagraphsTypeInterface);
      $options[$type->id()] = $type->label();
    }
    return $options;
  }

  /**
   * Filter form submission handler.
   */
  public function filterForm(array &$form, FormStateInterface $form_state): void {
    $query = [];
    $type = $form_state->getValue('type');
    if (is_string($type) && $type !== '') {
      $query['type'] = $type;
    }
    $form_state->setRedirect('pb_import.paragraphs_list', [], ['query' => $query]);
  }

  /**
   * Reset form submission handler.
   */
  public function resetForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('pb_import.paragraphs_list');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected_paragraphs = array_filter((array) $form_state->getValue('paragraphs_table', []));

    if (empty($selected_paragraphs)) {
      $this->messenger()->addWarning($this->t('No paragraphs selected for deletion.'));
      $form_state->setRedirect('pb_import.paragraphs_list');
      return;
    }

    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    assert($paragraph_storage instanceof EntityStorageInterface);

    $deleted_count = 0;
    foreach ($selected_paragraphs as $paragraph_id) {
      $paragraph = $paragraph_storage->load($paragraph_id);
      if ($paragraph instanceof ParagraphInterface) {
        try {
          $paragraph->delete();
          $deleted_count++;
        }
        catch (\Exception $e) {
          $this->messenger()->addError($this->t('Failed to delete paragraph @id: @message', [
            '@id' => $paragraph_id,
            '@message' => $e->getMessage(),
          ]));
        }
      }
    }

    if ($deleted_count > 0) {
      $this->messenger()->addStatus($this->t('Deleted @count paragraph(s).', [
        '@count' => $deleted_count,
      ]));
    }

    $form_state->setRedirect('pb_import.paragraphs_list');
  }

}
