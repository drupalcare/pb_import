<?php

declare(strict_types=1);

namespace Drupal\pb_import\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\pb_import\Service\HelpRenderer;

/**
 * Hook implementations for PB Import.
 */
final class PbImportHooks {

  /**
   * The route this module serves its own paragraph edit form on.
   */
  private const EDIT_ROUTE = 'pb_import.paragraph_edit';

  public function __construct(
    private readonly HelpRenderer $help,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    return $route_name === 'help.page.pb_import'
      ? $this->help->render('pb_import')
      : NULL;
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for paragraph forms.
   *
   * Sends the editor back to this module's paragraph list after saving — but
   * only for the form this module itself serves, at
   * `/admin/content/paragraph/{paragraph}/edit`.
   *
   * This used to run on every paragraph form on the site. Editing any
   * paragraph anywhere — a hero on a node form, a card inside a layout — then
   * redirected the editor to PB Import's list, a page they had not asked for
   * and, on a site that only uses the importer occasionally, had probably
   * never seen. The redirect belongs to this module's own workflow.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_id
   *   The form id.
   */
  #[Hook('form_paragraph_form_alter')]
  public function formParagraphFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if ($this->routeMatch->getRouteName() !== self::EDIT_ROUTE) {
      return;
    }
    if (!isset($form['actions']['submit']['#submit']) || !is_array($form['actions']['submit']['#submit'])) {
      return;
    }
    $form['actions']['submit']['#submit'][] = [static::class, 'redirectToList'];
  }

  /**
   * Submit handler that returns the editor to the paragraph list.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function redirectToList(array $form, FormStateInterface $form_state): void {
    $entity = $form_state->getFormObject()->getEntity();
    \Drupal::messenger()->addStatus(t('The paragraph %label has been saved.', [
      '%label' => $entity->label(),
    ]));
    $form_state->setRedirect('pb_import.paragraphs_list');
  }

}
