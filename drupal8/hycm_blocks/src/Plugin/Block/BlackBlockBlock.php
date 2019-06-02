<?php

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
 * Provides a 'BlackBlock' block.
 *
 * @Block(
 *   id = "hycm_blocks_black",
 *   admin_label = @Translation("Black block (Login, Live chat & Language switcher)"),
 *   category = @Translation("Global")
 * )
 */
class BlackBlockBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    $options = ['attributes' => ['class' => ['nav-link']]];

    $currentLanguage = $this->languageManager->getCurrentLanguage();
    $switcherOptions = [
      'attributes' => [
        'class' => ['nav-link' , $currentLanguage->getId(), 'dir' . $currentLanguage->getDirection()],
      ],
      'fragment' => 'switcher',
    ];
    $livechat = Link::fromTextAndUrl($this->t('Live chat'), Url::fromUserInput('#chat', ['attributes' => ['class' => ['nav-link', 'live-chat-trigger']]]));


    $switcher = Link::createFromRoute($this->t($this->languageManager->getCurrentLanguage()->getName()), '<current>', [], $switcherOptions);
    $build['content'] = [
      '#theme' => 'black_switch',
      '#login' => Link::fromTextAndUrl($this->t('Login'), Url::fromUri('internal:/login', $options))->toRenderable(),
      '#livechat' => $livechat->toRenderable(),
      '#switch' => $switcher->toRenderable(),
      '#attributes' => ['class' => 'black-sw-list'],
    ];

    return $build;
  }

}
