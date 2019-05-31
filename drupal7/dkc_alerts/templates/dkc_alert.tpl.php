<?php
/**
 * @file
 * Template for rendering alert.
 *
 * Variables:
 *  - alert Object which contains all info about alert.
 */
$class = $alert->icon ? 'dkc-error-icon' : '';
?>

<div class="dkc-alert <?php print $class ?>"
     data-aid="<?php print $alert->aid; ?>"
     style="background-color: <?php print $alert->color; ?>; color: <?php print $alert->textcolor; ?>;">
  <div class="dkc-alert-body">
    <?php print $alert->body ?>
  </div>
</div>
