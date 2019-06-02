<?php

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\hycm_services\HycmServicesDomain;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'ClientPortalLanguageBlock' block.
 *
 * @Block(
 *   id = "hycm_blocks_client_portal_language_block",
 *   admin_label = @Translation("Client Portal Language Block"),
 *   category = @Translation("HYCM")
 * )
 */
class ClientPortalLanguageBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\hycm_services\HycmServicesDomain
   */
  protected $hycmDomain;

  /**
   * Constructs a new ClientPortalLanguageBlock instance.
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
   * @param \Drupal\hycm_services\HycmServicesDomain $hycmDomain
   *   The example service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, HycmServicesDomain $hycmDomain) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->hycmDomain = $hycmDomain;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('hycm_services.domain')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'wrapper_classes' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['wrapper_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Wrapper classes'),
      '#description' => $this->t('Specific classes for wrapper of button and Risk Warning text'),
      '#default_value' => $this->configuration['wrapper_classes'],
      '#weight' => '5',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $settings = array(
      'wrapper_classes',
    );
    foreach ($settings as $setting) {
      $this->configuration[$setting] = $form_state->getValue($setting);
    }
  }


  /**
   * {@inheritdoc}
   */
  public function build() {

    $class = 'right_header_side';
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $build['content'] = [
      '#theme' => 'client_portal_language',
      '#items' => ['current_lang' => $language],
      '#attributes' => ['class' => $class.' '.$this->configuration['wrapper_classes']],
      '#attached' => [
        'library' => ['hycm_blocks/client-portal-language-block'],
        'drupalSettings' => ['language' => $language],
      ],
    ];

    return $build;
  }

  /*
 * {@inheritdoc}
 */
  public function getCacheContexts() {
    return parent::getCacheContexts() + ['languages:language_interface'];
  }
}
