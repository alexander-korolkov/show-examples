<?php

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a 'AwardsBlock' block.
 *
 * @Block(
 *   id = "hycm_blocks_awards_block",
 *   admin_label = @Translation("Awards"),
 *   category = @Translation("HYCM")
 * )
 */
class AwardsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Render\RendererInterface definition.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new AwardsList object.
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
    RendererInterface $renderer,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('config.factory')
    );
  }

  public function defaultConfiguration() {
    return  [
      'slick' => [],
        'bootstrap' => [
          'classes' => ['col-12'],
        ],
        'title' => '<strong>HYCM</strong> Multiple Global Awards',
      ] + parent::defaultConfiguration();
  }


  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $items = $this->configFactory->get('hycm_blocks.awards')->get('items');

    $slick = [
      'slidesToShow' => 4,
      'slidesToScroll' => 2,
    ];

    $build['label'] = [
      '#markup' => '<h2>' . $this->configuration['title'] .  '</h2>',
    ];
    $build['content'] = [
      '#theme' => 'awards',
      '#items' => $items,
      '#attributes' => [
        'data-slick' => Json::encode($slick),
      ],
    ];


    return $build;
  }

  public function blockForm($form, FormStateInterface $form_state) {

    $form['title'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Title text'),
      '#default_value' => $this->configuration['title'],
    ];

    $form['bootstrap'] = [
      '#type' => 'details',
      '#title' => 'Bootstrap support',
      '#open' => TRUE,
    ];
    $form['bootstrap']['classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Classes'),
      '#default_value' => $this->configuration['bootstrap']['classes'],
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['bootstrap'] = $form_state->getValue('bootstrap');
  }


}
