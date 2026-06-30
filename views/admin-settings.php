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
// A choice value is either a plain label string, or ['label'=>…, 'svg'=>inline SVG].
$seg = static function (string $key, array $choices) use ($o, $option, $name): void {
    echo '<div class="lrob-cc-segmented" role="radiogroup">';
    foreach ($choices as $value => $label) {
        $is = (string) $o[$key] === (string) $value;
        $svg = is_array($label) ? (string) ($label['svg'] ?? '') : '';
        $text = is_array($label) ? (string) ($label['label'] ?? '') : (string) $label;
        // $svg is hardcoded, trusted markup (no user input) — output as-is.
        $inner = $svg !== ''
            ? $svg . sprintf('<span class="screen-reader-text">%s</span>', esc_html($text))
            : esc_html($text);
        printf(
            '<label class="lrob-cc-seg%s" title="%s"><input type="radio" name="%s" value="%s" data-field="%s" %s /><span>%s</span></label>',
            $is ? ' is-active' : '',
            esc_attr($text),
            $name($key),
            esc_attr((string) $value),
            esc_attr($key),
            checked($is, true, false),
            $inner
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
        <a href="#general" class="nav-tab nav-tab-active" data-tab="general"><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('General', 'lrob-cookie-consent'); ?></a>
        <a href="#banner" class="nav-tab" data-tab="banner"><span class="dashicons dashicons-format-image"></span> <?php esc_html_e('Banner', 'lrob-cookie-consent'); ?></a>
        <a href="#cookies" class="nav-tab" data-tab="cookies"><span class="dashicons dashicons-shield"></span> <?php esc_html_e('Cookies', 'lrob-cookie-consent'); ?></a>
        <a href="#log" class="nav-tab" data-tab="log"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Log', 'lrob-cookie-consent'); ?></a>
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

                <tr><th><?php esc_html_e('Remember a choice for (days)', 'lrob-cookie-consent'); ?> <?php $help(__('How long the visitor’s decision is remembered before the banner asks again — the consent cookie and the proof’s renewal date follow these (separate from the proof log’s retention). As of July 2026, the CNIL advises keeping a refusal for at least 6 months (180 days).', 'lrob-cookie-consent')); ?></th>
                    <td>
                        <label class="lrob-cc-dur"><?php esc_html_e('After accepting', 'lrob-cookie-consent'); ?>
                            <input type="number" min="1" class="lrob-cc-num-default" data-default="<?php echo esc_attr((string) $defaults['accept_days']); ?>" name="<?php echo $name('accept_days'); ?>" value="<?php echo esc_attr((string) $o['accept_days']); ?>" /></label>
                        <label class="lrob-cc-dur"><?php esc_html_e('After refusing', 'lrob-cookie-consent'); ?>
                            <input type="number" min="1" class="lrob-cc-num-default" data-default="<?php echo esc_attr((string) $defaults['deny_days']); ?>" name="<?php echo $name('deny_days'); ?>" value="<?php echo esc_attr((string) $o['deny_days']); ?>" /></label>
                    </td></tr>

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
                    <td><input type="number" min="0" class="lrob-cc-num-default" data-default="<?php echo esc_attr((string) $defaults['log_retention_days']); ?>" name="<?php echo $name('log_retention_days'); ?>" value="<?php echo esc_attr((string) $o['log_retention_days']); ?>" /> <button type="button" class="button lrob-cc-default-btn" data-target="<?php echo $name('log_retention_days'); ?>"><?php esc_html_e('Default', 'lrob-cookie-consent'); ?></button> <span class="description"><?php esc_html_e('0 = keep forever', 'lrob-cookie-consent'); ?></span>
                        <p class="lrob-cc-hint lrob-cc-hint-warning" id="lrob-cc-retention-warn" hidden><?php esc_html_e('Proof of consent should be kept at least as long as the consent lasts (the consent duration above) — otherwise you may delete evidence for consents that are still valid.', 'lrob-cookie-consent'); ?></p></td></tr>

                <tr><th><?php esc_html_e('On uninstall', 'lrob-cookie-consent'); ?> <?php $help(__('Deleting the plugin normally removes everything, including the consent log. Keep it if you may need the proof of consent after uninstalling (legal accountability) — re-installing later will reuse it.', 'lrob-cookie-consent')); ?></th>
                    <td><label><input type="checkbox" name="<?php echo $name('keep_data_on_uninstall'); ?>" value="1" <?php echo $checked('keep_data_on_uninstall'); ?> /> <?php esc_html_e('Keep the consent log (proof of consent) when uninstalling', 'lrob-cookie-consent'); ?></label></td></tr>
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
                            <td><input type="text" class="regular-text" data-field="text_header" name="<?php echo $name('text_header'); ?>" value="<?php echo esc_attr((string) $o['text_header']); ?>" placeholder="<?php echo esc_attr($text_defaults['header']); ?>" /></td></tr>
                        <tr><th><?php esc_html_e('Message', 'lrob-cookie-consent'); ?></th>
                            <td><textarea rows="3" class="large-text" data-field="text_message" name="<?php echo $name('text_message'); ?>" placeholder="<?php echo esc_attr($text_defaults['message']); ?>"><?php echo esc_textarea((string) $o['text_message']); ?></textarea></td></tr>
                        <tr><th><?php esc_html_e('Accept button', 'lrob-cookie-consent'); ?> <?php $help(__('The one-click “accept everything” button. If you hide it, visitors give consent via Customize → Save instead.', 'lrob-cookie-consent')); ?></th>
                            <td><label class="lrob-cc-btn-toggle"><input type="checkbox" data-field="show_accept" data-toggle-text="text_accept" name="<?php echo $name('show_accept'); ?>" value="1" <?php echo $checked('show_accept'); ?> /> <?php esc_html_e('Show', 'lrob-cookie-consent'); ?></label>
                                <input type="text" data-field="text_accept" name="<?php echo $name('text_accept'); ?>" value="<?php echo esc_attr((string) $o['text_accept']); ?>" placeholder="<?php echo esc_attr($text_defaults['accept']); ?>"<?php echo empty($o['show_accept']) ? ' readonly class="lrob-cc-readonly"' : ''; ?> /></td></tr>
                        <tr><th><?php esc_html_e('Deny button', 'lrob-cookie-consent'); ?> <?php $help(__('Refuses every optional category, keeping only strictly-necessary (functional) cookies. If something is genuinely required — a CAPTCHA, a payment script — list it under the functional category so it is referenced but never blocked.', 'lrob-cookie-consent')); ?></th>
                            <td><label class="lrob-cc-btn-toggle"><input type="checkbox" data-field="show_deny" data-toggle-text="text_deny" name="<?php echo $name('show_deny'); ?>" value="1" <?php echo $checked('show_deny'); ?> /> <?php esc_html_e('Show', 'lrob-cookie-consent'); ?></label>
                                <input type="text" data-field="text_deny" name="<?php echo $name('text_deny'); ?>" value="<?php echo esc_attr((string) $o['text_deny']); ?>" placeholder="<?php echo esc_attr($text_defaults['deny']); ?>"<?php echo empty($o['show_deny']) ? ' readonly class="lrob-cc-readonly"' : ''; ?> /></td></tr>
                        <tr id="lrob-cc-refuse-row"><th><?php esc_html_e('Refuse style', 'lrob-cookie-consent'); ?> <?php $help(__('Show refusal as a normal button, or as a subtle “Continue without accepting” link (same effect as closing the banner). A link nudges toward acceptance — note that GDPR/CNIL expect refusing to be as easy as accepting.', 'lrob-cookie-consent')); ?></th>
                            <td><select data-field="deny_style" name="<?php echo $name('deny_style'); ?>">
                                    <option value="button" <?php selected($o['deny_style'], 'button'); ?>><?php esc_html_e('Button', 'lrob-cookie-consent'); ?></option>
                                    <option value="link" <?php selected($o['deny_style'], 'link'); ?>><?php esc_html_e('“Continue without accepting” link', 'lrob-cookie-consent'); ?></option>
                                </select>
                                <span class="lrob-cc-deny-link-opts"<?php echo $o['deny_style'] === 'link' ? '' : ' hidden'; ?>>
                                    <select data-field="deny_link_position" name="<?php echo $name('deny_link_position'); ?>">
                                        <option value="under-buttons" <?php selected($o['deny_link_position'], 'under-buttons'); ?>><?php esc_html_e('Under the buttons', 'lrob-cookie-consent'); ?></option>
                                        <option value="under-box" <?php selected($o['deny_link_position'], 'under-box'); ?>><?php esc_html_e('Under the banner', 'lrob-cookie-consent'); ?></option>
                                        <option value="top" <?php selected($o['deny_link_position'], 'top'); ?>><?php esc_html_e('Top of the banner', 'lrob-cookie-consent'); ?></option>
                                        <option value="near-close" <?php selected($o['deny_link_position'], 'near-close'); ?>><?php esc_html_e('Next to the × button', 'lrob-cookie-consent'); ?></option>
                                    </select>
                                    <input type="text" data-field="text_continue" name="<?php echo $name('text_continue'); ?>" value="<?php echo esc_attr((string) $o['text_continue']); ?>" placeholder="<?php echo esc_attr($text_defaults['continue']); ?>" />
                                    <select data-field="continue_align" name="<?php echo $name('continue_align'); ?>">
                                        <option value="left" <?php selected($o['continue_align'], 'left'); ?>><?php esc_html_e('Left', 'lrob-cookie-consent'); ?></option>
                                        <option value="center" <?php selected($o['continue_align'], 'center'); ?>><?php esc_html_e('Center', 'lrob-cookie-consent'); ?></option>
                                        <option value="right" <?php selected($o['continue_align'], 'right'); ?>><?php esc_html_e('Right', 'lrob-cookie-consent'); ?></option>
                                    </select>
                                    <label class="lrob-cc-check"><input type="checkbox" data-field="continue_arrow" name="<?php echo $name('continue_arrow'); ?>" value="1" <?php echo $checked('continue_arrow'); ?> /> <?php esc_html_e('Arrow (→)', 'lrob-cookie-consent'); ?></label>
                                </span></td></tr>
                        <tr><th><?php esc_html_e('Customize button', 'lrob-cookie-consent'); ?> <?php $help(__('Shown only when category options are hidden behind a button (see Layout). It reveals the per-category choices.', 'lrob-cookie-consent')); ?></th>
                            <td><label class="lrob-cc-btn-toggle"><input type="checkbox" data-field="show_customize" data-toggle-text="text_customize" name="<?php echo $name('show_customize'); ?>" value="1" <?php echo $checked('show_customize'); ?> /> <?php esc_html_e('Show', 'lrob-cookie-consent'); ?></label>
                                <input type="text" data-field="text_customize" name="<?php echo $name('text_customize'); ?>" value="<?php echo esc_attr((string) $o['text_customize']); ?>" placeholder="<?php echo esc_attr($text_defaults['customize']); ?>"<?php echo empty($o['show_customize']) ? ' readonly class="lrob-cc-readonly"' : ''; ?> /></td></tr>
                        <tr id="lrob-cc-save-row"<?php echo ($o['categories_collapsed'] && empty($o['show_customize'])) ? ' hidden' : ''; ?>><th><?php esc_html_e('Save button', 'lrob-cookie-consent'); ?> <?php $help(__('Always shown when the category options are visible (after “Customize”, or in the expanded layout) — it’s how a granular choice is confirmed, so it can’t be hidden. You can rename it.', 'lrob-cookie-consent')); ?></th>
                            <td><input type="text" data-field="text_save" name="<?php echo $name('text_save'); ?>" value="<?php echo esc_attr((string) $o['text_save']); ?>" placeholder="<?php echo esc_attr($text_defaults['save']); ?>" /></td></tr>
                        <tr><th><?php esc_html_e('Logo', 'lrob-cookie-consent'); ?></th>
                            <td>
                                <div class="lrob-cc-logo-field">
                                    <input type="hidden" id="lrob-cc-logo-input" data-field="logo" name="<?php echo $name('logo'); ?>" value="<?php echo esc_attr((string) $o['logo']); ?>" />
                                    <img id="lrob-cc-logo-preview" src="<?php echo esc_url((string) $o['logo']); ?>" alt="" <?php echo $o['logo'] === '' ? 'hidden' : ''; ?> />
                                    <button type="button" class="button" id="lrob-cc-logo-select"><?php esc_html_e('Select logo', 'lrob-cookie-consent'); ?></button>
                                    <button type="button" class="button-link lrob-cc-logo-remove" id="lrob-cc-logo-remove" <?php echo $o['logo'] === '' ? 'hidden' : ''; ?>><?php esc_html_e('Remove', 'lrob-cookie-consent'); ?></button>
                                </div>
                                <label class="lrob-cc-logo-size"><?php esc_html_e('Max height', 'lrob-cookie-consent'); ?>
                                    <input type="number" min="12" max="200" data-field="logo_height" name="<?php echo $name('logo_height'); ?>" value="<?php echo esc_attr((string) $o['logo_height']); ?>" class="small-text" /> px</label>
                            </td></tr>
                    </table>

                    <p class="lrob-cc-field-label"><?php esc_html_e('Button order', 'lrob-cookie-consent'); ?> <?php $help(__('Drag to reorder the banner buttons. Hidden buttons are simply skipped.', 'lrob-cookie-consent')); ?></p>
                    <?php $btn_labels = ['accept' => __('Accept', 'lrob-cookie-consent'), 'deny' => __('Refuse', 'lrob-cookie-consent'), 'customize' => __('Customize', 'lrob-cookie-consent')]; ?>
                    <ul class="lrob-cc-btn-order" id="lrob-cc-btn-order">
                        <?php foreach ((array) $o['button_order'] as $bk) : if (!isset($btn_labels[$bk])) { continue; } ?>
                            <li draggable="true" data-key="<?php echo esc_attr($bk); ?>"><span class="lrob-cc-drag-handle" aria-hidden="true">⠿</span> <?php echo esc_html($btn_labels[$bk]); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <input type="hidden" id="lrob-cc-btn-order-input" data-field="button_order" name="<?php echo $name('button_order'); ?>" value="<?php echo esc_attr(implode(',', (array) $o['button_order'])); ?>" />

                    <h3><?php esc_html_e('Layout', 'lrob-cookie-consent'); ?></h3>
                    <p>
                        <label class="lrob-cc-check"><input type="checkbox" data-field="categories_collapsed" name="<?php echo $name('categories_collapsed'); ?>" value="1" <?php echo $checked('categories_collapsed'); ?> /> <?php esc_html_e('Hide category options behind a “Customize” button (simpler banner)', 'lrob-cookie-consent'); ?></label>
                        <label class="lrob-cc-check"><input type="checkbox" name="<?php echo $name('revisit_button'); ?>" value="1" <?php echo $checked('revisit_button'); ?> /> <?php esc_html_e('Show a floating “Manage cookies” button after a decision', 'lrob-cookie-consent'); ?></label>
                        <label class="lrob-cc-check"><input type="checkbox" name="<?php echo $name('show_sources'); ?>" value="1" <?php echo $checked('show_sources'); ?> /> <?php esc_html_e('Let visitors expand each category to see exactly what it blocks', 'lrob-cookie-consent'); ?></label>
                    </p>
                    <p>
                        <label><?php esc_html_e('“Manage cookies” button label', 'lrob-cookie-consent'); ?>
                            <input type="text" data-field="revisit_text" name="<?php echo $name('revisit_text'); ?>" value="<?php echo esc_attr((string) $o['revisit_text']); ?>" placeholder="<?php esc_attr_e('Manage cookies', 'lrob-cookie-consent'); ?>" />
                        </label>
                        <label><?php esc_html_e('“Manage cookies” button position', 'lrob-cookie-consent'); ?>
                            <select data-field="revisit_position" name="<?php echo $name('revisit_position'); ?>">
                                <option value="follow" <?php selected($o['revisit_position'], 'follow'); ?>><?php esc_html_e('Follow the banner', 'lrob-cookie-consent'); ?></option>
                                <option value="bottom-right" <?php selected($o['revisit_position'], 'bottom-right'); ?>><?php esc_html_e('Bottom right', 'lrob-cookie-consent'); ?></option>
                                <option value="bottom-left" <?php selected($o['revisit_position'], 'bottom-left'); ?>><?php esc_html_e('Bottom left', 'lrob-cookie-consent'); ?></option>
                                <option value="top-right" <?php selected($o['revisit_position'], 'top-right'); ?>><?php esc_html_e('Top right', 'lrob-cookie-consent'); ?></option>
                                <option value="top-left" <?php selected($o['revisit_position'], 'top-left'); ?>><?php esc_html_e('Top left', 'lrob-cookie-consent'); ?></option>
                            </select>
                        </label>
                    </p>

                    <p class="lrob-cc-field-label"><?php esc_html_e('Cookie details on the main view', 'lrob-cookie-consent'); ?> <?php $help(__('Show a read-only “what we use” dropdown on the banner’s main page (each category + the services it covers) — handy when you don’t offer per-category customization, e.g. only necessary cookies. One combined list, or two (necessary / optional).', 'lrob-cookie-consent')); ?></p>
                    <?php $seg('disclosure', [
                        'off' => __('Off', 'lrob-cookie-consent'),
                        'one' => __('One list', 'lrob-cookie-consent'),
                        'two' => __('Two lists', 'lrob-cookie-consent'),
                    ]); ?>
                    <div id="lrob-cc-disclosure-opts"<?php echo $o['disclosure'] === 'off' ? ' hidden' : ''; ?>>
                        <p><label class="lrob-cc-check"><input type="checkbox" data-field="disclosure_open" name="<?php echo $name('disclosure_open'); ?>" value="1" <?php echo $checked('disclosure_open'); ?> /> <?php esc_html_e('Expanded by default', 'lrob-cookie-consent'); ?></label></p>
                        <p>
                            <label><?php esc_html_e('Heading', 'lrob-cookie-consent'); ?>
                                <input type="text" data-field="text_disclosure" name="<?php echo $name('text_disclosure'); ?>" value="<?php echo esc_attr((string) $o['text_disclosure']); ?>" placeholder="<?php echo esc_attr($text_defaults['disclosure']); ?>" /></label>
                            <label class="lrob-cc-disclosure-mandatory"<?php echo $o['disclosure'] === 'two' ? '' : ' hidden'; ?>><?php esc_html_e('Necessary heading', 'lrob-cookie-consent'); ?>
                                <input type="text" data-field="text_disclosure_mandatory" name="<?php echo $name('text_disclosure_mandatory'); ?>" value="<?php echo esc_attr((string) $o['text_disclosure_mandatory']); ?>" placeholder="<?php echo esc_attr($text_defaults['disclosure_mandatory']); ?>" /></label>
                        </p>
                    </div>

                    <p class="lrob-cc-field-label"><?php esc_html_e('Footer links', 'lrob-cookie-consent'); ?> <?php $help(__('Links shown at the bottom of the banner — e.g. your Privacy Policy. Search an existing page or add any URL.', 'lrob-cookie-consent')); ?></p>
                    <div class="lrob-cc-link-search">
                        <input type="search" id="lrob-cc-link-search" placeholder="<?php esc_attr_e('Search a page to add…', 'lrob-cookie-consent'); ?>" autocomplete="off" />
                        <div id="lrob-cc-link-search-results" class="lrob-cc-link-results" hidden></div>
                    </div>
                    <div id="lrob-cc-links" data-name="<?php echo esc_attr($option); ?>">
                        <?php foreach ((array) $o['footer_links'] as $i => $lnk) : if (!is_array($lnk)) { continue; } ?>
                            <div class="lrob-cc-link-row">
                                <input type="text" class="lrob-cc-link-label" name="<?php echo esc_attr($option); ?>[footer_links][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr((string) ($lnk['label'] ?? '')); ?>" placeholder="<?php esc_attr_e('Label', 'lrob-cookie-consent'); ?>" />
                                <input type="url" class="lrob-cc-link-url" name="<?php echo esc_attr($option); ?>[footer_links][<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr((string) ($lnk['url'] ?? '')); ?>" placeholder="https://…" />
                                <button type="button" class="button lrob-cc-link-remove" aria-label="<?php esc_attr_e('Remove', 'lrob-cookie-consent'); ?>">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button" id="lrob-cc-link-add"><?php esc_html_e('Add link', 'lrob-cookie-consent'); ?></button>
                    <p><label class="lrob-cc-check"><input type="checkbox" data-field="watermark" name="<?php echo $name('watermark'); ?>" value="1" <?php echo $checked('watermark'); ?> /> <?php esc_html_e('Show a small “Cookie Consent by LRob” credit in the footer', 'lrob-cookie-consent'); ?></label></p>
                    <p class="lrob-cc-field-label"><?php esc_html_e('Footer links alignment', 'lrob-cookie-consent'); ?></p>
                    <?php $seg('align_footer', ['left' => __('Left', 'lrob-cookie-consent'), 'center' => __('Center', 'lrob-cookie-consent'), 'right' => __('Right', 'lrob-cookie-consent')]); ?>

                    <h3><?php esc_html_e('Appearance', 'lrob-cookie-consent'); ?></h3>

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

                    <p class="lrob-cc-field-label"><?php esc_html_e('Backdrop', 'lrob-cookie-consent'); ?> <?php $help(__('Dim or blur the page behind the banner, making it modal. Not recommended by default; pairs naturally with the Center position.', 'lrob-cookie-consent')); ?></p>
                    <?php $seg('backdrop', [
                        'none' => __('None', 'lrob-cookie-consent'),
                        'dim' => __('Dim', 'lrob-cookie-consent'),
                        'blur' => __('Dim + blur', 'lrob-cookie-consent'),
                    ]); ?>
                    <p class="lrob-cc-backdrop-opt" id="lrob-cc-backdrop-dim"<?php echo in_array($o['backdrop'], ['dim', 'blur'], true) ? '' : ' hidden'; ?>>
                        <label><?php esc_html_e('Darken (%)', 'lrob-cookie-consent'); ?>
                            <input type="number" min="0" max="100" data-field="backdrop_dim" name="<?php echo $name('backdrop_dim'); ?>" value="<?php echo esc_attr((string) $o['backdrop_dim']); ?>" class="small-text" /></label>
                    </p>
                    <p class="lrob-cc-backdrop-opt" id="lrob-cc-backdrop-blur"<?php echo $o['backdrop'] === 'blur' ? '' : ' hidden'; ?>>
                        <label><?php esc_html_e('Blur strength (px)', 'lrob-cookie-consent'); ?>
                            <input type="number" min="0" max="30" data-field="backdrop_blur" name="<?php echo $name('backdrop_blur'); ?>" value="<?php echo esc_attr((string) $o['backdrop_blur']); ?>" class="small-text" /></label>
                    </p>

                    <p class="lrob-cc-field-label"><?php esc_html_e('Distance from the screen edges', 'lrob-cookie-consent'); ?> <?php $help(__('Gap between the banner and the screen edges when placed in a corner — increase it to clear a chat widget or other floating button. Choose a preset, or Custom to set your own value and unit (rem/em/% scale better across screens).', 'lrob-cookie-consent')); ?></p>
                    <?php $seg('offset_preset', [
                        'snug' => __('Snug', 'lrob-cookie-consent'),
                        'default' => __('Default', 'lrob-cookie-consent'),
                        'spacious' => __('Spacious', 'lrob-cookie-consent'),
                        'custom' => __('Custom', 'lrob-cookie-consent'),
                    ]); ?>
                    <div class="lrob-cc-offsets" id="lrob-cc-offset-custom"<?php echo $o['offset_preset'] === 'custom' ? '' : ' hidden'; ?>>
                        <label><?php esc_html_e('Horizontal', 'lrob-cookie-consent'); ?>
                            <input type="number" min="0" max="200" class="small-text lrob-cc-num-default" data-field="offset_x" data-default="<?php echo esc_attr((string) $defaults['offset_x']); ?>" name="<?php echo $name('offset_x'); ?>" value="<?php echo esc_attr((string) $o['offset_x']); ?>" /></label>
                        <label><?php esc_html_e('Vertical', 'lrob-cookie-consent'); ?>
                            <input type="number" min="0" max="200" class="small-text lrob-cc-num-default" data-field="offset_y" data-default="<?php echo esc_attr((string) $defaults['offset_y']); ?>" name="<?php echo $name('offset_y'); ?>" value="<?php echo esc_attr((string) $o['offset_y']); ?>" /></label>
                        <label><?php esc_html_e('Unit', 'lrob-cookie-consent'); ?>
                            <select name="<?php echo $name('offset_unit'); ?>" data-field="offset_unit">
                                <?php foreach (['px', 'rem', 'em', 'vw', '%'] as $u) : ?>
                                    <option value="<?php echo esc_attr($u); ?>" <?php selected($o['offset_unit'], $u); ?>><?php echo esc_html($u); ?></option>
                                <?php endforeach; ?>
                            </select></label>
                    </div>

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
                                'color_btn_hover_bg' => __('Accept button hover', 'lrob-cookie-consent'),
                                'color_btn_deny_bg' => __('Deny/Save button bg', 'lrob-cookie-consent'),
                                'color_btn_deny_text' => __('Deny/Save button text', 'lrob-cookie-consent'),
                                'color_btn_deny_hover_bg' => __('Deny/Save button hover', 'lrob-cookie-consent'),
                            ];
                            foreach ($color_fields as $key => $label) : ?>
                                <tr><th><?php echo esc_html($label); ?></th>
                                    <td><input type="text" class="lrob-cc-color" data-field="<?php echo esc_attr($key); ?>" name="<?php echo $name($key); ?>" value="<?php echo esc_attr((string) $o[$key]); ?>" /></td></tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <?php
                    $align_svg = static fn (array $rects): string => '<svg class="lrob-cc-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">'
                        . implode('', array_map(static fn (array $r): string => sprintf('<rect x="%d" y="%d" width="%d" height="2" rx="1"/>', $r[0], $r[1], $r[2]), $rects)) . '</svg>';
                    $align_choices = [
                        'left'   => ['label' => __('Left', 'lrob-cookie-consent'), 'svg' => $align_svg([[2, 3, 12], [2, 7, 8], [2, 11, 11]])],
                        'center' => ['label' => __('Center', 'lrob-cookie-consent'), 'svg' => $align_svg([[2, 3, 12], [4, 7, 8], [3, 11, 10]])],
                        'right'  => ['label' => __('Right', 'lrob-cookie-consent'), 'svg' => $align_svg([[2, 3, 12], [6, 7, 8], [3, 11, 11]])],
                    ];
                    ?>
                    <div class="lrob-cc-fieldgrid">
                        <div class="lrob-cc-field"><p class="lrob-cc-field-label"><?php esc_html_e('Popup width', 'lrob-cookie-consent'); ?></p>
                            <?php $seg('popup_size', ['small' => __('Small', 'lrob-cookie-consent'), 'medium' => __('Medium', 'lrob-cookie-consent'), 'large' => __('Large', 'lrob-cookie-consent')]); ?></div>
                        <div class="lrob-cc-field"><p class="lrob-cc-field-label"><?php esc_html_e('Density (spacing)', 'lrob-cookie-consent'); ?></p>
                            <?php $seg('density', ['compact' => __('Compact', 'lrob-cookie-consent'), 'cozy' => __('Cozy', 'lrob-cookie-consent'), 'comfortable' => __('Comfortable', 'lrob-cookie-consent')]); ?></div>
                        <div class="lrob-cc-field"><p class="lrob-cc-field-label"><?php esc_html_e('Font size', 'lrob-cookie-consent'); ?></p>
                            <?php $seg('font_size', ['small' => __('Small', 'lrob-cookie-consent'), 'medium' => __('Medium', 'lrob-cookie-consent'), 'large' => __('Large', 'lrob-cookie-consent')]); ?></div>
                        <div class="lrob-cc-field"><p class="lrob-cc-field-label"><?php esc_html_e('Corners', 'lrob-cookie-consent'); ?></p>
                            <?php $seg('shape', ['square' => __('Square', 'lrob-cookie-consent'), 'rounded' => __('Rounded', 'lrob-cookie-consent'), 'pill' => __('Pill', 'lrob-cookie-consent')]); ?></div>
                        <div class="lrob-cc-field"><p class="lrob-cc-field-label"><?php esc_html_e('Title alignment', 'lrob-cookie-consent'); ?></p>
                            <?php $seg('align_title', $align_choices); ?></div>
                        <div class="lrob-cc-field"><p class="lrob-cc-field-label"><?php esc_html_e('Text alignment', 'lrob-cookie-consent'); ?></p>
                            <?php $seg('align_text', $align_choices); ?></div>
                        <div class="lrob-cc-field"><p class="lrob-cc-field-label"><?php esc_html_e('Buttons alignment', 'lrob-cookie-consent'); ?></p>
                            <?php $seg('align_buttons', $align_choices); ?></div>
                    </div>

                    <h3><?php esc_html_e('Animation', 'lrob-cookie-consent'); ?> <?php $help(__('How the banner enters. Fade, movement and easing combine freely. Speed sets the duration; the preview replays whenever you change a setting.', 'lrob-cookie-consent')); ?></h3>
                    <p><label class="lrob-cc-check"><input type="checkbox" data-field="anim_fade" name="<?php echo $name('anim_fade'); ?>" value="1" <?php echo $checked('anim_fade'); ?> /> <?php esc_html_e('Fade in', 'lrob-cookie-consent'); ?></label></p>
                    <div class="lrob-cc-fieldgrid">
                        <div class="lrob-cc-field"><p class="lrob-cc-field-label"><?php esc_html_e('Movement', 'lrob-cookie-consent'); ?></p>
                            <?php $seg('anim_move', ['none' => __('None', 'lrob-cookie-consent'), 'slide' => __('Slide', 'lrob-cookie-consent'), 'zoom' => __('Zoom', 'lrob-cookie-consent')]); ?></div>
                        <div class="lrob-cc-field" id="lrob-cc-anim-dir-field"<?php echo $o['anim_move'] === 'slide' ? '' : ' hidden'; ?>><p class="lrob-cc-field-label"><?php esc_html_e('Slide from', 'lrob-cookie-consent'); ?></p>
                            <?php $seg('anim_direction', ['top' => __('Top', 'lrob-cookie-consent'), 'bottom' => __('Bottom', 'lrob-cookie-consent'), 'left' => __('Left', 'lrob-cookie-consent'), 'right' => __('Right', 'lrob-cookie-consent')]); ?></div>
                    </div>
                    <p class="lrob-cc-field-label"><?php esc_html_e('Speed (ms)', 'lrob-cookie-consent'); ?></p>
                    <p><input type="number" min="0" max="2000" step="50" class="small-text lrob-cc-num-default" data-field="anim_speed" data-default="<?php echo esc_attr((string) $defaults['anim_speed']); ?>" name="<?php echo $name('anim_speed'); ?>" value="<?php echo esc_attr((string) $o['anim_speed']); ?>" />
                        <button type="button" class="button lrob-cc-default-btn" data-target="<?php echo $name('anim_speed'); ?>"><?php esc_html_e('Default', 'lrob-cookie-consent'); ?></button>
                        <button type="button" class="button" id="lrob-cc-anim-replay"><?php esc_html_e('Replay', 'lrob-cookie-consent'); ?></button></p>
                    <p class="lrob-cc-field-label"><?php esc_html_e('Delay before showing (ms)', 'lrob-cookie-consent'); ?> <?php $help(__('Wait this long after page load before the banner first appears. Reopening it later is always instant.', 'lrob-cookie-consent')); ?></p>
                    <p><input type="number" min="0" max="20000" step="100" class="lrob-cc-num-default" data-default="<?php echo esc_attr((string) $defaults['show_delay']); ?>" name="<?php echo $name('show_delay'); ?>" value="<?php echo esc_attr((string) $o['show_delay']); ?>" />
                        <button type="button" class="button lrob-cc-default-btn" data-target="<?php echo $name('show_delay'); ?>"><?php esc_html_e('Default', 'lrob-cookie-consent'); ?></button></p>
                </div>

                <div class="lrob-cc-banner-preview">
                    <p class="lrob-cc-preview-label"><?php esc_html_e('Live preview', 'lrob-cookie-consent'); ?></p>
                    <div class="lrob-cc-preview-states">
                        <button type="button" class="button button-small button-primary" data-preview-state="main"><?php esc_html_e('Main view', 'lrob-cookie-consent'); ?></button>
                        <button type="button" class="button button-small" data-preview-state="options"><?php esc_html_e('Options', 'lrob-cookie-consent'); ?></button>
                        <button type="button" class="button button-small" data-preview-state="closed"><?php esc_html_e('Closed', 'lrob-cookie-consent'); ?></button>
                        <button type="button" class="button button-small" id="lrob-cc-preview-refresh" title="<?php esc_attr_e('Show the banner again', 'lrob-cookie-consent'); ?>">&#x21bb;</button>
                    </div>
                    <div class="lrob-cc-preview-stage">
                        <div class="lrob-cc-preview-bd" hidden></div>
                        <p class="lrob-cc-preview-closed" hidden><?php esc_html_e('Banner closed — visitors would see the floating “Manage cookies” button.', 'lrob-cookie-consent'); ?></p>
                        <div id="lrob-cc-preview" class="lrob-cc-banner">
                            <div class="lrob-cc-inner" role="document">
                                <div class="lrob-cc-header">
                                    <img class="lrob-cc-logo" data-preview="logo" alt="" hidden />
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
                                <button type="button" class="lrob-cc-continue" data-preview="continue" data-default="<?php echo esc_attr($text_defaults['continue']); ?>" hidden></button>
                                <div class="lrob-cc-footer" data-preview="footer"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- COOKIES -->
        <section class="lrob-cc-panel" data-panel="cookies" hidden>
            <h3><?php esc_html_e('Auto-detect my cookies', 'lrob-cookie-consent'); ?> <?php $help(__('Finds third-party scripts and embeds so you can block them. Runs as an anonymous visitor — admin/member-only cookies are never touched. Note: external fonts (e.g. Google Fonts) also send visitor IPs to a third party — best self-hosted rather than blocked.', 'lrob-cookie-consent')); ?></h3>
            <div class="lrob-cc-scan">
                <p><?php esc_html_e('Find third-party scripts, embeds and cookies so you can block them. Start with a fast scan of your content; then optionally visit your pages for a deeper look.', 'lrob-cookie-consent'); ?></p>

                <p>
                    <button type="button" class="button button-primary" id="lrob-cc-scan-db-btn"><?php esc_html_e('Scan my site', 'lrob-cookie-consent'); ?></button>
                    <button type="button" class="button" id="lrob-cc-scan-startover" hidden><?php esc_html_e('Start over', 'lrob-cookie-consent'); ?></button>
                </p>

                <div id="lrob-cc-scan-http-card" class="lrob-cc-scan-deeper" hidden>
                    <h4><?php esc_html_e('Deeper scan — visit pages', 'lrob-cookie-consent'); ?> <?php $help(__('Loads your pages as a visitor would, catching resources injected by your theme or plugins that aren’t in the stored content. Findings add to the results below.', 'lrob-cookie-consent')); ?></h4>
                    <table class="lrob-cc-scan-types">
                        <thead><tr>
                            <th><label><input type="checkbox" id="lrob-cc-scan-all-types" checked /> <?php esc_html_e('Scan everything', 'lrob-cookie-consent'); ?></label></th>
                            <th><?php esc_html_e('Limit', 'lrob-cookie-consent'); ?></th>
                            <th><?php esc_html_e('Order', 'lrob-cookie-consent'); ?></th>
                        </tr></thead>
                        <tbody>
                            <tr class="lrob-cc-scan-type" data-type="home" data-count="1">
                                <td><label><input type="checkbox" checked disabled /> <?php esc_html_e('Home page', 'lrob-cookie-consent'); ?></label></td>
                                <td colspan="2" class="description"><?php esc_html_e('always', 'lrob-cookie-consent'); ?></td>
                            </tr>
                            <?php foreach ($scan_types as $t) : ?>
                                <tr class="lrob-cc-scan-type" data-type="<?php echo esc_attr($t['type']); ?>" data-count="<?php echo (int) $t['count']; ?>">
                                    <td><label><input type="checkbox" class="lrob-cc-scan-type-on" checked /> <?php echo esc_html($t['label']); ?> <span class="description">(<?php echo (int) $t['count']; ?>)</span></label></td>
                                    <td><input type="number" class="small-text lrob-cc-scan-type-limit" min="0" step="1" placeholder="<?php esc_attr_e('all', 'lrob-cookie-consent'); ?>" /></td>
                                    <td><select class="lrob-cc-scan-type-order"><option value="newest"><?php esc_html_e('newest', 'lrob-cookie-consent'); ?></option><option value="oldest"><?php esc_html_e('oldest', 'lrob-cookie-consent'); ?></option></select></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="lrob-cc-scan-speed-wrap">
                        <label for="lrob-cc-scan-speed"><?php esc_html_e('Scan speed', 'lrob-cookie-consent'); ?></label>
                        <input type="range" id="lrob-cc-scan-speed" min="1" max="8" value="2" step="1" />
                        <span id="lrob-cc-scan-speed-val" class="lrob-cc-scan-speed-val">2</span>
                        <?php $help(__('How many pages to fetch at once. Higher is faster but loads your server more — the scan slows itself down automatically if your host can’t keep up.', 'lrob-cookie-consent')); ?>
                    </p>

                    <p class="description" id="lrob-cc-scan-total"></p>
                    <p class="lrob-cc-hint lrob-cc-hint-warning" id="lrob-cc-scan-many-warn" hidden><?php esc_html_e('That is a lot of pages to fetch one by one — on a slow or limited host this can take a while.', 'lrob-cookie-consent'); ?></p>

                    <p><button type="button" class="button button-primary" id="lrob-cc-scan-http-btn"><?php esc_html_e('Run deeper scan', 'lrob-cookie-consent'); ?></button></p>
                </div>

                <div id="lrob-cc-scan-progress" class="lrob-cc-scan-progress" hidden>
                    <progress id="lrob-cc-scan-bar" max="100" value="0"></progress>
                    <span id="lrob-cc-scan-progress-text"></span>
                    <span id="lrob-cc-scan-current" class="description"></span>
                </div>

                <div id="lrob-cc-scan-results"></div>
            </div>

            <h3><?php esc_html_e('Categories', 'lrob-cookie-consent'); ?> <?php $help(__('Functional cookies (WordPress login, cart, CSRF/security tokens) are always allowed. The built-in categories below are fixed; rename their wording via translation. You can add your own custom categories.', 'lrob-cookie-consent')); ?></h3>
            <?php
            $cat_overrides = is_array($o['cat_desc_overrides'] ?? null) ? $o['cat_desc_overrides'] : [];
            // Card: title + badge on one line; description shown as text with a
            // pencil to reveal the (hidden) edit field.
            $cat_card = static function (string $slug, string $badge) use ($option, $cat_overrides, $labels): void {
                $pencil = '<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 13.5V16h2.5l7.4-7.4-2.5-2.5L4 13.5zM15.7 6.3a1 1 0 0 0 0-1.4l-1.6-1.6a1 1 0 0 0-1.4 0l-1.2 1.2 2.5 2.5 1.7-1.7z"/></svg>';
                printf(
                    '<div class="lrob-cc-cat-card is-locked">'
                        . '<div class="lrob-cc-cat-card-head"><strong>%1$s</strong><span class="lrob-cc-cat-badge">%2$s</span></div>'
                        . '<div class="lrob-cc-cat-card-body">'
                            . '<span class="lrob-cc-cat-card-desc">%3$s</span>'
                            . '<button type="button" class="button lrob-cc-cat-edit" aria-label="%4$s" title="%4$s">%5$s</button>'
                            . '<textarea class="lrob-cc-cat-card-desc-input" rows="3" hidden name="%6$s[cat_desc_overrides][%7$s]" placeholder="%8$s">%9$s</textarea>'
                        . '</div>'
                    . '</div>',
                    esc_html($labels[$slug]['title']),
                    esc_html($badge),
                    esc_html($labels[$slug]['desc']),
                    esc_attr__('Edit description', 'lrob-cookie-consent'),
                    $pencil,
                    esc_attr($option),
                    esc_attr($slug),
                    esc_attr(\LRob\CookieConsent\Support\Categories::default_desc($slug)),
                    esc_textarea((string) ($cat_overrides[$slug] ?? ''))
                );
            };
            ?>
            <div class="lrob-cc-cat-grid">
                <?php $cat_card('functional', __('always on', 'lrob-cookie-consent')); ?>
                <?php foreach ($default_categories as $slug) : ?>
                    <?php $cat_card($slug, __('built-in', 'lrob-cookie-consent')); ?>
                <?php endforeach; ?>
            </div>
            <p class="description"><?php esc_html_e('Built-in category names are fixed; click the pencil to customise a description (leave blank for the default).', 'lrob-cookie-consent'); ?></p>

            <p class="lrob-cc-field-label"><?php esc_html_e('Custom categories', 'lrob-cookie-consent'); ?></p>
            <div id="lrob-cc-cats" data-name="<?php echo esc_attr($option); ?>">
                <?php foreach ($category_rows as $i => $c) : ?>
                    <div class="lrob-cc-cat-row">
                        <input type="text" class="lrob-cc-cat-slug" name="<?php echo esc_attr($option); ?>[categories][<?php echo (int) $i; ?>][slug]" value="<?php echo esc_attr($c['slug']); ?>" placeholder="<?php esc_attr_e('slug', 'lrob-cookie-consent'); ?>" />
                        <input type="text" name="<?php echo esc_attr($option); ?>[categories][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr($c['label']); ?>" placeholder="<?php esc_attr_e('Label', 'lrob-cookie-consent'); ?>" />
                        <input type="text" class="lrob-cc-cat-desc" name="<?php echo esc_attr($option); ?>[categories][<?php echo (int) $i; ?>][desc]" value="<?php echo esc_attr($c['desc']); ?>" placeholder="<?php esc_attr_e('Description', 'lrob-cookie-consent'); ?>" />
                        <button type="button" class="button lrob-cc-cat-remove" aria-label="<?php esc_attr_e('Remove', 'lrob-cookie-consent'); ?>">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="lrob-cc-cat-add"><?php esc_html_e('Add category', 'lrob-cookie-consent'); ?></button>

            <h3><?php esc_html_e('Blocking', 'lrob-cookie-consent'); ?></h3>
            <table class="form-table" role="presentation">
                <tr><th><?php esc_html_e('Block iframes', 'lrob-cookie-consent'); ?> <?php $help(__('Embedded iframes (YouTube, Maps…) can set third-party cookies before consent. Turning this off loads them immediately — a GDPR risk. Leave on.', 'lrob-cookie-consent')); ?></th>
                    <td><label class="lrob-cc-check"><input type="checkbox" name="<?php echo $name('block_iframes'); ?>" value="1" <?php echo $checked('block_iframes'); ?> /> <?php esc_html_e('Neutralise matching iframes until consent', 'lrob-cookie-consent'); ?></label></td></tr>
                <tr><th><?php esc_html_e('Behavior on change', 'lrob-cookie-consent'); ?> <?php $help(__('When you start blocking a brand-new category of cookies, returning visitors are re-asked so their consent stays accurate. Enable this to also re-ask on smaller changes (e.g. adding another service to a category they already decided on).', 'lrob-cookie-consent')); ?></th>
                    <td><label class="lrob-cc-check"><input type="checkbox" name="<?php echo $name('reprompt_on_rule_change'); ?>" value="1" <?php echo $checked('reprompt_on_rule_change'); ?> /> <?php esc_html_e('Re-prompt on any change to the block list', 'lrob-cookie-consent'); ?></label></td></tr>
            </table>

            <h3><?php esc_html_e('Block rules', 'lrob-cookie-consent'); ?> <?php $help(__('The patterns blocked under each category. Use “Auto-detect my cookies” at the top to find them automatically, or add them by hand.', 'lrob-cookie-consent')); ?></h3>
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
                // functional is offered so admins can reference necessary cookies
                // (own site, payment gateways) — documented but never blocked.
                foreach (array_merge(['functional'], $optional) as $cat) {
                    $label = $labels[$cat]['title'] ?? $cat;
                    if ($cat === 'functional') {
                        $label .= ' — ' . __('necessary, not blocked', 'lrob-cookie-consent');
                    }
                    printf('<option value="%s" %s>%s</option>', esc_attr($cat), selected($selected, $cat, false), esc_html($label));
                }
            };
            ?>

            <div class="lrob-cc-rules-structured" data-rules-panel="structured"<?php echo $o['rules_mode'] === 'raw' ? ' hidden' : ''; ?>>
                <p class="description"><?php esc_html_e('Click a common service to add it, or add a custom rule. The pattern is matched against script/iframe URLs and inline script bodies.', 'lrob-cookie-consent'); ?></p>
                <?php
                $svc_groups = [];
                foreach ($services as $svc) {
                    $svc_groups[$svc['category']][] = $svc;
                }
                foreach (array_merge($optional, ['functional']) as $gcat) :
                    if (empty($svc_groups[$gcat])) {
                        continue;
                    } ?>
                    <div class="lrob-cc-service-group">
                        <span><?php echo esc_html($labels[$gcat]['title'] ?? $gcat); ?></span>
                        <div class="lrob-cc-services">
                            <?php foreach ($svc_groups[$gcat] as $svc) : ?>
                                <button type="button" class="button lrob-cc-service"
                                        data-pattern="<?php echo esc_attr($svc['pattern']); ?>"
                                        data-category="<?php echo esc_attr($svc['category']); ?>"
                                        data-service="<?php echo esc_attr($svc['service']); ?>">
                                    + <?php echo esc_html($svc['label']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="lrob-cc-rule-row lrob-cc-rule-head">
                    <span class="lrob-cc-rule-pattern"><?php esc_html_e('Pattern — text matched in script/iframe URLs & inline code', 'lrob-cookie-consent'); ?></span>
                    <span class="lrob-cc-rule-category"><?php esc_html_e('Category', 'lrob-cookie-consent'); ?></span>
                    <span class="lrob-cc-rule-service"><?php esc_html_e('Service name — shown to visitors', 'lrob-cookie-consent'); ?></span>
                    <span class="lrob-cc-rule-remove" aria-hidden="true"></span>
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
                        <select name="<?php echo esc_attr($option); ?>[inline_scripts][<?php echo (int) $i; ?>][category]"><?php $cat_options((string) ($row['category'] ?? '')); ?></select>
                        <input type="text" class="lrob-cc-inline-name" name="<?php echo esc_attr($option); ?>[inline_scripts][<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr((string) ($row['name'] ?? '')); ?>" placeholder="<?php esc_attr_e('Service name (shown to visitors)', 'lrob-cookie-consent'); ?>" />
                        <textarea rows="3" class="large-text code" name="<?php echo esc_attr($option); ?>[inline_scripts][<?php echo (int) $i; ?>][code]"><?php echo esc_textarea((string) ($row['code'] ?? '')); ?></textarea>
                        <button type="button" class="button lrob-cc-inline-remove"><?php esc_html_e('Remove', 'lrob-cookie-consent'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="lrob-cc-inline-add"><?php esc_html_e('Add inline script', 'lrob-cookie-consent'); ?></button>
        </section>

        <div class="lrob-cc-savebar">
            <?php submit_button(__('Save all settings', 'lrob-cookie-consent'), 'primary', 'lrob_cc_save', false); ?>
            <span class="description"><?php esc_html_e('One save applies to the General, Banner and Cookies tabs together.', 'lrob-cookie-consent'); ?></span>
        </div>
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
            // Strip translation-plugin markers from older snapshots at display time.
            $clean = static fn ($s): string => \LRob\CookieConsent\Consent\BannerVersion::clean((string) $s);
            ?>
            <details class="lrob-cc-version" id="lrob-cc-ver-<?php echo esc_attr((string) $v['version_hash']); ?>">
                <summary><code><?php echo esc_html(substr((string) $v['version_hash'], 0, 12)); ?></code> — <?php echo esc_html((string) $v['created_at']); ?> <?php esc_html_e('UTC', 'lrob-cookie-consent'); ?></summary>
                <div class="lrob-cc-version-body">
                    <p><strong><?php esc_html_e('Header', 'lrob-cookie-consent'); ?>:</strong> <?php echo esc_html($clean($st['header'] ?? '')); ?></p>
                    <p><strong><?php esc_html_e('Message', 'lrob-cookie-consent'); ?>:</strong> <?php echo esc_html($clean($st['message'] ?? '')); ?></p>
                    <p><strong><?php esc_html_e('Buttons', 'lrob-cookie-consent'); ?>:</strong> <?php echo esc_html(trim($clean($st['accept'] ?? '') . ' / ' . $clean($st['deny'] ?? '') . ' / ' . $clean($st['save'] ?? ''), ' /')); ?></p>
                    <p><strong><?php esc_html_e('Categories & what was blocked', 'lrob-cookie-consent'); ?>:</strong></p>
                    <ul class="lrob-cc-version-cats">
                        <?php foreach ($sc as $slug => $cat) :
                            if ($slug === 'functional') {
                                continue;
                            }
                            $blocked = is_array($sb[$slug] ?? null) ? $sb[$slug] : [];
                            ?>
                            <li>
                                <strong><?php echo esc_html($clean($cat['title'] ?? $slug)); ?></strong> — <?php echo esc_html($clean($cat['desc'] ?? '')); ?>
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
