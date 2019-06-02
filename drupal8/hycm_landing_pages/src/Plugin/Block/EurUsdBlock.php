<?php

namespace Drupal\hycm_landing_pages\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\hycm_graphs\HycmGraphsPrices;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a 'EurUsdBlock' block.
 *
 * @Block(
 *  id = "eurusd_block",
 *  admin_label = @Translation("EurUsd Block"),
 *  category = @Translation("Landing components")
 * )
 */
class EurUsdBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * @var \Drupal\hycm_graphs\HycmGraphsPrices
   */
  protected $graphsPrices;
  /**
   * Constructs a new ImportantEventsBlock object.
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
    HycmGraphsPrices $graphsPrices,
  	ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->configFactory = $config_factory;
    $this->graphsPrices = $graphsPrices;
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
      $container->get('hycm_graphs.prices'),
      $container->get('config.factory')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {

  }


  /**
   * {@inheritdoc}
   */
  public function build() {

    $build = [];
    $data = $this->graphsPrices->getGraphs();

    $build['graphs'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['graphs-forex-four'],
      ],
      '#attached' => ['library' => ['hycm_graphs/graphs_forex_four']],
    ];


    $build['graphs']['eurusd'] = [
      '#theme' => 'lpblock_eurusd_block',
      '#title' => $data['Forex']['EURUSD']['details']['name'],
      '#points' => $data['Forex']['EURUSD']['points'],
      '#item' => $data['Forex']['EURUSD'],
      '#item_id' => ['EURUSD'],
    ];

    return $build;
  }

}
