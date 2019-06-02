<?php

namespace Drupal\hycm_landing_pages\Layout;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Plugin\PluginFormInterface;

class LayoutDynamicWrapper extends LayoutDefault implements PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
        'header' => [
          'background' => FALSE,
          'type' => 'image',
          'src' => '',
          'video' => [
            'options' => [],
            'source' => [
              'mp4' => '/themes/custom/hycm/assets/video/desktop.mp4',
            ]
          ]
        ],
        'container' => [
          'extra_classes' => '',
        ],
        'libraries' => [],
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();

    $form['container'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#title' => $this->t('Container settings'),
    ];
    $form['container']['extra_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Extra classes'),
      '#default_value' => $configuration['container']['extra_classes'],
    ];

    $form['header'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#open' => TRUE,
      '#title' => $this->t('Header background'),
    ];
    $form['header']['background'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use background'),
      '#default_value' => $configuration['header']['background'],
      '#description' => $this->t('Render a special div tag'),
    ];
    $form['header']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
        'image' => $this->t('Image'),
        'video' => $this->t('Video'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="layout_settings_wrapper[layout_settings][header][background]"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => $configuration['header']['type'],
    ];
    $form['header']['src'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Background image or video poster'),
      '#default_value' => $configuration['header']['src'],
      '#description' => $this->t('Source in theme folder without "/"'),
      '#states' => [
        'required' => [
          ':input[name="layout_settings_wrapper[layout_settings][header][background]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['header']['video'] = [
      '#type' => 'details',
      '#title' => $this->t('Video'),
    ];
    $form['header']['video']['options'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Video settings'),
      '#options' => [
        'loop' => $this->t('Loop'),
        'autoplay' =>  $this->t('Autoplay'),
        'muted' => $this->t('Muted'),
      ],
      '#default_value' => $configuration['header']['video']['options'],
    ];
    $form['header']['video']['source'] = [
      '#type' => 'details',
      '#title' => $this->t('Source'),
    ];
    $form['header']['video']['source']['mp4'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MP4'),
      '#default_value' => $configuration['header']['video']['source']['mp4'],
    ];

    foreach ($this->getPluginDefinition()->getRegionNames() as $region) {
      $form[$region] = [
        '#type' => 'details',
        '#tree' => TRUE,
        '#title' => $this->t('Settings @region region', ['@region' => $region]),
      ];
      $form[$region]['extra_classes'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Extra classes'),
        '#default_value' => isset($configuration[$region]['extra_classes']) ? $configuration[$region]['extra_classes'] : '',
      ];
      $form[$region]['container_wrapper'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Container Wrapper'),
        '#default_value' => isset($configuration[$region]['container_wrapper']) ? $configuration[$region]['container_wrapper'] : '',
      ];
      $form[$region]['row_wrapper'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Row Wrapper'),
        '#default_value' => isset($configuration[$region]['row_wrapper']) ? $configuration[$region]['row_wrapper'] : '',
      ];

    }


    return $form;
  }

  /**
   * @inheritdoc
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // any additional form validation that is required
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {

//    $values = $form_state->getValues();
    $this->configuration['header'] = $form_state->getValue('header');
    $this->configuration['container'] = $form_state->getValue('container');
    foreach ($this->getPluginDefinition()->getRegionNames() as $region) {
//      $this->configuration[$region]['extra_classes'] = $values[$region]['extra_classes'];
      $this->configuration[$region] = $form_state->getValue($region);
    }
  }

}