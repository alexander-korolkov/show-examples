<?php

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\hycm_graphs\HycmGraphsPrices;
use Drupal\hycm_services\HycmServicesDomain;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Markets (home)' block.
 *
 * @Block(
 *   id = "hycm_blocks_markets_home",
 *   admin_label = @Translation("Markets (home)"),
 *   category = @Translation("HYCM")
 * )
 */
class MarketsHomeBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * @var \Drupal\hycm_services\HycmServicesDomain
   */
  protected $hycmDomain;

  /**
   * @var array
   */
  protected $markets;

  /**
   * @var array
   */
  protected $graphsData;

  /**
   * Constructs a new MarketsHomeBlock instance.
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
   *
   * @param RendererInterface $renderer
   * @param ConfigFactoryInterface $configFactory
   * @param \Drupal\hycm_graphs\HycmGraphsPrices $graphsPrices
   * @param \Drupal\hycm_services\HycmServicesDomain $hycmDomain
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
                              RendererInterface $renderer,
                              ConfigFactoryInterface $configFactory,
                              HycmGraphsPrices $graphsPrices,
                              HycmServicesDomain $hycmDomain) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->configFactory = $configFactory;
    $this->graphsPrices = $graphsPrices;
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
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('hycm_graphs.prices'),
      $container->get('hycm_services.domain')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'foo' => $this->t('Hello world!'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {


    $form['foo'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Foo'),
      '#default_value' => $this->configuration['foo'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['foo'] = $form_state->getValue('foo');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $contents = $this->getMarkets();


    $isCIMA = $this->hycmDomain->getDomain() == 'com';


    foreach ($contents as $key => $item) {

      $itemAttributes = [
        'class' => ['markets-item'],
        'data-markets-name' => $item['name'],
        'data-markets-id' => $key,
      ];
      $contents[$key]['attributes'] = new Attribute($itemAttributes);
      $contents[$key]['leverage'] = $isCIMA ? $item['max_leverage_eu'] : $item['max_leverage_eu'];

      $contents[$key]['graphs'] = $this->getGraphs($key);

    }

    $url = Url::fromRoute('entity.node.canonical', [
      'node' => 18,
    ]);
    $link = Link::fromTextAndUrl($this->t('See all products'), $url);
    //dpm($contents);
    $build['content'] = [
      '#theme' => 'markets_home',
      '#items' => $contents,
      '#link' => $link->toRenderable(),
      '#attributes' => [
        'id' => 'markets-home-wrap',
        'class' => [
          'hycm-markets-home',
        ],
      ],
    ];


    $build['content']['#attached']['library'][] = 'hycm_blocks/markets-home';

    return $build;
  }


  /**
   * @return array
   */
  private function getMarkets() {
    if (empty($this->markets)) {
      $marketsContent = $this->configFactory->get('hycm_blocks.marketscontent');
      $names = ['forex','stocks', 'indices','cryptocurrencies', 'commodities'];
      $content = [];
      foreach ($names as $name) {
        $content[$name] = $marketsContent->get($name);
      }
      $this->markets = $content;
    }
    return $this->markets;
  }

  /**
   * @param $marketId
   * @return array
   */
  protected function getGraphs($marketId) {
    $build = [];

    $graphsData = $this->getGraphsData();

    $marketData = isset($graphsData[$marketId]) ? $graphsData[$marketId] : [];

    foreach ($marketData as $key => $item) {
      $build[] = [
        '#theme' => 'hycm_market_graph',
        '#item' => $item,
        '#title' => $item['details']['name'],
        '#item_id' => strtolower($key),
      ];
    }
   // dpm($marketData, $marketId);

    return $build;
  }


  private function getGraphsData() {
    if (empty($this->graphsData)) {
      $this->graphsData = $this->graphsPrices->getMarkets();
    }
    return $this->graphsData;
  }

  public function getCacheContexts() {
    return parent::getCacheContexts() + ['hycm_domain'];
  }


}
