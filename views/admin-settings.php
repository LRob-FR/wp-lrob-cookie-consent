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
?>
<div class="wrap lrob-cc-admin">
    <h1><?php esc_html_e('LRob Cookie Consent', 'lrob-cookie-consent'); ?></h1>

    <?php if (isset($_GET['purged'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Consent log cleared.', 'lrob-cookie-consent'); ?></p></div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper lrob-cc-tabs">
        <a href="#general" class="nav-tab nav-tab-active" data-tab="general"><?php esc_html_e('General', 'lrob-cookie-consent'); ?></a>
        <a href="#banner" class="nav-tab" data-tab="banner"><?php esc_html_e('Banner', 'lrob-cookie-consent'); ?></a>
        <a href="#blocking" class="nav-tab" data-tab="blocking"><?php esc_html_e('Blocking', 'lrob-cookie-consent'); ?></a>
        <a href="#log" class="nav-tab" data-tab="log"><?php esc_html_e('Log', 'lrob-cookie-consent'); ?></a>
    </h2>

    <form method="post" action="options.php">
        <?php settings_fields('lrob_cc_group'); ?>

        <!-- GENERAL -->
        <section class="lrob-cc-panel" data-panel="general">
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
                    <td><label><input type="checkbox" name="<?php echo $name('log_consent'); ?>" value="1" <?php echo $checked('log_consent'); ?> /> <?php esc_html_e('Record each consent decision in the database (advised for GDPR accountability)', 'lrob-cookie-consent'); ?></label></td></tr>

                <tr><th><?php esc_html_e('IP address', 'lrob-cookie-consent'); ?></th>
                    <td>
                        <?php foreach (['anonymized' => __('Store anonymised (IPv4 → /24, IPv6 → /48)', 'lrob-cookie-consent'), 'full' => __('Store full IP', 'lrob-cookie-consent'), 'none' => __('Do not store any IP', 'lrob-cookie-consent')] as $v => $l) : ?>
                            <label class="lrob-cc-radio-line"><input type="radio" name="<?php echo $name('ip_storage'); ?>" value="<?php echo esc_attr($v); ?>" <?php checked($o['ip_storage'], $v); ?> /> <?php echo esc_html($l); ?></label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e('Your server already logs IPs; GDPR requires disclosing it, not omitting it. Anonymisation is the privacy-friendly default.', 'lrob-cookie-consent'); ?></p></td></tr>

                <tr><th><?php esc_html_e('Store user-agent', 'lrob-cookie-consent'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo $name('store_user_agent'); ?>" value="1" <?php echo $checked('store_user_agent'); ?> /></label></td></tr>

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
                    <table class="form-table" role="presentation">
                        <tr><th><?php esc_html_e('Header', 'lrob-cookie-consent'); ?></th>
                            <td><input type="text" class="regular-text" data-field="text_header" name="<?php echo $name('text_header'); ?>" value="<?php echo esc_attr((string) $o['text_header']); ?>" placeholder="<?php echo esc_attr($texts['header']); ?>" /></td></tr>
                        <tr><th><?php esc_html_e('Message', 'lrob-cookie-consent'); ?></th>
                            <td><textarea rows="3" class="large-text" data-field="text_message" name="<?php echo $name('text_message'); ?>" placeholder="<?php echo esc_attr($texts['message']); ?>"><?php echo esc_textarea((string) $o['text_message']); ?></textarea></td></tr>
                        <tr><th><?php esc_html_e('Accept button', 'lrob-cookie-consent'); ?></th>
                            <td><input type="text" data-field="text_accept" name="<?php echo $name('text_accept'); ?>" value="<?php echo esc_attr((string) $o['text_accept']); ?>" placeholder="<?php echo esc_attr($texts['accept']); ?>" /></td></tr>
                        <tr><th><?php esc_html_e('Deny button', 'lrob-cookie-consent'); ?></th>
                            <td><input type="text" data-field="text_deny" name="<?php echo $name('text_deny'); ?>" value="<?php echo esc_attr((string) $o['text_deny']); ?>" placeholder="<?php echo esc_attr($texts['deny']); ?>" /></td></tr>
                        <tr><th><?php esc_html_e('Save button', 'lrob-cookie-consent'); ?></th>
                            <td><input type="text" data-field="text_save" name="<?php echo $name('text_save'); ?>" value="<?php echo esc_attr((string) $o['text_save']); ?>" placeholder="<?php echo esc_attr($texts['save']); ?>" /></td></tr>
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

                    <h3><?php esc_html_e('Buttons & layout', 'lrob-cookie-consent'); ?></h3>
                    <p>
                        <label class="lrob-cc-check"><input type="checkbox" data-field="show_deny" name="<?php echo $name('show_deny'); ?>" value="1" <?php echo $checked('show_deny'); ?> /> <?php esc_html_e('Show “Deny” button', 'lrob-cookie-consent'); ?></label>
                        <label class="lrob-cc-check"><input type="checkbox" data-field="show_save" name="<?php echo $name('show_save'); ?>" value="1" <?php echo $checked('show_save'); ?> /> <?php esc_html_e('Show “Save preferences” button', 'lrob-cookie-consent'); ?></label>
                        <label class="lrob-cc-check"><input type="checkbox" data-field="categories_collapsed" name="<?php echo $name('categories_collapsed'); ?>" value="1" <?php echo $checked('categories_collapsed'); ?> /> <?php esc_html_e('Hide category options behind a “Customize” button (simpler banner)', 'lrob-cookie-consent'); ?></label>
                        <label class="lrob-cc-check"><input type="checkbox" name="<?php echo $name('revisit_button'); ?>" value="1" <?php echo $checked('revisit_button'); ?> /> <?php esc_html_e('Show a floating “Manage cookies” button after a decision', 'lrob-cookie-consent'); ?></label>
                    </p>

                    <p class="lrob-cc-field-label"><?php esc_html_e('Position', 'lrob-cookie-consent'); ?></p>
                    <?php $seg('position', ['bottom' => __('Bottom', 'lrob-cookie-consent'), 'center' => __('Center', 'lrob-cookie-consent'), 'bottom-left' => __('Bottom left', 'lrob-cookie-consent'), 'bottom-right' => __('Bottom right', 'lrob-cookie-consent')]); ?>

                    <h3><?php esc_html_e('Appearance', 'lrob-cookie-consent'); ?></h3>
                    <p class="lrob-cc-field-label"><?php esc_html_e('Colors', 'lrob-cookie-consent'); ?></p>
                    <?php $seg('theme', ['auto' => __('Auto (theme)', 'lrob-cookie-consent'), 'light' => __('Light', 'lrob-cookie-consent'), 'dark' => __('Dark', 'lrob-cookie-consent'), 'custom' => __('Custom', 'lrob-cookie-consent')]); ?>

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

        <!-- BLOCKING -->
        <section class="lrob-cc-panel" data-panel="blocking" hidden>
            <table class="form-table" role="presentation">
                <tr><th><?php esc_html_e('Block method', 'lrob-cookie-consent'); ?></th>
                    <td><?php $seg('block_method', ['full' => __('Full-page scan (recommended)', 'lrob-cookie-consent'), 'enqueued' => __('Enqueued scripts only', 'lrob-cookie-consent')]); ?>
                        <p class="description"><?php esc_html_e('Full-page scan catches hardcoded scripts and iframes too; enqueued-only is lighter but misses them.', 'lrob-cookie-consent'); ?></p></td></tr>
                <tr><th><?php esc_html_e('Block iframes / embeds', 'lrob-cookie-consent'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo $name('block_iframes'); ?>" value="1" <?php echo $checked('block_iframes'); ?> /> <?php esc_html_e('Neutralise matching iframes until consent', 'lrob-cookie-consent'); ?></label>
                        <p class="description"><?php esc_html_e('Turning this off may load third-party cookies before consent — a GDPR risk.', 'lrob-cookie-consent'); ?></p></td></tr>
                <tr><th><?php esc_html_e('Re-prompt on rule change', 'lrob-cookie-consent'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo $name('reprompt_on_rule_change'); ?>" value="1" <?php echo $checked('reprompt_on_rule_change'); ?> /> <?php esc_html_e('Re-ask everyone whenever any block rule changes (default: only when the set of active categories changes)', 'lrob-cookie-consent'); ?></label></td></tr>
            </table>

            <h3><?php esc_html_e('What to block', 'lrob-cookie-consent'); ?></h3>

            <div class="lrob-cc-scan">
                <p class="description"><?php esc_html_e('Scan your public pages for third-party scripts and embeds, then add the ones you want to block. The scan runs as an anonymous visitor, so admin/member-only cookies are never touched. It cannot see trackers injected later by JavaScript (e.g. via Tag Manager) — add those manually or via the deep scan.', 'lrob-cookie-consent'); ?></p>
                <p>
                    <button type="button" class="button button-secondary" id="lrob-cc-scan-btn"><?php esc_html_e('Scan my site', 'lrob-cookie-consent'); ?></button>
                </p>
                <div id="lrob-cc-scan-results"></div>
            </div>

            <p>
                <button type="button" class="button" id="lrob-cc-wizard-open"><?php esc_html_e('Guided setup wizard', 'lrob-cookie-consent'); ?></button>
                <span class="description"><?php esc_html_e('Answer a few quick questions to generate rules.', 'lrob-cookie-consent'); ?></span>
            </p>

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
                <p class="description"><?php esc_html_e('One rule per line: pattern | category | service name. Category is one of: preferences, statistics, marketing.', 'lrob-cookie-consent'); ?></p>
            </div>

            <h3><?php esc_html_e('Inline scripts', 'lrob-cookie-consent'); ?></h3>
            <p class="description"><?php esc_html_e('Paste a tracking snippet (e.g. GA4, Matomo) and pick a category. It is injected inert and only runs after consent — no theme editing.', 'lrob-cookie-consent'); ?></p>
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
        <p><?php
            /* translators: %s: number of stored consent records. */
            printf(esc_html__('Stored consent records: %s', 'lrob-cookie-consent'), '<strong>' . esc_html((string) $log->count()) . '</strong>');
        ?></p>

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

        <table class="widefat striped">
            <thead><tr>
                <th><?php esc_html_e('Date (UTC)', 'lrob-cookie-consent'); ?></th>
                <th><?php esc_html_e('IP', 'lrob-cookie-consent'); ?></th>
                <th><?php esc_html_e('Categories', 'lrob-cookie-consent'); ?></th>
                <th><?php esc_html_e('Config', 'lrob-cookie-consent'); ?></th>
                <th><?php esc_html_e('User-agent', 'lrob-cookie-consent'); ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ($log->recent(100) as $row) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $row['created_at']); ?></td>
                        <td><?php echo esc_html((string) $row['ip_anon']); ?></td>
                        <td><?php echo esc_html((string) $row['categories']); ?></td>
                        <td><code><?php echo esc_html((string) $row['config_version']); ?></code></td>
                        <td><?php echo esc_html((string) $row['user_agent']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
