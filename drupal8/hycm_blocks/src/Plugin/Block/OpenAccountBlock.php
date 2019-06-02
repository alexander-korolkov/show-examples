<?php

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\hycm_services\HycmServicesDomain;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'OpenAccount' block.
 *
 * @Block(
 *   id = "hycm_blocks_openaccount",
 *   admin_label = @Translation("Open an Account"),
 *   category = @Translation("HYCM")
 * )
 */
class OpenAccountBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\hycm_services\HycmServicesDomain
   */
  protected $hycmDomain;

  /**
   * Constructs a new OpenAccountBlock instance.
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
   * @param \Drupal\hycm_services\HycmServicesDomain $hycmDomain
   *   The example service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, HycmServicesDomain $hycmDomain) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('hycm_services.domain')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'link_text' => 'Open Live Account',
      'button_classes' => '',
      'wrapper_classes' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $this->configuration['link_text'],
    ];

    $form['button_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button classes'),
      '#description' => $this->t('Specific classes for wrapper of button and Risk Warning text. Inter classes using space as separator'),
      '#default_value' => $this->configuration['button_classes'],
      '#weight' => '5',
    ];

    $form['wrapper_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Wrapper classes'),
      '#description' => $this->t('Specific classes for wrapper of button and Risk Warning text'),
      '#default_value' => $this->configuration['wrapper_classes'],
      '#weight' => '5',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $settings = array(
      'link_text',
      'button_classes',
      'wrapper_classes',
    );
    foreach ($settings as $setting) {
      $this->configuration[$setting] = $form_state->getValue($setting);
    }
  }


  /**
   * {@inheritdoc}
   */
  public function build() {

    $added_classes = explode(' ', $this->configuration['button_classes']);
    $class = ['open-an-account', 'btn', 'fade-in', 'btn-hycm-hover'];

    $hycmDomain = $this->hycmDomain->getDomain();
    $registerLink = Link::fromTextAndUrl($this->t($this->configuration['link_text']),Url::fromUserInput('/register', [
      'attributes' => [
        'class' => array_merge($class, $added_classes),
      ],
    ]));

    $build['content'] = [
      '#theme' => 'open_an_account',
      '#button' => $registerLink,
      '#attributes' => ['class' => $this->configuration['wrapper_classes']],
      '#is_warning' => !($hycmDomain == 'com'),// Show in not .com domain
    ];

    return $build;
  }

  /*
 * {@inheritdoc}
 */
  public function getCacheContexts() {
    return parent::getCacheContexts() + ['hycm_domain'];
  }
}
