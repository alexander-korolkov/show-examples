<?php
/**
 * @file
 * Represents template for rendering alerts on page.
 *
 * Variables:
 *  alerts - Array of alerts;
 *  path - Path which was used for getting alerts.
 */
?>
<div id="dkc-alerts-wrapper">
  <?php foreach ($alerts as $alert): ?>
    <?php print $alert;?>
  <?php endforeach; ?>
</div>
