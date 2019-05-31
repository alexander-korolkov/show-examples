<?php
/**
 * @file
 * Represents template for rendering sidebar cards logos block.
 *
 * Available variables:
 * - $title: Block title.
 * - $subtitle: Block subtitle.
 * - $image: Url to cards logos image.
 * - $footer: Additional info after logos image.
 *
 * @ingroup themeable
 */
?>
<div class="dkc-cards-header">
  <h3 class="title"><?php print $title; ?></h3>
  <h4 class="subtitle"><?php print $subtitle; ?></h4>
</div>
<div class="dkc-cards-image">
  <img src="<?php print $image; ?>">
</div>
<div class="dkc-footer">
  <?php foreach ($footer as $item) {
    print '<p>' . $item . '</p>';
  }
  ?>
</div>