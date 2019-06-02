<?php

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\hycm_blocks\MainMenuTreeTrait;
use Drupal\hycm_services\HycmServicesDomain;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'MainMenu' block.
 *
 * @Block(
 *   id = "hycm_blocks_mainmenu",
 *   admin_label = @Translation("Main navigation"),
 *   category = @Translation("HYCM")
 * )
 */
class MainMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
  public function defaultConfiguration() {
    return [
      //'foo' => $this->t('Hello world!'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    /*
    $form['foo'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Foo'),
      '#default_value' => $this->configuration['foo'],
    ];
    */
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    //$this->configuration['foo'] = $form_state->getValue('foo');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $hycmDomain = $this->hycmDomain->getDomain();

    $tree = $this->getMainTree();

    $build = $this->menuLinkTree->build($tree);

    $build['#theme'] = 'hycm_main_menu';


    $build['#brand'] = Link::createFromRoute('Home','<front>');


    $registerLink = Link::fromTextAndUrl($this->t('Open An Account'),Url::fromUserInput('/register', [
      'attributes' => [
        'class' => ['open-an-account', 'btn', 'fade-in', 'btn-hycm-hover'],
      ],
    ]));

    $depositLink = Link::fromTextAndUrl($this->t('Deposit'),Url::fromUserInput('/banking', [
      'attributes' => [
        'class' => ['deposit_btn', 'btn', 'fade-in', 'is-paused', 'font14', 'btn-red', 'py-2'],
      ],
    ]));

    $build['#register'] = [
      '#theme' => 'open_an_account',
      '#button' => $registerLink,
      '#warning' => $this->t('Trading CFDs involves significant risk of loss'),
      '#is_warning' => !($hycmDomain == 'com'),// ? FALSE : TRUE,
    ];

    $build['#deposit'] = [
      '#theme' => 'open_an_account',
      '#button' => $depositLink,
      '#warning' => $this->t('Trading CFDs involves significant risk of loss'),
      '#is_warning' => !($hycmDomain == 'com'),// ? FALSE : TRUE,
    ];

    foreach ($build['#items'] as $key => $item) {
      $build['#items'][$key]['attributes']->setAttribute('data-hynavid', $key);
//      dpm($item['below']);

      foreach ($item['below'] as $subkey => $subitem) {
        /** @var Url $url */
        $url = $subitem['url'];
        $options = $url->getOptions();
        $description = $options['attributes']['title'];
        $title = new FormattableMarkup('<span class="a-title semibold d-block">@title</span><span>@description</span>', [
          '@title' => $subitem['title'],
          '@description' => $description,
        ]);
        $build['#items'][$key]['below'][$subkey]['title'] = $title;

      }
    }
    $build['#attached'] = [
      'library' => ['hycm_blocks/main-nav'],
      'drupalSettings' => [
        'hycm_blocks' => [
          'mainmenu' => [],
        ],
      ],
    ];
 //   dpm($build['#items'], 'build');
    return $build;
  }

  /*
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return parent::getCacheContexts() + ['hycm_domain'];
  }


}
