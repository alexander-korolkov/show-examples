<?php
/**
 * Created by PhpStorm.
 * User: Lex
 * Date: 28.09.2018
 * Time: 13:09
 */

namespace Drupal\hycm_blocks\Plugin\paragraphs\Behavior;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
/**
 * @ParagraphsBehavior(
 *   id = "hycm_blocks_paragraph_slick_slider",
 *   label = @Translation("Paragraph Slick slider"),
 *   description = @Translation("Render body field as Slick slider."),
 *   weight = 0,
 * )
 */
class ParagraphSlickSliderBehavior extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    if ($paragraphs_type->id() == 'simple_slick_slider') {
      return TRUE;
    }
    return FALSE;
  }

  private function settingsPrepare(array &$settings) {
    $intKeys = ['slidesToShow', 'slidesToScroll'];
    foreach ($settings as $key => $value) {
      if (in_array($key, $intKeys)) {
        $settings[$key] = (int) $value;
      }
    }
  }

  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode) {
    // TODO: Implement view() method.
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage();
    $direction = $currentLanguage->getDirection();
    $langcode = $currentLanguage->getId();
    $settings = $paragraph->getBehaviorSetting($this->getPluginId(), 'slick', []);
    $this->settingsPrepare($settings);

    if ($direction == 'rtl' || $langcode == 'ar' || $langcode == 'fa') {
      $settings['rtl'] = TRUE;
      $build['#attributes']['dir'] = 'rtl';
    }

    $settingsJson = Json::encode($settings);
   // $build['#attributes']['data-slick-settings'] = $settingsJson;

    $build['field_slide']['#attributes']['data-slick'] = $settingsJson;
    $build['field_slide']['#attributes']['data-direction'] = $direction;
    $build['field_slide']['#attributes']['data-langcode'] = $langcode;
    $build['#attributes']['class'][] = 'slick-settings-container';
    $build['#attached']['library'][] = 'hycm_blocks/paragraph-slick';

    //$build['slick_attributes'] = $settingsJson;

   // $build['slick_attributes'] = new Attribute($build['slick_attributes']);
  //  dpm($build['field_slide']['#attributes']);

  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    //slidesToShow
    //slidesToScroll
    $slickSettings = $paragraph->getBehaviorSetting($this->getPluginId(), 'slick', []);
    $form['slick'] = [
      '#type' => 'container',
    ];
   // dpm($slickSettings);
    $form['slick']['slidesToShow'] = [
      '#type' => 'number',
      '#title' => $this->t('slidesToShow'),
      '#default_value' =>  isset($slickSettings['slidesToShow']) ? $slickSettings['slidesToShow'] : 3,
    ];

    $form['slick']['slidesToScroll'] = [
      '#type' => 'number',
      '#title' => $this->t('slidesToScroll'),
      '#default_value' => isset($slickSettings['slidesToScroll']) ? $slickSettings['slidesToScroll'] : 1,
    ];

    $form['slick']['responsive'] = [
      '#type' => 'details',
      '#title' => $this->t('Responsive breakpoints')
    ];

    for ($i = 0; $i < 3; $i++) {

      $form['slick']['responsive'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Breakpoint #@number', ['@number' => $i+1]),
      ];

      $form['slick']['responsive'][$i]['breakpoint'] = [
        '#type' => 'number',
        '#title' => $this->t('Breakpoint value in px'),
      ];
      // settings 1199, 991, 767, 576
      $form['slick']['responsive'][$i]['settings'] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $this->t('Breakpoint settings'),
      ];
      $form['slick']['responsive'][$i]['settings']['slidesToShow'] = [
        '#type' => 'number',
        '#title' => $this->t('slidesToShow'),
        '#default_value' =>  isset($slickSettings['responsive'][$i]['settings']['slidesToShow'])
          ? $slickSettings['responsive'][$i]['settings']['slidesToShow'] : '',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $title_element = $paragraph->getBehaviorSetting($this->getPluginId(), 'title_element');
    return [$title_element ? $this->t('Title element: @element', ['@element' => $title_element]) : ''];
  }

  /**
   * Return options for heading elements.
   */
  private function getTitleOptions() {
    return [
      'h2' => '<h2>',
      'h3' => '<h3>',
      'h4' => '<h4>',
      'div' => '<div>',
    ];
  }


}