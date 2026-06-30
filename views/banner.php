<?php
/**
 * Front banner markup. Rendered by Frontend\Banner::render().
 *
 * @var array<string,string> $texts
 * @var array<string,array{title:string,desc:string}> $labels
 * @var list<string> $optional
 * @var string $position
 * @var bool $show_deny
 * @var bool $show_save
 * @var bool $collapsed
 * @var string $logo
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="lrob-cc-banner" class="lrob-cc-banner lrob-cc-pos-<?php echo esc_attr($position); ?>"
     role="dialog" aria-modal="true" aria-labelledby="lrob-cc-title" aria-describedby="lrob-cc-desc" hidden>
    <div class="lrob-cc-backdrop" data-lrob-cc-action="close" tabindex="-1" aria-hidden="true"></div>
    <div class="lrob-cc-inner" role="document">
        <div class="lrob-cc-header">
            <?php if ($logo !== '') : ?>
                <img class="lrob-cc-logo" src="<?php echo esc_url($logo); ?>" alt="" />
            <?php endif; ?>
            <h2 id="lrob-cc-title" class="lrob-cc-title"><?php echo esc_html($texts['header']); ?></h2>
            <button type="button" class="lrob-cc-close" data-lrob-cc-action="close"
                    aria-label="<?php echo esc_attr($texts['close']); ?>">&times;</button>
        </div>

        <div id="lrob-cc-desc" class="lrob-cc-message"><?php echo wp_kses_post(wpautop($texts['message'])); ?></div>

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
            <?php if ($show_accept) : ?>
                <button type="button" class="lrob-cc-btn lrob-cc-btn-accept" data-lrob-cc-action="accept-all">
                    <?php echo esc_html($texts['accept']); ?>
                </button>
            <?php endif; ?>
            <?php if ($show_deny) : ?>
                <button type="button" class="lrob-cc-btn lrob-cc-btn-deny" data-lrob-cc-action="deny-all">
                    <?php echo esc_html($texts['deny']); ?>
                </button>
            <?php endif; ?>
            <?php if ($collapsed && $show_customize) : ?>
                <button type="button" class="lrob-cc-btn lrob-cc-btn-customize" data-lrob-cc-action="customize"
                        aria-expanded="false" aria-controls="lrob-cc-categories">
                    <?php echo esc_html($texts['customize']); ?>
                </button>
            <?php endif; ?>
            <button type="button" class="lrob-cc-btn lrob-cc-btn-save" data-lrob-cc-action="save"
                    <?php echo ($collapsed || !$show_save) ? 'hidden' : ''; ?>>
                <?php echo esc_html($texts['save']); ?>
            </button>
        </div>

        <?php if (!empty($footer_links) || !empty($watermark)) : ?>
            <div class="lrob-cc-footer">
                <?php foreach ($footer_links as $link) : ?>
                    <a href="<?php echo esc_url((string) $link['url']); ?>"><?php echo esc_html((string) $link['label']); ?></a>
                <?php endforeach; ?>
                <?php if (!empty($watermark)) : ?>
                    <a class="lrob-cc-watermark" href="https://www.lrob.fr/" target="_blank" rel="noopener"><?php esc_html_e('Cookie Consent by LRob', 'lrob-cookie-consent'); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
