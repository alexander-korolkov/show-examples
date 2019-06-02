<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 24.09.2018
 * Time: 23:58
 *
 * AwardsListBlock
 *
 */

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a 'AwardsList' block.
 *
 * @Block(
 *   id = "hycm_blocks_awards_list",
 *   admin_label = @Translation("Awards List"),
 *   category = @Translation("HYCM")
 * )
 */
class AwardsList extends BlockBase implements ContainerFactoryPluginInterface {

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

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $renderArray = [
      '#theme'  =>  'awards_list',
      '#awards' =>  'awards'
    ];

    $build['awards_list']['#markup'] = \Drupal::service('renderer')->render($renderArray);

    return $build;
  }

}
