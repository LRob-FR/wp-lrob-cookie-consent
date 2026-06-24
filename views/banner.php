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
            <div class="lrob-cc-cat lrob-cc-cat-functional">
                <div class="lrob-cc-cat-head">
                    <span class="lrob-cc-cat-title"><?php echo esc_html($labels['functional']['title']); ?></span>
                    <span class="lrob-cc-cat-always"><?php echo esc_html($texts['always']); ?></span>
                </div>
                <div class="lrob-cc-cat-desc"><?php echo esc_html($labels['functional']['desc']); ?></div>
            </div>

            <?php foreach ($optional as $cat) : ?>
                <div class="lrob-cc-cat lrob-cc-cat-<?php echo esc_attr($cat); ?>">
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
                </div>
            <?php endforeach; ?>
        </div>

        <div class="lrob-cc-buttons">
            <button type="button" class="lrob-cc-btn lrob-cc-btn-accept" data-lrob-cc-action="accept-all">
                <?php echo esc_html($texts['accept']); ?>
            </button>
            <?php if ($show_deny) : ?>
                <button type="button" class="lrob-cc-btn lrob-cc-btn-deny" data-lrob-cc-action="deny-all">
                    <?php echo esc_html($texts['deny']); ?>
                </button>
            <?php endif; ?>
            <?php if ($collapsed) : ?>
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

        <?php if (!empty($footer_links)) : ?>
            <div class="lrob-cc-footer">
                <?php foreach ($footer_links as $link) : ?>
                    <a href="<?php echo esc_url((string) $link['url']); ?>"><?php echo esc_html((string) $link['label']); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
