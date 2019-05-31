<?php
/**
 * @file
 * Lush cart tpl.
 */
?>
<div class="add-cart-box">
  <div id="lush-cart-form-view" class="lush-cart-wrapper">
    <div class="lush-cart-wishlist">
      <?php if (isset($node['wishlist'])): ?>
        <?php print render($node['wishlist']); ?>
      <?php endif; ?>
    </div>
    <div class="close">x</div>
    <div class="product-image object-commerce-image">
      <div class="object-commerce-image-inner">
        <div class="object-commerce-image-inner-inner">
          <?php if (isset($node['field_image']) && $node['field_image']): ?>
            <?php print l($node['field_image'], 'node/' . $node['node']->nid, array(
              'html' => TRUE,
              'attributes' => array('title' => check_plain($node['node']->title)),
            )); ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="lush-product-title">
      <?php if (isset($node['node']->title)): ?>
        <?php print l($node['node']->title, 'node/' . $node['node']->nid, array('attributes' => array('title' => check_plain($node['node']->title)))); ?>
      <?php endif; ?>
    </div>
    <div id="lush-field-product" class="blackboard container-padding-mobile">
      <?php if (isset($form_content)): ?>
        <?php print $form_content; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
