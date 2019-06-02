<?php

namespace Drupal\hycm_landing_pages\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a 'ShortFormBlock' block.
 *
 * @Block(
 *  id = "shortform_block",
 *  admin_label = @Translation("ShortForm Block"),
 *  category = @Translation("Landing components")
 * )
 */
class ShortFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * Constructs a new ShortFormBlock object.
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
  public function defaultConfiguration() {
    return [
            'title_form' => 'Start Trading <strong>Today</strong>',
            'attached_library' => 'hycm_landing_pages/shortform_js',
            'block_class' => 'col-12',
           ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['title_form'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title Form'),
      '#description' => $this->t('Top title on the form'),
      '#default_value' => $this->configuration['title_form'],
      '#weight' => '0',
    ];

    $form['attached_library'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Specific attached library'),
      '#description' => $this->t('Inter library that you want to attach'),
      '#default_value' => $this->configuration['attached_library'],
      '#weight' => '5',
    ];

    $form['block_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Specific block classes'),
      '#description' => $this->t('Inter classes using space as separator'),
      '#default_value' => $this->configuration['block_class'],
      '#weight' => '5',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $settings = array(
      'title_form',
      'attached_library',
      'block_class'
    );
    foreach ($settings as $setting) {
      $this->configuration[$setting] = $form_state->getValue($setting);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $class= "form_wrap formwrap inner1s lp_form_global";

    $build['content'] = [
      '#theme' => 'lpblock_shortform',
      '#items' => [
        'title_form' => t($this->configuration['title_form']),
        'block_class' => $this->configuration['block_class'],
      ],
      '#attributes' => [
       // 'class' => $this->configuration['block_class'].' '.$class,
        'id' => "reg-form",
        'ng-controller' => "LandingController",
      ],
      '#attached' => [
        'library' => [ 'hycm_landing_pages/shortform', $this->configuration['attached_library']],
      ],
    ];

    return $build;
  }

}
