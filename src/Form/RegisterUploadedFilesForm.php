<?php

namespace Drupal\pb_import\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\pb_import\Service\FileRegistrar;
use Drupal\pb_import\Service\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class RegisterUploadedFilesForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected LoggerChannelInterface $logger;
  protected $messenger;
  protected $configFactory;
  protected FileRegistrar $fileRegistrar;
  protected Utility $utility;

  /**
   *
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger, ConfigFactoryInterface $config_factory, FileRegistrar $file_registrar, Utility $utility) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('pb_import');
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
    $this->fileRegistrar = $file_registrar;
    $this->utility = $utility;
  }

  /**
   *
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('config.factory'),
      $container->get('pb_import.file_registrar'),
      $container->get('pb_import.utility')
    );
  }

  /**
   *
   */
  public function getFormId() {
    return 'bulk_file_import_form';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['image_folder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image Folder'),
      '#description' => $this->t('Enter the relative path to the image folder inside files, e.g., gallery/austin (it will be @site_path/gallery/austin)', ['@site_path' => $this->utility->getSiteSpecificPath()]),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register Files'),
    ];

    return $form;
  }

  /**
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $folder_name = trim($form_state->getValue('image_folder'));
    if (empty($folder_name)) {
      $form_state->setErrorByName('image_folder', $this->t('The image folder path cannot be empty.'));
    }
    elseif (!preg_match('/^[a-zA-Z0-9-_\/]+$/', $folder_name)) {
      $form_state->setErrorByName('image_folder', $this->t('The image folder path contains invalid characters.'));
    }
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $folder_name = rtrim($form_state->getValue('image_folder'), '/');
    $this->logger->info('Relative directory: @relative_directory', ['@relative_directory' => $folder_name]);

    // Scan the directory and register files.
    $this->fileRegistrar->registerFiles($folder_name);
  }

}
