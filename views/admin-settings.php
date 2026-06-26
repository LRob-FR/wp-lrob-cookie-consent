<?php
/**
 * Admin settings page shell. Rendered by Admin\SettingsPage::render().
 *
 * @var array<string,mixed> $o
 * @var array<string,string> $texts
 * @var array<string,array{title:string,desc:string}> $labels
 * @var list<string> $optional
 * @var list<array<string,mixed>> $text_presets
 * @var list<array<string,mixed>> $color_presets
 * @var list<array{label:string,pattern:string,category:string,service:string}> $services
 * @var \LRob\CookieConsent\Consent\LogRepository $log
 * @var string $option
 */

if (!defined('ABSPATH')) {
    exit;
}

$name = static fn (string $key): string => esc_attr($option . '[' . $key . ']');
$checked = static fn (string $key) => checked(!empty($o[$key]), true, false);

/** Segmented radio button group. */
$seg = static function (string $key, array $choices) use ($o, $option, $name): void {
    echo '<div class="lrob-cc-segmented" role="radiogroup">';
    foreach ($choices as $value => $label) {
        $is = (string) $o[$key] === (string) $value;
        printf(
            '<label class="lrob-cc-seg%s"><input type="radio" name="%s" value="%s" data-field="%s" %s /><span>%s</span></label>',
            $is ? ' is-active' : '',
            $name($key),
            esc_attr((string) $value),
            esc_attr($key),
            checked($is, true, false),
            esc_html((string) $label)
        );
    }
    echo '</div>';
};

// Reusable hint/callout box.
$hint = static function (string $text, string $kind = 'info'): void {
    printf('<p class="lrob-cc-hint lrob-cc-hint-%s">%s</p>', esc_attr($kind), esc_html($text));
};

// Small "?" tooltip — shows on hover, keyboard focus, or tap. Keeps long
// explanations off the page (debloat). Use everywhere instead of paragraphs.
$help = static function (string $text): void {
    printf(
        '<span class="lrob-cc-tip" tabindex="0" role="note" aria-label="%s"><span class="lrob-cc-tip-i" aria-hidden="true">?</span><span class="lrob-cc-tip-bubble">%s</span></span>',
        esc_attr__('Help', 'lrob-cookie-consent'),
        esc_html($text)
    );
};

$configured = trim((string) $o['block_rules']) !== '' || (is_array($o['inline_scripts']) && $o['inline_scripts'] !== []);
?>
<div class="wrap lrob-cc-admin">
    <h1><?php esc_html_e('LRob Cookie Consent', 'lrob-cookie-consent'); ?></h1>

    <?php if (isset($_GET['purged'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Consent log cleared.', 'lrob-cookie-consent'); ?></p></div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper lrob-cc-tabs">
        <a href="#general" class="nav-tab nav-tab-active" data-tab="general"><?php esc_html_e('General', 'lrob-cookie-consent'); ?></a>
        <a href="#banner" class="nav-tab" data-tab="banner"><?php esc_html_e('Banner', 'lrob-cookie-consent'); ?></a>
        <a href="#cookies" class="nav-tab" data-tab="cookies"><?php esc_html_e('Cookies', 'lrob-cookie-consent'); ?></a>
        <a href="#log" class="nav-tab" data-tab="log"><?php esc_html_e('Log', 'lrob-cookie-consent'); ?></a>
    </h2>

    <form method="post" action="options.php">
        <?php settings_fields('lrob_cc_group'); ?>

        <!-- GENERAL -->
        <section class="lrob-cc-panel" data-panel="general">
            <?php if (!$configured) : ?>
                <div class="lrob-cc-welcome">
                    <div>
                        <h2><?php esc_html_e('Set up cookie consent in a minute', 'lrob-cookie-consent'); ?></h2>
                        <p><?php esc_html_e('Answer a few quick questions and the wizard configures the banner look, blocking rules and proof of consent for you. You can fine-tune everything in the tabs afterwards.', 'lrob-cookie-consent'); ?></p>
                    </div>
                    <button type="button" class="button button-primary button-hero lrob-cc-wizard-open"><?php esc_html_e('Run setup wizard', 'lrob-cookie-consent'); ?></button>
                </div>
            <?php else : ?>
                <p class="lrob-cc-welcome-mini"><button type="button" class="button lrob-cc-wizard-open"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('Re-run setup wizard', 'lrob-cookie-consent'); ?></button></p>
            <?php endif; ?>

            <table class="form-table" role="presentation">
                <tr><th><?php esc_html_e('Cookie consent', 'lrob-cookie-consent'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo $name('enabled'); ?>" value="1" <?php echo $checked('enabled'); ?> /> <?php esc_html_e('Show the consent banner and block configured scripts/iframes until the visitor agrees', 'lrob-cookie-consent'); ?></label>
                        <p class="description"><?php esc_html_e('The banner only appears once you have added at least one block rule or inline script (Blocking tab).', 'lrob-cookie-consent'); ?></p></td></tr>

                <tr><th><?php esc_html_e('Consent model', 'lrob-cookie-consent'); ?></th>
                    <td><input type="text" value="<?php esc_attr_e('Opt-in (EU / GDPR)', 'lrob-cookie-consent'); ?>" disabled />
                        <p class="description"><?php esc_html_e('Opt-in only in v1: nothing optional loads until the visitor agrees.', 'lrob-cookie-consent'); ?></p></td></tr>

                <tr><th><?php esc_html_e('Consent duration (days)', 'lrob-cookie-consent'); ?></th>
                    <td><input type="number" min="1" name="<?php echo $name('cookie_days'); ?>" value="<?php echo esc_attr((string) $o['cookie_days']); ?>" /></td></tr>

                <tr><th><?php esc_html_e('Show to logged-in users', 'lrob-cookie-consent'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo $name('show_to_logged_in'); ?>" value="1" <?php echo $checked('show_to_logged_in'); ?> /> <?php esc_html_e('Run consent + blocking for logged-in users too (off = logged-in users see the unmodified site)', 'lrob-cookie-consent'); ?></label></td></tr>

                <tr><th><?php esc_html_e('Respect DNT / GPC', 'lrob-cookie-consent'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo $name('respect_dnt'); ?>" value="1" <?php echo $checked('respect_dnt'); ?> /> <?php esc_html_e('Treat a Do-Not-Track / Global-Privacy-Control browser signal as a refusal of optional cookies', 'lrob-cookie-consent'); ?></label>
                        <p><label><input type="checkbox" name="<?php echo $name('dnt_hide_banner'); ?>" value="1" <?php echo $checked('dnt_hide_banner'); ?> /> <?php esc_html_e('…and hide the banner entirely for those visitors (otherwise still ask)', 'lrob-cookie-consent'); ?></label></p></td></tr>
            </table>

            <h3><?php esc_html_e('Proof of consent', 'lrob-cookie-consent'); ?></h3>
            <table class="form-table" role="presentation">
                <tr><th><?php esc_html_e('Store proof of consent', 'lrob-cookie-consent'); ?></th>
                    <td><label><input type="checkbox" data-field="log_consent" name="<?php echo $name('log_consent'); ?>" value="1" <?php echo $checked('log_consent'); ?> /> <?php esc_html_e('Record each consent decision in the database (advised for GDPR accountability)', 'lrob-cookie-consent'); ?></label></td></tr>

                <tr><th><?php esc_html_e('IP address', 'lrob-cookie-consent'); ?> <?php $help(__('A salted hash is irreversible but still lets you count unique consents. Your server already logs IPs; GDPR requires disclosing it, not omitting it.', 'lrob-cookie-consent')); ?></th>
                    <td>
                        <?php foreach (['hashed' => __('Store a salted hash (recommended)', 'lrob-cookie-consent'), 'full' => __('Store the full IP', 'lrob-cookie-consent')] as $v => $l) : ?>
                            <label class="lrob-cc-radio-line"><input type="radio" name="<?php echo $name('ip_storage'); ?>" value="<?php echo esc_attr($v); ?>" <?php checked($o['ip_storage'], $v); ?> /> <?php echo esc_html($l); ?></label>
                        <?php endforeach; ?></td></tr>

                <tr><th><?php esc_html_e('Store user-agent', 'lrob-cookie-consent'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo $name('store_user_agent'); ?>" value="1" <?php echo $checked('store_user_agent'); ?> /> <?php esc_html_e('Record the browser user-agent string', 'lrob-cookie-consent'); ?></label></td></tr>

                <tr><th><?php esc_html_e('Store WP user', 'lrob-cookie-consent'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo $name('store_wp_user'); ?>" value="1" <?php echo $checked('store_wp_user'); ?> /> <?php esc_html_e('Record the logged-in user (guests are never identified)', 'lrob-cookie-consent'); ?></label></td></tr>

                <tr><th><?php esc_html_e('Log retention (days)', 'lrob-cookie-consent'); ?></th>
                    <td><input type="number" min="0" name="<?php echo $name('log_retention_days'); ?>" value="<?php echo esc_attr((string) $o['log_retention_days']); ?>" /> <span class="description"><?php esc_html_e('0 = keep forever', 'lrob-cookie-consent'); ?></span></td></tr>
            </table>
        </section>

        <!-- BANNER -->
        <section class="lrob-cc-panel" data-panel="banner" hidden>
            <div class="lrob-cc-banner-layout">
                <div class="lrob-cc-banner-fields">
                    <h3><?php esc_html_e('Text', 'lrob-cookie-consent'); ?></h3>
                    <p class="lrob-cc-field-label"><?php esc_html_e('Presets', 'lrob-cookie-consent'); ?></p>
                    <div class="lrob-cc-preset-row" data-preset-group="text">
                        <?php foreach ($text_presets as $p) : ?>
                            <button type="button" class="button lrob-cc-preset" data-preset-id="<?php echo esc_attr((string) $p['id']); ?>"><?php echo esc_html((string) $p['label']); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="lrob-cc-text-preset" data-field="text_preset" name="<?php echo $name('text_preset'); ?>" value="<?php echo esc_attr((string) $o['text_preset']); ?>" />
                    <table class="form-table" role="presentation">
                        <tr><th><?php esc_html_e('Header', 'lrob-cookie-consent'); ?></th>
                            <td><input type="text" class="regular-text" data-field="text_header" name="<?php echo $name('text_header'); ?>" value="<?php echo esc_attr((string) $o['text_header']); ?>" placeholder="<?php echo esc_attr($texts['header']); ?>" /></td></tr>
                        <tr><th><?php esc_html_e('Message', 'lrob-cookie-consent'); ?></th>
                            <td><textarea rows="3" class="large-text" data-field="text_message" name="<?php echo $name('text_message'); ?>" placeholder="<?php echo esc_attr($texts['message']); ?>"><?php echo esc_textarea((string) $o['text_message']); ?></textarea></td></tr>
                        <tr><th><?php esc_html_e('Accept button', 'lrob-cookie-consent'); ?></th>
                            <td><input type="text" data-field="text_accept" name="<?php echo $name('text_accept'); ?>" value="<?php echo esc_attr((string) $o['text_accept']); ?>" placeholder="<?php echo esc_attr($texts['accept']); ?>" /></td></tr>
                        <tr><th><?php esc_html_e('Deny button', 'lrob-cookie-consent'); ?></th>
                            <td><label class="lrob-cc-btn-toggle"><input type="checkbox" data-field="show_deny" data-toggle-text="text_deny" name="<?php echo $name('show_deny'); ?>" value="1" <?php echo $checked('show_deny'); ?> /> <?php esc_html_e('Show', 'lrob-cookie-consent'); ?></label>
                                <input type="text" data-field="text_deny" name="<?php echo $name('text_deny'); ?>" value="<?php echo esc_attr((string) $o['text_deny']); ?>" placeholder="<?php echo esc_attr($texts['deny']); ?>"<?php echo empty($o['show_deny']) ? ' readonly class="lrob-cc-readonly"' : ''; ?> /></td></tr>
                        <tr><th><?php esc_html_e('Save button', 'lrob-cookie-consent'); ?></th>
                            <td><label class="lrob-cc-btn-toggle"><input type="checkbox" data-field="show_save" data-toggle-text="text_save" name="<?php echo $name('show_save'); ?>" value="1" <?php echo $checked('show_save'); ?> /> <?php esc_html_e('Show', 'lrob-cookie-consent'); ?></label>
                                <input type="text" data-field="text_save" name="<?php echo $name('text_save'); ?>" value="<?php echo esc_attr((string) $o['text_save']); ?>" placeholder="<?php echo esc_attr($texts['save']); ?>"<?php echo empty($o['show_save']) ? ' readonly class="lrob-cc-readonly"' : ''; ?> /></td></tr>
                        <tr><th><?php esc_html_e('Logo', 'lrob-cookie-consent'); ?></th>
                            <td>
                                <div class="lrob-cc-logo-field">
                                    <input type="hidden" id="lrob-cc-logo-input" data-field="logo" name="<?php echo $name('logo'); ?>" value="<?php echo esc_attr((string) $o['logo']); ?>" />
                                    <img id="lrob-cc-logo-preview" src="<?php echo esc_url((string) $o['logo']); ?>" alt="" <?php echo $o['logo'] === '' ? 'hidden' : ''; ?> />
                                    <button type="button" class="button" id="lrob-cc-logo-select"><?php esc_html_e('Select logo', 'lrob-cookie-consent'); ?></button>
                                    <button type="button" class="button-link lrob-cc-logo-remove" id="lrob-cc-logo-remove" <?php echo $o['logo'] === '' ? 'hidden' : ''; ?>><?php esc_html_e('Remove', 'lrob-cookie-consent'); ?></button>
                                </div>
                            </td></tr>
                    </table>

                    <h3><?php esc_html_e('Layout', 'lrob-cookie-consent'); ?></h3>
                    <p>
                        <label class="lrob-cc-check"><input type="checkbox" data-field="categories_collapsed" name="<?php echo $name('categories_collapsed'); ?>" value="1" <?php echo $checked('categories_collapsed'); ?> /> <?php esc_html_e('Hide category options behind a “Customize” button (simpler banner)', 'lrob-cookie-consent'); ?></label>
                        <label class="lrob-cc-check"><input type="checkbox" name="<?php echo $name('revisit_button'); ?>" value="1" <?php echo $checked('revisit_button'); ?> /> <?php esc_html_e('Show a floating “Manage cookies” button after a decision', 'lrob-cookie-consent'); ?></label>
                    </p>
                    <p>
                        <label><?php esc_html_e('“Manage cookies” button label', 'lrob-cookie-consent'); ?>
                            <input type="text" data-field="revisit_text" name="<?php echo $name('revisit_text'); ?>" value="<?php echo esc_attr((string) $o['revisit_text']); ?>" placeholder="<?php esc_attr_e('Manage cookies', 'lrob-cookie-consent'); ?>" />
                        </label>
                    </p>

                    <p class="lrob-cc-field-label"><?php esc_html_e('Footer links', 'lrob-cookie-consent'); ?> <?php $help(__('Add links shown at the bottom of the banner — e.g. your Privacy Policy. You can add several.', 'lrob-cookie-consent')); ?></p>
                    <div id="lrob-cc-links" data-name="<?php echo esc_attr($option); ?>">
                        <?php foreach ((array) $o['footer_links'] as $i => $lnk) : if (!is_array($lnk)) { continue; } ?>
                            <div class="lrob-cc-link-row">
                                <input type="text" name="<?php echo esc_attr($option); ?>[footer_links][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr((string) ($lnk['label'] ?? '')); ?>" placeholder="<?php esc_attr_e('Label', 'lrob-cookie-consent'); ?>" />
                                <input type="url" name="<?php echo esc_attr($option); ?>[footer_links][<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr((string) ($lnk['url'] ?? '')); ?>" placeholder="https://…" />
                                <button type="button" class="button lrob-cc-link-remove" aria-label="<?php esc_attr_e('Remove', 'lrob-cookie-consent'); ?>">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button" id="lrob-cc-link-add"><?php esc_html_e('Add link', 'lrob-cookie-consent'); ?></button>

                    <p class="lrob-cc-field-label"><?php esc_html_e('Position', 'lrob-cookie-consent'); ?></p>
                    <?php $seg('position', [
                        'top-left' => __('Top left', 'lrob-cookie-consent'),
                        'top' => __('Top', 'lrob-cookie-consent'),
                        'top-right' => __('Top right', 'lrob-cookie-consent'),
                        'center' => __('Center', 'lrob-cookie-consent'),
                        'bottom-left' => __('Bottom left', 'lrob-cookie-consent'),
                        'bottom' => __('Bottom', 'lrob-cookie-consent'),
                        'bottom-right' => __('Bottom right', 'lrob-cookie-consent'),
                    ]); ?>

                    <h3><?php esc_html_e('Appearance', 'lrob-cookie-consent'); ?></h3>
                    <p class="lrob-cc-field-label"><?php esc_html_e('Colors', 'lrob-cookie-consent'); ?> <?php $help(__('Auto follows your theme via WordPress global-style tokens. The back-office preview may not match your theme exactly — always check the result on the front end.', 'lrob-cookie-consent')); ?></p>
                    <?php $seg('theme', [
                        'auto' => __('Auto', 'lrob-cookie-consent'),
                        'light' => __('Light', 'lrob-cookie-consent'),
                        'dark' => __('Dark', 'lrob-cookie-consent'),
                        'midnight' => __('Midnight', 'lrob-cookie-consent'),
                        'ocean' => __('Ocean', 'lrob-cookie-consent'),
                        'sand' => __('Sand', 'lrob-cookie-consent'),
                        'custom' => __('Custom', 'lrob-cookie-consent'),
                    ]); ?>

                    <div class="lrob-cc-custom-colors" data-theme-only="custom">
                        <?php
                        $custom_presets = array_filter($color_presets, static fn ($p) => ($p['options']['theme'] ?? '') === 'custom');
                        if ($custom_presets) : ?>
                            <p class="lrob-cc-field-label"><?php esc_html_e('Custom palettes', 'lrob-cookie-consent'); ?></p>
                            <div class="lrob-cc-preset-row" data-preset-group="colors">
                                <?php foreach ($custom_presets as $p) : ?>
                                    <button type="button" class="button lrob-cc-preset" data-preset-id="<?php echo esc_attr((string) $p['id']); ?>"><?php echo esc_html((string) $p['label']); ?></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <table class="form-table" role="presentation">
                            <?php
                            $color_fields = [
                                'color_bg' => __('Background', 'lrob-cookie-consent'),
                                'color_text' => __('Text', 'lrob-cookie-consent'),
                                'color_title' => __('Title', 'lrob-cookie-consent'),
                                'color_border' => __('Border', 'lrob-cookie-consent'),
                                'color_btn_bg' => __('Accept button bg', 'lrob-cookie-consent'),
                                'color_btn_text' => __('Accept button text', 'lrob-cookie-consent'),
                                'color_btn_deny_bg' => __('Deny/Save button bg', 'lrob-cookie-consent'),
                                'color_btn_deny_text' => __('Deny/Save button text', 'lrob-cookie-consent'),
                            ];
                            foreach ($color_fields as $key => $label) : ?>
                                <tr><th><?php echo esc_html($label); ?></th>
                                    <td><input type="text" class="lrob-cc-color" data-field="<?php echo esc_attr($key); ?>" name="<?php echo $name($key); ?>" value="<?php echo esc_attr((string) $o[$key]); ?>" /></td></tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <p class="lrob-cc-field-label"><?php esc_html_e('Popup width', 'lrob-cookie-consent'); ?></p>
                    <?php $seg('popup_size', ['small' => __('Small', 'lrob-cookie-consent'), 'medium' => __('Medium', 'lrob-cookie-consent'), 'large' => __('Large', 'lrob-cookie-consent')]); ?>

                    <p class="lrob-cc-field-label"><?php esc_html_e('Density (spacing)', 'lrob-cookie-consent'); ?></p>
                    <?php $seg('density', ['compact' => __('Compact', 'lrob-cookie-consent'), 'cozy' => __('Cozy', 'lrob-cookie-consent'), 'comfortable' => __('Comfortable', 'lrob-cookie-consent')]); ?>

                    <p class="lrob-cc-field-label"><?php esc_html_e('Font size', 'lrob-cookie-consent'); ?></p>
                    <?php $seg('font_size', ['small' => __('Small', 'lrob-cookie-consent'), 'medium' => __('Medium', 'lrob-cookie-consent'), 'large' => __('Large', 'lrob-cookie-consent')]); ?>

                    <p class="lrob-cc-field-label"><?php esc_html_e('Corners', 'lrob-cookie-consent'); ?></p>
                    <?php $seg('shape', ['square' => __('Square', 'lrob-cookie-consent'), 'rounded' => __('Rounded', 'lrob-cookie-consent'), 'pill' => __('Pill', 'lrob-cookie-consent')]); ?>

                    <?php
                    $align_choices = ['left' => __('Left', 'lrob-cookie-consent'), 'center' => __('Center', 'lrob-cookie-consent'), 'right' => __('Right', 'lrob-cookie-consent')];
                    ?>
                    <p class="lrob-cc-field-label"><?php esc_html_e('Title alignment', 'lrob-cookie-consent'); ?></p>
                    <?php $seg('align_title', $align_choices); ?>
                    <p class="lrob-cc-field-label"><?php esc_html_e('Text alignment', 'lrob-cookie-consent'); ?></p>
                    <?php $seg('align_text', $align_choices); ?>
                    <p class="lrob-cc-field-label"><?php esc_html_e('Buttons alignment', 'lrob-cookie-consent'); ?></p>
                    <?php $seg('align_buttons', $align_choices); ?>
                </div>

                <div class="lrob-cc-banner-preview">
                    <p class="lrob-cc-preview-label"><?php esc_html_e('Live preview', 'lrob-cookie-consent'); ?></p>
                    <div class="lrob-cc-preview-stage">
                        <div id="lrob-cc-preview" class="lrob-cc-banner">
                            <div class="lrob-cc-inner" role="document">
                                <div class="lrob-cc-header">
                                    <h2 class="lrob-cc-title" data-preview="header"></h2>
                                </div>
                                <div class="lrob-cc-message" data-preview="message"></div>
                                <div class="lrob-cc-categories" data-preview="cats">
                                    <?php foreach ($optional as $cat) : ?>
                                        <div class="lrob-cc-cat"><div class="lrob-cc-cat-head"><span class="lrob-cc-cat-title"><?php echo esc_html($labels[$cat]['title']); ?></span><span class="lrob-cc-switch"><span class="lrob-cc-switch-ui"></span></span></div></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="lrob-cc-buttons">
                                    <button type="button" class="lrob-cc-btn lrob-cc-btn-accept" data-preview="accept"></button>
                                    <button type="button" class="lrob-cc-btn lrob-cc-btn-deny" data-preview="deny"></button>
                                    <button type="button" class="lrob-cc-btn lrob-cc-btn-customize" data-preview="customize"><?php echo esc_html($texts['customize']); ?></button>
                                    <button type="button" class="lrob-cc-btn lrob-cc-btn-save" data-preview="save"></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- COOKIES -->
        <section class="lrob-cc-panel" data-panel="cookies" hidden>
            <h3><?php esc_html_e('Categories', 'lrob-cookie-consent'); ?> <?php $help(__('Functional cookies (WordPress login, cart, CSRF/security tokens) are always allowed — they cannot be blocked without breaking the site. Below are the optional categories visitors can accept or refuse. Leave a label blank to use the built-in default.', 'lrob-cookie-consent')); ?></h3>
            <div id="lrob-cc-cats" data-name="<?php echo esc_attr($option); ?>">
                <div class="lrob-cc-cat-row is-functional">
                    <strong><?php echo esc_html($labels['functional']['title']); ?></strong>
                    <span class="description"><?php esc_html_e('Always allowed (cannot be disabled)', 'lrob-cookie-consent'); ?></span>
                </div>
                <?php foreach ($category_rows as $i => $c) : ?>
                    <div class="lrob-cc-cat-row">
                        <input type="text" class="lrob-cc-cat-slug" name="<?php echo esc_attr($option); ?>[categories][<?php echo (int) $i; ?>][slug]" value="<?php echo esc_attr($c['slug']); ?>" placeholder="<?php esc_attr_e('slug', 'lrob-cookie-consent'); ?>" />
                        <input type="text" name="<?php echo esc_attr($option); ?>[categories][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr($c['label']); ?>" placeholder="<?php echo esc_attr($c['ph_label']); ?>" />
                        <input type="text" class="lrob-cc-cat-desc" name="<?php echo esc_attr($option); ?>[categories][<?php echo (int) $i; ?>][desc]" value="<?php echo esc_attr($c['desc']); ?>" placeholder="<?php echo esc_attr($c['ph_desc']); ?>" />
                        <button type="button" class="button lrob-cc-cat-remove" aria-label="<?php esc_attr_e('Remove', 'lrob-cookie-consent'); ?>">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="lrob-cc-cat-add"><?php esc_html_e('Add category', 'lrob-cookie-consent'); ?></button>

            <h3><?php esc_html_e('Blocking', 'lrob-cookie-consent'); ?></h3>
            <table class="form-table" role="presentation">
                <tr><th><?php esc_html_e('Block method', 'lrob-cookie-consent'); ?> <?php $help(__('Full-page scan catches hardcoded scripts and iframes too; enqueued-only is lighter but misses them.', 'lrob-cookie-consent')); ?></th>
                    <td><?php $seg('block_method', ['full' => __('Full-page scan', 'lrob-cookie-consent'), 'enqueued' => __('Enqueued only', 'lrob-cookie-consent')]); ?></td></tr>
                <tr><th><?php esc_html_e('Block iframes', 'lrob-cookie-consent'); ?> <?php $help(__('Turning this off may load third-party cookies before consent — a GDPR risk.', 'lrob-cookie-consent')); ?></th>
                    <td><label class="lrob-cc-check"><input type="checkbox" name="<?php echo $name('block_iframes'); ?>" value="1" <?php echo $checked('block_iframes'); ?> /> <?php esc_html_e('Neutralise matching iframes until consent', 'lrob-cookie-consent'); ?></label></td></tr>
                <tr><th><?php esc_html_e('Re-prompt on change', 'lrob-cookie-consent'); ?> <?php $help(__('Default: only re-ask when the set of active categories changes. Enable to re-ask on any block-rule edit.', 'lrob-cookie-consent')); ?></th>
                    <td><label class="lrob-cc-check"><input type="checkbox" name="<?php echo $name('reprompt_on_rule_change'); ?>" value="1" <?php echo $checked('reprompt_on_rule_change'); ?> /> <?php esc_html_e('Re-ask on any rule change', 'lrob-cookie-consent'); ?></label></td></tr>
            </table>

            <h3><?php esc_html_e('What to block', 'lrob-cookie-consent'); ?> <?php $help(__('Scanning requests your own public pages, one at a time, to find third-party scripts and embeds. It runs as an anonymous visitor (admin/member-only cookies are never touched) and cannot see trackers injected later by JavaScript, e.g. via Tag Manager.', 'lrob-cookie-consent')); ?></h3>

            <div class="lrob-cc-scan">

                <p class="lrob-cc-field-label"><?php esc_html_e('Scan depth', 'lrob-cookie-consent'); ?></p>
                <div class="lrob-cc-segmented" role="radiogroup">
                    <label class="lrob-cc-seg is-active"><input type="radio" name="lrob-cc-scan-mode" value="simple" checked /><span><?php esc_html_e('Simple — home + a few pages', 'lrob-cookie-consent'); ?></span></label>
                    <label class="lrob-cc-seg"><input type="radio" name="lrob-cc-scan-mode" value="deep" /><span><?php esc_html_e('Deep — more pages', 'lrob-cookie-consent'); ?></span></label>
                </div>
                <p class="lrob-cc-hint lrob-cc-hint-warning" id="lrob-cc-scan-deep-warn" hidden><?php esc_html_e('Deep scan requests many pages from your own server, one after another. On a heavy or poorly-optimised site this adds load — use it sparingly.', 'lrob-cookie-consent'); ?></p>

                <p><button type="button" class="button button-secondary" id="lrob-cc-scan-btn"><?php esc_html_e('Scan my site', 'lrob-cookie-consent'); ?></button></p>
                <div id="lrob-cc-scan-progress" hidden><progress id="lrob-cc-scan-bar" max="100" value="0"></progress> <span id="lrob-cc-scan-progress-text"></span></div>
                <div id="lrob-cc-scan-results"></div>
            </div>

            <p class="lrob-cc-field-label"><?php esc_html_e('Editor mode', 'lrob-cookie-consent'); ?></p>
            <?php $seg('rules_mode', ['structured' => __('Guided', 'lrob-cookie-consent'), 'raw' => __('Raw text', 'lrob-cookie-consent')]); ?>

            <?php
            // Parse the stored rules into rows for the guided editor.
            $rule_rows = [];
            foreach (preg_split('/\r\n|\r|\n/', (string) $o['block_rules']) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $p = array_map('trim', explode('|', $line));
                $rule_rows[] = ['pattern' => $p[0] ?? '', 'category' => $p[1] ?? '', 'service' => $p[2] ?? ''];
            }
            $cat_options = static function (string $selected) use ($optional, $labels): void {
                foreach ($optional as $cat) {
                    printf('<option value="%s" %s>%s</option>', esc_attr($cat), selected($selected, $cat, false), esc_html($labels[$cat]['title']));
                }
            };
            ?>

            <div class="lrob-cc-rules-structured" data-rules-panel="structured"<?php echo $o['rules_mode'] === 'raw' ? ' hidden' : ''; ?>>
                <p class="description"><?php esc_html_e('Click a common service to add it, or add a custom rule. The pattern is matched against script/iframe URLs and inline script bodies.', 'lrob-cookie-consent'); ?></p>
                <div class="lrob-cc-services">
                    <?php foreach ($services as $svc) : ?>
                        <button type="button" class="button lrob-cc-service"
                                data-pattern="<?php echo esc_attr($svc['pattern']); ?>"
                                data-category="<?php echo esc_attr($svc['category']); ?>"
                                data-service="<?php echo esc_attr($svc['service']); ?>">
                            + <?php echo esc_html($svc['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div id="lrob-cc-rule-rows">
                    <?php foreach ($rule_rows as $r) : ?>
                        <div class="lrob-cc-rule-row">
                            <input type="text" class="lrob-cc-rule-pattern" value="<?php echo esc_attr($r['pattern']); ?>" placeholder="<?php esc_attr_e('pattern (e.g. google-analytics.com)', 'lrob-cookie-consent'); ?>" />
                            <select class="lrob-cc-rule-category"><?php $cat_options($r['category']); ?></select>
                            <input type="text" class="lrob-cc-rule-service" value="<?php echo esc_attr($r['service']); ?>" placeholder="<?php esc_attr_e('Service name', 'lrob-cookie-consent'); ?>" />
                            <button type="button" class="button lrob-cc-rule-remove" aria-label="<?php esc_attr_e('Remove', 'lrob-cookie-consent'); ?>">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" id="lrob-cc-rule-add"><?php esc_html_e('Add custom rule', 'lrob-cookie-consent'); ?></button>
                <template id="lrob-cc-rule-template">
                    <div class="lrob-cc-rule-row">
                        <input type="text" class="lrob-cc-rule-pattern" placeholder="<?php esc_attr_e('pattern (e.g. google-analytics.com)', 'lrob-cookie-consent'); ?>" />
                        <select class="lrob-cc-rule-category"><?php $cat_options(''); ?></select>
                        <input type="text" class="lrob-cc-rule-service" placeholder="<?php esc_attr_e('Service name', 'lrob-cookie-consent'); ?>" />
                        <button type="button" class="button lrob-cc-rule-remove" aria-label="<?php esc_attr_e('Remove', 'lrob-cookie-consent'); ?>">&times;</button>
                    </div>
                </template>
            </div>

            <div class="lrob-cc-rules-raw" data-rules-panel="raw"<?php echo $o['rules_mode'] !== 'raw' ? ' hidden' : ''; ?>>
                <textarea rows="6" class="large-text code" id="lrob-cc-block-rules" name="<?php echo $name('block_rules'); ?>"><?php echo esc_textarea((string) $o['block_rules']); ?></textarea>
                <p class="description"><?php esc_html_e('One rule per line: pattern | category | service name. The category must be one of your configured category slugs.', 'lrob-cookie-consent'); ?></p>
            </div>

            <h3><?php esc_html_e('Inline scripts', 'lrob-cookie-consent'); ?> <?php $help(__('Paste a snippet (GA4, Matomo…) and pick a category — injected inert, runs only after consent, no theme editing. Tip: Matomo cookieless and Cloudflare Turnstile / hCaptcha usually need no blocking, and blocking a CAPTCHA can break forms.', 'lrob-cookie-consent')); ?></h3>
            <div id="lrob-cc-inline-scripts" data-name="<?php echo esc_attr($option); ?>">
                <?php
                $rows = is_array($o['inline_scripts']) ? $o['inline_scripts'] : [];
                foreach ($rows as $i => $row) : ?>
                    <div class="lrob-cc-inline-row">
                        <select name="<?php echo esc_attr($option); ?>[inline_scripts][<?php echo (int) $i; ?>][category]">
                            <?php foreach ($optional as $cat) : ?>
                                <option value="<?php echo esc_attr($cat); ?>" <?php selected($row['category'] ?? '', $cat); ?>><?php echo esc_html($labels[$cat]['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea rows="3" class="large-text code" name="<?php echo esc_attr($option); ?>[inline_scripts][<?php echo (int) $i; ?>][code]"><?php echo esc_textarea((string) ($row['code'] ?? '')); ?></textarea>
                        <button type="button" class="button lrob-cc-inline-remove"><?php esc_html_e('Remove', 'lrob-cookie-consent'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="lrob-cc-inline-add"><?php esc_html_e('Add inline script', 'lrob-cookie-consent'); ?></button>
        </section>

        <?php submit_button(); ?>
    </form>

    <!-- LOG -->
    <section class="lrob-cc-panel" data-panel="log" hidden>
        <?php $stats = $log->decision_counts(); ?>
        <p><?php
            /* translators: %s: number of stored consent records. */
            printf(esc_html__('Stored consent records: %s', 'lrob-cookie-consent'), '<strong>' . esc_html((string) $log->count()) . '</strong>');
        ?></p>
        <div class="lrob-cc-stats">
            <span class="lrob-cc-stat"><strong><?php echo esc_html((string) $stats['accept_all']); ?></strong> <?php esc_html_e('Accepted all', 'lrob-cookie-consent'); ?></span>
            <span class="lrob-cc-stat"><strong><?php echo esc_html((string) $stats['deny_all']); ?></strong> <?php esc_html_e('Denied all', 'lrob-cookie-consent'); ?></span>
            <span class="lrob-cc-stat"><strong><?php echo esc_html((string) $stats['custom']); ?></strong> <?php esc_html_e('Customised', 'lrob-cookie-consent'); ?></span>
        </div>

        <p class="lrob-cc-log-actions">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                <input type="hidden" name="action" value="lrob_cc_export_log" />
                <?php wp_nonce_field('lrob_cc_export_log'); ?>
                <button type="submit" class="button"><?php esc_html_e('Export CSV', 'lrob-cookie-consent'); ?></button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" data-lrob-cc-confirm>
                <input type="hidden" name="action" value="lrob_cc_purge_log" />
                <?php wp_nonce_field('lrob_cc_purge_log'); ?>
                <button type="submit" class="button button-link-delete"><?php esc_html_e('Delete all', 'lrob-cookie-consent'); ?></button>
            </form>
        </p>

        <form method="post" action="<?php echo esc_url(add_query_arg(['page' => 'lrob-cookie-consent', 'tab' => 'log'], admin_url('options-general.php'))); ?>">
            <input type="hidden" name="page" value="lrob-cookie-consent" />
            <?php $log_table->display(); ?>
        </form>

        <h3><?php esc_html_e('Cookie consent versions', 'lrob-cookie-consent'); ?> <?php $help(__('Each consent record links to the exact context shown then: banner text, what each category covers, AND what was blocked under each. Editing any of these creates a new version; older records keep their version. The full snapshot is stored and included in the CSV export, and a version is removed once no record references it.', 'lrob-cookie-consent')); ?></h3>
        <?php if (empty($banner_versions)) : ?>
            <p><?php esc_html_e('No versions recorded yet.', 'lrob-cookie-consent'); ?></p>
        <?php else : foreach ($banner_versions as $v) :
            $snap = is_array($v['snapshot'] ?? null) ? $v['snapshot'] : [];
            $st = is_array($snap['texts'] ?? null) ? $snap['texts'] : [];
            $sc = is_array($snap['categories'] ?? null) ? $snap['categories'] : [];
            $sb = is_array($snap['blocking'] ?? null) ? $snap['blocking'] : [];
            ?>
            <details class="lrob-cc-version" id="lrob-cc-ver-<?php echo esc_attr((string) $v['version_hash']); ?>">
                <summary><code><?php echo esc_html(substr((string) $v['version_hash'], 0, 12)); ?></code> — <?php echo esc_html((string) $v['created_at']); ?> <?php esc_html_e('UTC', 'lrob-cookie-consent'); ?></summary>
                <div class="lrob-cc-version-body">
                    <p><strong><?php esc_html_e('Header', 'lrob-cookie-consent'); ?>:</strong> <?php echo esc_html((string) ($st['header'] ?? '')); ?></p>
                    <p><strong><?php esc_html_e('Message', 'lrob-cookie-consent'); ?>:</strong> <?php echo esc_html((string) ($st['message'] ?? '')); ?></p>
                    <p><strong><?php esc_html_e('Buttons', 'lrob-cookie-consent'); ?>:</strong> <?php echo esc_html(trim(($st['accept'] ?? '') . ' / ' . ($st['deny'] ?? '') . ' / ' . ($st['save'] ?? ''), ' /')); ?></p>
                    <p><strong><?php esc_html_e('Categories & what was blocked', 'lrob-cookie-consent'); ?>:</strong></p>
                    <ul class="lrob-cc-version-cats">
                        <?php foreach ($sc as $slug => $cat) :
                            if ($slug === 'functional') {
                                continue;
                            }
                            $blocked = is_array($sb[$slug] ?? null) ? $sb[$slug] : [];
                            ?>
                            <li>
                                <strong><?php echo esc_html((string) ($cat['title'] ?? $slug)); ?></strong> — <?php echo esc_html((string) ($cat['desc'] ?? '')); ?>
                                <?php if ($blocked) : ?>
                                    <span class="lrob-cc-version-blocked">
                                        <?php
                                        $labels_b = array_map(static function (array $b): string {
                                            $svc = (string) ($b['service'] ?? '');
                                            $pat = (string) ($b['pattern'] ?? '');
                                            return $svc !== '' ? $svc . ' (' . $pat . ')' : $pat;
                                        }, $blocked);
                                        echo esc_html(implode(', ', $labels_b));
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </details>
        <?php endforeach; endif; ?>
    </section>
</div>
