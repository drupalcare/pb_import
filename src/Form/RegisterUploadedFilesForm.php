<?php

declare(strict_types=1);

namespace Drupal\pb_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pb_import\Service\FileRegistrar;
use Drupal\pb_import\Service\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to register uploaded files in a specified directory.
 */
class RegisterUploadedFilesForm extends FormBase {

  /**
   * Constructs a RegisterUploadedFilesForm object.
   *
   * @param \Drupal\pb_import\Service\FileRegistrar $fileRegistrar
   *   The file registrar service.
   * @param \Drupal\pb_import\Service\Utility $utility
   *   The utility service.
   */
  public function __construct(
    private readonly FileRegistrar $fileRegistrar,
    private readonly Utility $utility,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('pb_import.file_registrar'),
      $container->get('pb_import.utility')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pb_import_register_files_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['image_folder_relative_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image Folder Relative Path'),
      '#description' => $this->t('Enter the relative path to the image folder inside files, e.g., gallery/austin (it will be @site_path/gallery/austin)', [
        '@site_path' => $this->utility->getSiteSpecificPath(),
      ]),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register Files'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $image_folder_relative_path = trim((string) $form_state->getValue('image_folder_relative_path'));

    if ($image_folder_relative_path === '') {
      $form_state->setErrorByName('image_folder_relative_path', $this->t('The image folder path cannot be empty.'));
      return;
    }

    if (!preg_match('/^[a-zA-Z0-9\-_\/]+$/', $image_folder_relative_path)) {
      $form_state->setErrorByName('image_folder_relative_path', $this->t('The image folder path contains invalid characters. Only letters, numbers, hyphens, underscores, and forward slashes are allowed.'));
      return;
    }

    if (str_contains($image_folder_relative_path, '..')) {
      $form_state->setErrorByName('image_folder_relative_path', $this->t('The image folder path cannot contain ".." sequences.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $image_folder_relative_path = rtrim((string) $form_state->getValue('image_folder_relative_path'), '/');
    $this->fileRegistrar->registerFiles($image_folder_relative_path);
  }

}
