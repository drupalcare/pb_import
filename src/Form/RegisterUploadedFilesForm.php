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
 * Form to register uploaded files in a specified directory.
 */
class RegisterUploadedFilesForm extends FormBase {

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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The file registrar service.
   *
   * @var \Drupal\pb_import\Service\FileRegistrar
   */
  protected FileRegistrar $fileRegistrar;

  /**
   * The utility service.
   *
   * @var \Drupal\pb_import\Service\Utility
   */
  protected Utility $utility;

  /**
   * RegisterUploadedFilesForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\pb_import\Service\FileRegistrar $file_registrar
   *   The file registrar service.
   * @param \Drupal\pb_import\Service\Utility $utility
   *   The utility service.
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
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bulk_file_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['image_folder_relative_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image Folder Relative Path'),
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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $image_folder_relative_path = trim($form_state->getValue('image_folder_relative_path'));
    if (empty($image_folder_relative_path)) {
      $form_state->setErrorByName('image_folder_relative_path', $this->t('The image folder path cannot be empty.'));
    }
    elseif (!preg_match('/^[a-zA-Z0-9-_\/]+$/', $image_folder_relative_path)) {
      $form_state->setErrorByName('image_folder_relative_path', $this->t('The image folder path contains invalid characters.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $image_folder_relative_path = rtrim($form_state->getValue('image_folder_relative_path'), '/');
    $this->logger->info('Relative directory: @relative_directory', ['@relative_directory' => $image_folder_relative_path]);

    // Scan the directory and register files.
    $this->fileRegistrar->registerFiles($image_folder_relative_path);
  }

}
