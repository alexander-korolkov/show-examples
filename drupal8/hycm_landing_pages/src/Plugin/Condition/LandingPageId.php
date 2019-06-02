<?php

namespace Drupal\hycm_landing_pages\Plugin\Condition;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'LandingPageId' condition.
 *
 * @Condition(
 *   id = "hycm_landing_page_id",
 *   label = @Translation("Landing Page ID"),
 *   context = {
 *     "hylp" = @ContextDefinition(
 *       "entity:hylp",
 *        label = @Translation("Landing page")
 *      )
 *   }
 * )
 */
class LandingPageId extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  protected $storage;

  const ENTITY_ID = "hylp";


  /**
   * Creates a new BasePageId instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManager	$entityTypeManager
   *   The entity type manager.

   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->storage = $entityTypeManager->getStorage(self::ENTITY_ID);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['entity_id' => NULL] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['entity_id'] = [
      '#title' => $this->t('Landing page'),
      '#type' => 'entity_autocomplete',
      '#target_type' => self::ENTITY_ID,
      '#default_value' => isset($this->configuration['entity_id']) ?
        $this->storage->load($this->configuration['entity_id']) : NULL,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['entity_id'] = $form_state->getValue('entity_id');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t(
      'Landing page ID: @entity_id',
      ['@entity_id' => $this->configuration['entity_id']]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {

    try {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $this->getContextValue(self::ENTITY_ID);
    }catch (PluginException $e) {
      \Drupal::logger('hycm_landing_pages')->critical($e->getMessage());
      return FALSE;
    }
    if ($entity instanceof EntityInterface && $this->configuration['entity_id'] == $entity->id() && !$this->isNegated()) {
      return TRUE;
    }
    return FALSE;
  }

}
