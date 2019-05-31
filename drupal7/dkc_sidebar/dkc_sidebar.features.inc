<?php
/**
 * @file
 * dkc_sidebar.features.inc
 */

/**
 * Implements hook_image_default_styles().
 */
function dkc_sidebar_image_default_styles() {
  $styles = array();

  // Exported image style: dkc_sidebar_images.
  $styles['dkc_sidebar_images'] = array(
    'label' => 'DKC sidebar images',
    'effects' => array(
      13 => array(
        'name' => 'image_scale',
        'data' => array(
          'width' => 262,
          'height' => '',
          'upscale' => 1,
        ),
        'weight' => 1,
      ),
    ),
  );

  return $styles;
}
