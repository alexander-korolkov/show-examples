<?php

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\hycm_services\HycmServicesDomain;

/**
 * Provides a 'JoinWorkshopBlock' block.
 *
 * @Block(
 *  id = "join_workshop_block",
 *  admin_label = @Translation("Join workshop form"),
 *  category = @Translation("Workshop")
 * )
 */
class JoinWorkshopBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Form\FormBuilderInterface definition.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;
  /**
   * Drupal\hycm_services\HycmServicesDomain definition.
   *
   * @var \Drupal\hycm_services\HycmServicesDomain
   */
  protected $hycmServicesDomain;
  /**
   * Constructs a new JoinWorkshopBlock object.
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
    FormBuilderInterface $form_builder, 
	HycmServicesDomain $hycm_services_domain
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
    $this->hycmServicesDomain = $hycm_services_domain;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('hycm_services.domain')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'webinar' => FALSE,
      'webinar_id' => '',
        'form_label' => 'Join Our Workshop Community'
          ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['form_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Form label'),
      '#default_value' => $this->configuration['form_label'],
      '#weight' => '0',
    ];
    $form['webinar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Webinar'),
      '#default_value' => $this->configuration['webinar'],
      '#weight' => '1',
    ];

    $form['webinar_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webinar ID'),
      '#default_value' => $this->configuration['webinar_id'],
      '#weight' => '2',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['form_label'] = $form_state->getValue('form_label');
    $this->configuration['webinar'] = $form_state->getValue('webinar');
    $this->configuration['webinar_id'] = $form_state->getValue('webinar_id');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $options = [];
    if ($this->configuration['webinar']) {
      $options['webinar_id'] = $this->configuration['webinar_id'];
    }

    $build['form_label']['#markup'] = new FormattableMarkup("<h2>@form_label</h2>", [
      '@form_label' => $this->t($this->configuration['form_label']),
    ]);

    $build['form'] = $this->formBuilder
      ->getForm('\Drupal\hycm_blocks\Form\JoinWorkshopForm', $options);

    /*
    $build['after_form_text']['#markup'] = new FormattableMarkup('<p class="login-link">Already have an account? <a href=":login" class="font-red">Log in here.</a></p>',
      [':login' => '/login',]
    );
    */
    return $build;
  }

}
