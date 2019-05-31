<?php
/**
 * @file
 * Lush cart form tpl.
 */
?>
<?php if (isset($form['lush_product_id'])): ?>
  <?php print drupal_render($form['lush_product_id']); ?>
<?php endif; ?>
<?php if (isset($form['lush_quantity'])): ?>
  <?php print drupal_render($form['lush_quantity']); ?>
<?php endif; ?>
<?php if (isset($form['lush_submit']) && $form['lush_submit']['#type'] == 'submit'): ?>
<?php if (isset($form['lush_submit_disable'])): ?>
<div class="form-submit-box submit-disable">
  <?php else: ?>
  <div class="form-submit-box">
    <?php endif; ?>
    <?php print drupal_render($form['lush_submit']); ?>
  </div>
  <?php elseif (isset($form['lush_submit'])): ?>
    <?php print drupal_render($form['lush_submit']); ?>
  <?php endif; ?>
  <?php print drupal_render_children($form); ?>
