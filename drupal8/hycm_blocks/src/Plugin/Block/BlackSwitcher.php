<?php
/**
 * Created by PhpStorm.
 * User: Lex
 * Date: 08.08.2018
 * Time: 16:23
 */

namespace Drupal\hycm_blocks\Plugin\Block;


use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Provides a 'BlackSwitcher' block.
 *
 * @Block(
 *   id = "hycm_blocks_black_switcher",
 *   admin_label = @Translation("Black Language switcher)"),
 *   category = @Translation("Global")
 * )
 */
class BlackSwitcher extends BlockBase implements ContainerFactoryPluginInterface {


  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * Constructs a new BlackBlockBlock instance.
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
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager, PathMatcherInterface $path_matcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = $language_manager;
    $this->pathMatcher = $path_matcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('path.matcher')
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
  public function build() {
    $route_name = $this->pathMatcher->isFrontPage() ? '<front>' : '<current>';
    $type = $this->getDerivativeId();
    $links = $this->languageManager->getLanguageSwitchLinks($type, Url::fromRoute($route_name));

    $cnUrl = Url::fromUri("https://hycm.com/cn");
    $build = [];
    if (isset($links->links)) {

      if (isset($links->links['zh-hans'])) {
        $links->links['zh-hans']['url'] = $cnUrl;
      }

      foreach ($links->links as $code => $link) {
        $title = $links->links[$code]['title'];
        $links->links[$code]['title'] = $this->t($title, [], ['langcode' => $code, 'context' => 'Black switch']);
      }

      $build['content'] = [
        '#theme' => 'links__language_block',
        '#links' => $links->links,
        '#attributes' => [
          'class' => [
            "language-switcher-{$links->method_id}",
            "black-switcher",
          ],
        ],
        '#set_active_class' => TRUE,
      ];

      $build['#attached'] = [
        'library' => ['hycm_blocks/switcher', 'hycm_blocks/mob-switcher'],
        'drupalSettings' => [
          'hycm_blocks' => [
            'switcher' => [],
          ],
        ],
      ];
    }
    return $build;
  }

}