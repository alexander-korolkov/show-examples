<?php

namespace Drupal\hycm_blocks\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hycm_services\Ajax\HycmSetCookies;
use Drupal\hycm_services\HycmServicesGotoWebinar;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\hycm_services\HycmServicesDomain;

/**
 * Class JoinWorkshopForm.
 */
class JoinWorkshopForm extends FormBase {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;
  /**
   * Drupal\hycm_services\HycmServicesDomain definition.
   *
   * @var \Drupal\hycm_services\HycmServicesDomain
   */
  protected $hycmServicesDomain;

  /**
   * Country phone codes
   *
   * @var array
   */
  protected $countryCodes;

  /**
   * @var \Drupal\hycm_services\HycmServicesGotoWebinar
   */
  protected $gotoWebinar;

  /**
   * Constructs a new JoinWorkshopForm object.
   */
  public function __construct(
    HycmServicesDomain $hycm_services_domain,
    HycmServicesGotoWebinar $gotoWebinar,
    ConfigFactoryInterface $configFactory

  ) {
    $this->hycmServicesDomain = $hycm_services_domain;
    $this->gotoWebinar = $gotoWebinar;
    $this->configFactory = $configFactory;

  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('hycm_services.domain'),
      $container->get('hycm_services.goto_webinar'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'join_workshop_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'hycm_blocks/join-workshop';

    $form['source'] = [
      '#type' => 'hidden',
      '#value' => 'join-workshop'
    ];
    $request = \Drupal::request();
    if ($request->query->has('utm_source') && $request->query->has('utm_medium')) {
      $utm['source'] = $request->query->get('utm_source');
      $utm['medium'] = $request->query->get('utm_medium');
      $form['source']['#value'] =  implode("; ", $utm);
    }

    $options = $form_state->getBuildInfo()['args'][0];
 //   dpm($options);

    // 'webinar_id'
    if (isset($options['webinar_id'])) {
      $form['webinar_id'] = [
        '#type' => 'hidden',
        '#value' => $options['webinar_id'],
      ];
    }

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
      '#attributes' => [
        'data-parsley-pattern' => "[A-Za-z\-\' ]+",
        'data-parsley-error-message' => $this->t('This value is invalid, please enter correct details'),

      ],
    ];
    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
      '#attributes' => [
        'data-parsley-pattern' => "[A-Za-z\-\' ]+",
        'data-parsley-error-message' => $this->t('This value is invalid, please enter correct details'),

      ],
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
      '#attributes' => [
        'data-parsley-pattern' => "[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+.[A-Za-z]{2,4}$",
        'data-parsley-error-message' => $this->t('This value is invalid, please enter correct details'),

      ],
    ];

    $form['phone'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['field-row'],
      ],
    ];
    $form['phone']['code'] = [
      '#type' => 'select',
      '#options' => $this->countryCodes(),
      '#weight' => '4',
      '#attributes' => [
        'id' => 'phone-prefix',
        'class' => [],
      ],
    ];
    $form['phone']['tel'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone'),
      '#maxlength' => 20,
      '#size' => 64,
      '#weight' => '5',
      '#attributes' => [
        'autocomplete' => 'off',
        'data-parsley-pattern' => "([0-9])+",
        'data-parsley-minlength' => 6,
        'data-parsley-error-message' => $this->t('This value is invalid, please enter correct details'),
        'data-parsley-required-message' => $this->t('This value is invalid'),
      ],
    ];

    // I have read and accept the Privacy Policy of HYCM

    $form['app'] = [
      '#type' => 'checkbox',
      '#weight' => '49',
      '#title' => $this->t('I have read and accept the <a class="pp_link" target="_blank" href=":applink">Privacy Policy</a> of HYCM',[
        ':applink' => Url::fromUri('internal:/')->toString(),
      ]),
    ];

    $form['receive_news'] = [
      '#type' => 'checkbox',
      '#weight' => '49',
      '#title' => $this->t('I would like to receive Company news, products updates and promotions'),
    ];

    $form['customQuestion0'] = [
      '#type' => 'hidden',
      '#value' => '',
      '#attributes' => [
        'class' => ['custom_question'],
      ]
    ];

    $form['actions']['#type'] =  'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Open Live Account'),
      '#attributes' => [
        'class' => ['btn', 'red-btn'],
      ],
//      '#ajax' => [
//        'callback' => '::ajaxSubmitCallback',
//        'event' => 'click',
//        'progress' => [
//          'type' => 'throbber', // 'throbber' (default) or 'bar'.
//        ],
//      ],
      '#weight' => '50',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // @todo Add register action
    // @todo Add webinar register action

    $record = $this->gotoWebinarRegister($form_state);

    $states =  $form_state->getValues();
    $states = Json::encode($states);
    $build = [
      'content' => [
        '#markup' => '<p>A workshop invintation link has been sent to your email.</p>',
      ],
      'states' => ['#markup' => Json::encode($record),],
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ]
      ]
    ];


    $title = $this->t('Thank you <span>for registering!</span>');

    $dialog_options = [
      'height' => '220',
      'width' => 'auto',
      'classes' => [
        'ui-dialog' => 'workshop-modal-dialog',
        'ui-dialog-titlebar' => 'workshop-modal-title',
      ]
    ];

    $codes = $this->countryCodesList();
    $registration = [
      'first_name' => $form_state->getValue('first_name'),
      'surname' => $form_state->getValue('last_name'),
      'email' => $form_state->getValue('email'),
      'phone_prefix' => $codes[$form_state->getValue('code')]['code'],
      'phone' => $form_state->getValue('tel'),
      'country' => $form_state->getValue('code'),
      'landing_page' => 'true',
      'agree_pp' => true,
    ];

    //[surname , first_name, email, country, phone, phone_prefix]
//    $response->addCommand(new OpenModalDialogCommand($title, $build, $dialog_options));
//    $cookies = [];
//    $cookies[] = [
//      'name' => 'step_1',
//      'value' => Json::encode($registration),
//      'options' => [
//        'expires' => 365,
//        'path' => '/'
//      ],
//    ];
//    dpm($cookies);
//    $cookies[] = [
//      'name' => 'lastStep',
//      'value' => 1,
//    ];
//    $cookies[] = [
//      'name' => 'fromLP',
//      'value' => true,
//      'options' => [
//        'expires' => 1,
//      ],
//    ];
//    $response->addCommand(new HycmSetCookies($cookies));

//    $url = Url::fromUserInput('/register');
//    $response->addCommand(new RedirectCommand($url->toString()));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
   //   \Drupal::messenger()->addMessage($key . ': ' . $value);
    }

  }

  protected function countryCodes() {
    $codes = $this->countryCodesList();
    $items = [];
    foreach ($codes as $key => $code) {
      $items[$key] = $this->t($code['name']);
    }
    return $items;
  }

  protected function countryCodesList() {
    if (empty($this->countryCodes)) {
      $codesConfig = $this->configFactory->get('hycm_services.country_codes');
      $codes = $codesConfig->get('codes');
      $items = [];
      foreach ($codes as $code) {
        $country = $code['country'];
        $items[$country] = $code;
      }
      $this->countryCodes = $items;
    }

    return $this->countryCodes;
  }

  /**
   * Create registration on GotoWebinar
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array|mixed|null
   */
  protected function gotoWebinarRegister(FormStateInterface $form_state) {
    $webinarKey = $form_state->getValue('webinar_id');
    \Drupal::logger('join form')->debug('$webinarKey: ' . $webinarKey);

    $registrant = [
      'firstName' => $form_state->getValue('first_name'),
      'lastName' => $form_state->getValue('last_name'),
      'email' => $form_state->getValue('email'),
      'source' => $form_state->getValue('source'),
      'phone' => $this->getPhone($form_state),
    ];
    try {
      $apiResponse = $this->gotoWebinar->createRegistrant($webinarKey, $registrant);
    }catch (\Exception $e) {
      \Drupal::logger('join form')->error('Error: ' . $e->getMessage());
      $apiResponse = [
        'status' => 'error',
        'massage' => 'Unknown error',
      ];
    }
    return $apiResponse;
  }


  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return string
   */
  protected function getPhone(FormStateInterface $form_state) {
    $codes = $this->countryCodesList();
    $number = $form_state->getValue('tel');
    $country = $form_state->getValue('code');
    $telephone = $codes[$country] . $number;
    return $telephone;
  }

}
