<?php
/**
 * Front banner markup. Rendered by Frontend\Banner::render().
 *
 * @var array<string,string> $texts
 * @var array<string,array{title:string,desc:string}> $labels
 * @var list<string> $optional
 * @var string $position
 * @var bool $show_deny
 * @var bool $collapsed
 * @var string $logo
 */

if (!defined('ABSPATH')) {
    exit;
}

// Refuse-as-link ("Continue without accepting") — same action as closing the
// banner (deny all). Rendered at the admin-chosen position; the Deny button is
// shown instead when the style is "button".
$continue_pos = ($show_deny && ($deny_style ?? 'button') === 'link') ? ($deny_link_position ?? 'under-buttons') : '';
$continue_arrow_html = !empty($continue_arrow) ? ' <span class="lrob-cc-continue-arrow" aria-hidden="true">&rarr;</span>' : '';
$continue_html = $continue_pos !== ''
    ? '<button type="button" class="lrob-cc-continue" data-lrob-cc-action="deny-all">' . esc_html($texts['continue']) . $continue_arrow_html . '</button>'
    : '';
$continue_align_cls = ' lrob-cc-continue-align-' . esc_attr($continue_align ?? 'center');
?>
<div id="lrob-cc-banner" class="lrob-cc-banner lrob-cc-pos-<?php echo esc_attr($position); ?><?php echo in_array($backdrop ?? 'none', ['dim', 'blur'], true) ? ' lrob-cc-bd-' . esc_attr($backdrop) : ''; ?>"
     role="dialog" aria-modal="true" aria-labelledby="lrob-cc-title" aria-describedby="lrob-cc-desc" hidden>
    <div class="lrob-cc-backdrop" data-lrob-cc-action="close" tabindex="-1" aria-hidden="true"></div>
    <div class="lrob-cc-inner" role="document">
        <?php $has_logo = $logo !== ''; $placement = $has_logo ? ($logo_placement ?? 'header') : ''; ?>
        <div class="lrob-cc-header">
            <?php if ($placement === 'header') : ?>
                <img class="lrob-cc-logo" src="<?php echo esc_url($logo); ?>" alt="" />
            <?php endif; ?>
            <h2 id="lrob-cc-title" class="lrob-cc-title"><?php echo esc_html($texts['header']); ?></h2>
            <?php if ($continue_pos === 'near-close') : ?>
                <span class="lrob-cc-continue-wrap lrob-cc-continue-near"><?php echo $continue_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <?php endif; ?>
            <button type="button" class="lrob-cc-close" data-lrob-cc-action="close"
                    aria-label="<?php echo esc_attr($texts['close']); ?>">&times;</button>
        </div>

        <?php if ($placement === 'below') : ?>
            <div class="lrob-cc-logo-row lrob-cc-logo-below"><img class="lrob-cc-logo" src="<?php echo esc_url($logo); ?>" alt="" /></div>
        <?php endif; ?>

        <?php if ($continue_pos === 'top') : ?>
            <div class="lrob-cc-continue-wrap lrob-cc-continue-top<?php echo $continue_align_cls; ?>"><?php echo $continue_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
        <?php endif; ?>

        <div id="lrob-cc-desc" class="lrob-cc-message"><?php echo wp_kses_post(wpautop($texts['message'])); ?></div>

        <?php if (!empty($disclosure_required) || !empty($disclosure_optional)) :
            $open_attr = !empty($disclosure_open) ? ' open' : '';
            // Precise listings — what visitors actually accept/decline. Required cookies
            // first; no generic descriptions (the cookie names are the useful part).
            $svc_list = static function (array $items): string {
                $h = '<ul class="lrob-cc-disc-svc">';
                foreach ($items as $s) {
                    $h .= '<li>' . esc_html($s) . '</li>';
                }
                return $h . '</ul>';
            };
        ?>
            <div class="lrob-cc-disclosures">
                <?php if (!empty($disclosure_required)) : ?>
                    <details class="lrob-cc-disclosure"<?php echo $open_attr; ?>><summary><?php echo esc_html($texts['disclosure_mandatory']); ?></summary>
                        <?php if (!empty($sources['functional'])) : ?>
                            <?php echo $svc_list($sources['functional']); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        <?php else : ?>
                            <p class="lrob-cc-disc-note"><?php echo esc_html($labels['functional']['desc']); ?></p>
                        <?php endif; ?>
                    </details>
                <?php endif; ?>
                <?php if (!empty($disclosure_optional) && !empty($optional)) : ?>
                    <details class="lrob-cc-disclosure"<?php echo $open_attr; ?>><summary><?php echo esc_html($texts['disclosure']); ?></summary>
                        <ul class="lrob-cc-disc-list"><?php foreach ($optional as $cat) : ?>
                            <li><strong><?php echo esc_html($labels[$cat]['title']); ?></strong><?php
                                if (!empty($sources[$cat])) {
                                    echo $svc_list($sources[$cat]); // phpcs:ignore WordPress.Security.EscapeOutput
                                } ?></li>
                        <?php endforeach; ?></ul>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div id="lrob-cc-categories" class="lrob-cc-categories"<?php echo $collapsed ? ' hidden' : ''; ?>>
            <div class="lrob-cc-cat lrob-cc-cat-functional" data-cat-slug="functional">
                <div class="lrob-cc-cat-head">
                    <span class="lrob-cc-cat-title"><?php echo esc_html($labels['functional']['title']); ?></span>
                    <span class="lrob-cc-cat-always"><?php echo esc_html($texts['always']); ?></span>
                </div>
                <div class="lrob-cc-cat-desc"><?php echo esc_html($labels['functional']['desc']); ?></div>
                <?php if (!empty($show_sources) && !empty($sources['functional'])) : ?>
                    <details class="lrob-cc-cat-sources"><summary><?php esc_html_e('What this includes', 'lrob-cookie-consent'); ?></summary>
                        <ul><?php foreach ($sources['functional'] as $s) : ?><li><?php echo esc_html($s); ?></li><?php endforeach; ?></ul>
                    </details>
                <?php endif; ?>
            </div>

            <?php foreach ($optional as $cat) : ?>
                <div class="lrob-cc-cat lrob-cc-cat-<?php echo esc_attr($cat); ?>" data-cat-slug="<?php echo esc_attr($cat); ?>">
                    <label class="lrob-cc-cat-head">
                        <span class="lrob-cc-cat-title"><?php echo esc_html($labels[$cat]['title']); ?></span>
                        <span class="lrob-cc-switch">
                            <input type="checkbox" data-category="<?php echo esc_attr($cat); ?>"
                                   aria-label="<?php
                                        /* translators: %s: cookie category name. */
                                        printf(esc_attr__('Allow %s cookies', 'lrob-cookie-consent'), esc_attr($labels[$cat]['title']));
                                   ?>" />
                            <span class="lrob-cc-switch-ui" aria-hidden="true"></span>
                        </span>
                    </label>
                    <div class="lrob-cc-cat-desc"><?php echo esc_html($labels[$cat]['desc']); ?></div>
                    <?php if (!empty($show_sources) && !empty($sources[$cat])) : ?>
                        <details class="lrob-cc-cat-sources"><summary><?php esc_html_e('What this includes', 'lrob-cookie-consent'); ?></summary>
                            <ul><?php foreach ($sources[$cat] as $s) : ?><li><?php echo esc_html($s); ?></li><?php endforeach; ?></ul>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="lrob-cc-buttons">
            <?php foreach ($button_order as $b) : ?>
                <?php if ($b === 'accept' && $show_accept) : ?>
                    <button type="button" class="lrob-cc-btn lrob-cc-btn-accept" data-lrob-cc-action="accept-all">
                        <?php echo esc_html($texts['accept']); ?>
                    </button>
                <?php elseif ($b === 'deny' && $show_deny && $deny_style === 'button') : ?>
                    <button type="button" class="lrob-cc-btn lrob-cc-btn-deny" data-lrob-cc-action="deny-all">
                        <?php echo esc_html($texts['deny']); ?>
                    </button>
                <?php elseif ($b === 'customize' && $collapsed && $show_customize) : ?>
                    <button type="button" class="lrob-cc-btn lrob-cc-btn-customize" data-lrob-cc-action="customize"
                            aria-expanded="false" aria-controls="lrob-cc-categories">
                        <?php echo esc_html($texts['customize']); ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php // Save is mandatory and contextual: hidden until the categories show (revealed by Customize, or always in the expanded layout). ?>
            <button type="button" class="lrob-cc-btn lrob-cc-btn-save" data-lrob-cc-action="save"<?php echo $collapsed ? ' hidden' : ''; ?>>
                <?php echo esc_html($texts['save']); ?>
            </button>
        </div>

        <?php if ($continue_pos === 'under-buttons') : ?>
            <div class="lrob-cc-continue-wrap lrob-cc-continue-under-buttons<?php echo $continue_align_cls; ?>"><?php echo $continue_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
        <?php endif; ?>

        <?php if (!empty($footer_links) || !empty($watermark) || $placement === 'footer') : ?>
            <div class="lrob-cc-footer">
                <?php if ($placement === 'footer') : ?>
                    <span class="lrob-cc-logo-footer"><img class="lrob-cc-logo" src="<?php echo esc_url($logo); ?>" alt="" /></span>
                <?php endif; ?>
                <?php foreach ($footer_links as $link) : ?>
                    <a href="<?php echo esc_url((string) $link['url']); ?>"><?php echo esc_html((string) $link['label']); ?></a>
                <?php endforeach; ?>
                <?php if (!empty($watermark)) : ?>
                    <a class="lrob-cc-watermark" href="https://www.lrob.fr/" target="_blank" rel="noopener"><?php esc_html_e('Cookie Consent by LRob', 'lrob-cookie-consent'); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($continue_pos === 'under-box') : ?>
        <div class="lrob-cc-continue-wrap lrob-cc-continue-under-box<?php echo $continue_align_cls; ?>"><?php echo $continue_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
    <?php endif; ?>
</div>
