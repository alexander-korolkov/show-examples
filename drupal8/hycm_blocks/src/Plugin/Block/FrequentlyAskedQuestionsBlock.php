<?php

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Provides a 'FrequentlyAskedQuestionsBlock' block.
 *
 * @Block(
 *  id = "frequently_asked_questions_block",
 *  admin_label = @Translation("Frequently Asked Questions"),
 *  category = @Translation("Workshop")
 * )
 */
class FrequentlyAskedQuestionsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;
  /**
   * Drupal\Core\Render\RendererInterface definition.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;
  /**
   * Constructs a new FrequentlyAskedQuestionsBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory, 
	RendererInterface $renderer
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->renderer = $renderer;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('renderer')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'show_items' => 5,
          ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['show_items'] = [
      '#type' => 'number',
      '#title' => $this->t('Show Items'),
      '#default_value' => $this->configuration['show_items'],
      '#weight' => '0',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['show_items'] = $form_state->getValue('show_items');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $config = $this->configFactory->get('hycm_blocks.frequently_asked_questions');
    $items = $config->get('faqs');

    $build['content'] = [
      '#theme' => 'workshop_faq',
      '#items' => $items,
      '#show_items' => (int) $this->configuration['show_items'],
      '#attributes' => [
        'class' => ['workshop-faq'],
      ],
      '#attached' => [
        'library' => [
          'hycm_blocks/workshop_faq'
        ],
      ],
    ];

    $this->renderer->addCacheableDependency($build, $config);
    return $build;
  }

  /**
  public function getCacheContexts() {
    return parent::getCacheContexts() + ['config:hycm_blocks.frequently_asked_questions'];
  }
   */
}
