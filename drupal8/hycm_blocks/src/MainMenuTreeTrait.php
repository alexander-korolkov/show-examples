<?php
/**
 * Created by PhpStorm.
 * User: Lex
 * Date: 22.08.2018
 * Time: 23:43
 */

namespace Drupal\hycm_blocks;


trait MainMenuTreeTrait {

  // todo move to service

  /**
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   */
  protected function getMainTree() {

    $menu_name = 'main';

    $menu_parameters = new \Drupal\Core\Menu\MenuTreeParameters();
    $menu_parameters->setMaxDepth(2);
    $menu_parameters->setRoot('');
    $menu_parameters->excludeRoot();

    $tree = $this->menuLinkTree->load($menu_name, $menu_parameters);



    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'],
      //['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    return $this->menuLinkTree->transform($tree, $manipulators);
  }


}