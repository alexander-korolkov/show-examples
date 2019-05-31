<?php
/**
 * @file
 * dkc_sidebar.features.menu_custom.inc
 */

/**
 * Implements hook_menu_default_menu_custom().
 */
function dkc_sidebar_menu_default_menu_custom() {
  $menus = array();

  // Exported menu: menu-airport-menu.
  $menus['menu-airport-menu'] = array(
    'menu_name' => 'menu-airport-menu',
    'title' => 'Airport menu',
    'description' => '',
  );
  // Translatables
  // Included for use with string extractors like potx.
  t('Airport menu');

  return $menus;
}
