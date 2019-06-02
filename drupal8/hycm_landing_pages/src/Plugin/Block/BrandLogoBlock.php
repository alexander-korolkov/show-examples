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
 * Provides a 'BrandLogoBlock' block.
 *
 * @Block(
 *  id = "brand_logo_block",
 *  admin_label = @Translation("Brand logo"),
 *  category = @Translation("Landing components")
 * )
 */
class BrandLogoBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * Constructs a new BrandLogoBlock object.
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
            'link_to_home' => 1,
        'optional_links' => FALSE,
          ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['link_to_home'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link to home'),
      '#description' => $this->t('Use link to home'),
      '#default_value' => $this->configuration['link_to_home'],
      '#weight' => 0,
    ];

    $form['optional_links'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add optional links'),
      '#description' => $this->t('Render register and login links'),
      '#default_value' => $this->configuration['optional_links'],
      '#weight' => 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['link_to_home'] = $form_state->getValue('link_to_home');
    $this->configuration['optional_links'] = $form_state->getValue('optional_links');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $themePath = \Drupal::theme()->getActiveTheme()->getPath();

    $image = new FormattableMarkup("<img src=':logo' alt='HYCM'>", [
      ':logo' => '/' . $themePath . '/assets/images/logo_white.svg'
    ]);
    if ($this->configuration['link_to_home']) {
      $url = Url::fromRoute('<front>', [], [
        'attributes' => [
          'class' => ['brand-logo'],
        ],
      ]);
      $build['brand_logo'] = Link::fromTextAndUrl($image, $url)->toRenderable();
    }else{
      $build['brand_logo'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $image,
        '#attributes' => [
          'class' => ['brand-logo'],
        ],
      ];
    }

    $build['brand_logo']['#prefix'] = '<div class="logo_wrap">';
    $build['brand_logo']['#suffix'] = '</div>';

    if ($this->configuration['optional_links']) {
      $build['optional_links'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['btns', 'd-flex'],
        ],
      ];

      $build['optional_links']['register'] = [
        '#markup' => '<a href="#shortForm" class="btn open_account_btn btn_white smooth_scroll" style="display: inline-block;">Register</a>',
      ];
      $build['optional_links']['login'] = [
        '#markup' => '<a href="/en/login" class="btn open_account_btn btn_transparent" style="display: inline-block;">Login</a>',
      ];
    }

    return $build;
  }

}
