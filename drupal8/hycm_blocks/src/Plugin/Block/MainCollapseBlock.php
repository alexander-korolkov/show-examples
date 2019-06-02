<?php

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\hycm_blocks\MainMenuTreeTrait;
use Drupal\hycm_services\HycmServicesDomain;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Main Collapse' block.
 *
 * @Block(
 *   id = "hycm_blocks_main_collapse",
 *   admin_label = @Translation("Main Collapse"),
 *   category = @Translation("Menus")
 * )
 */
class MainCollapseBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use MainMenuTreeTrait;
  /**
   * The Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * @var \Drupal\hycm_services\HycmServicesDomain
   */
  protected $hycmDomain;

  /**
   * Constructs a new MainMenuBlock instance.
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
   * @param \Drupal\Core\Render\RendererInterface|object $renderer
   *   The renderer service
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menuLinkManager
   *   The Menu manager service
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuLinkTree
   *   The Menu link tree service
   * @param \Drupal\hycm_services\HycmServicesDomain $hycmDomain
   *   The HYCM domains service
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
                              RendererInterface $renderer,
                              MenuLinkManagerInterface $menuLinkManager,
                              MenuLinkTreeInterface $menuLinkTree,
                              HycmServicesDomain $hycmDomain) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->menuLinkManager = $menuLinkManager;
    $this->menuLinkTree = $menuLinkTree;
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
      $container->get('plugin.manager.menu.link'),
      $container->get('menu.link_tree'),
      $container->get('hycm_services.domain')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $tree = $this->getMainTree();
    $build = $this->menuLinkTree->build($tree);
    $build['#attached']['library'][] = 'hycm_blocks/main-nav-mobile';
    return $build;
  }

}
