<?php
/**
 * [lrob_cc_manage] output — re-opens the consent banner.
 *
 * @var string $label
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<button type="button" class="lrob-cc-manage-link" data-lrob-cc-open><?php echo esc_html($label); ?></button>
