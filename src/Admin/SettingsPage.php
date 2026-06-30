<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Admin;

use LRob\CookieConsent\Consent\LogRepository;
use LRob\CookieConsent\Frontend\Appearance;
use LRob\CookieConsent\Frontend\Banner;
use LRob\CookieConsent\Support\Categories;
use LRob\CookieConsent\Support\Options;
use LRob\CookieConsent\Support\Presets;
use LRob\CookieConsent\Scanning\Scanner;
use LRob\CookieConsent\Support\Rules;
use LRob\CookieConsent\Support\Services;

final class SettingsPage
{
    private const GROUP = 'lrob_cc_group';
    private const SLUG = 'lrob-cookie-consent';

    private string $hook_suffix = '';

    public function __construct(private LogRepository $log)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_setting']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_post_lrob_cc_export_log', [$this, 'handle_export']);
        add_action('admin_post_lrob_cc_purge_log', [$this, 'handle_purge']);
        add_action('wp_ajax_lrob_cc_scan_targets', [$this, 'handle_scan_targets']);
        add_action('wp_ajax_lrob_cc_scan_url', [$this, 'handle_scan_url']);
        add_action('wp_ajax_lrob_cc_scan_db', [$this, 'handle_scan_db']);
        add_action('wp_ajax_lrob_cc_search_pages', [$this, 'handle_search_pages']);
        add_filter('plugin_action_links_' . LROB_CC_BASENAME, [$this, 'action_links']);
    }

    /**
     * Category choices for rule + inline-script dropdowns: functional first
     * (referenced, never blocked), then the optional categories.
     *
     * @return list<array{slug:string,label:string}>
     */
    public static function category_choices(): array
    {
        $labels = Categories::labels();
        $out = [[
            'slug'  => 'functional',
            'label' => ($labels['functional']['title'] ?? 'Functional') . ' — ' . __('necessary, not blocked', 'lrob-cookie-consent'),
        ]];
        foreach (Categories::optional() as $slug) {
            $out[] = ['slug' => $slug, 'label' => $labels[$slug]['title'] ?? $slug];
        }
        return $out;
    }

    public function handle_search_pages(): void
    {
        $this->require_scan_access();
        $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash((string) $_POST['q'])) : '';
        $posts = get_posts([
            'post_type'      => ['page', 'post'],
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            's'              => $q,
            'orderby'        => 'relevance',
        ]);
        $out = [];
        foreach ($posts as $post) {
            $out[] = ['title' => get_the_title($post), 'url' => (string) get_permalink($post)];
        }
        wp_send_json_success(['pages' => $out]);
    }

    /**
     * @param array<int|string,string> $links
     * @return array<int|string,string>
     */
    public function action_links(array $links): array
    {
        $url = admin_url('options-general.php?page=' . self::SLUG);
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Configure', 'lrob-cookie-consent') . '</a>');
        return $links;
    }

    public function handle_scan_targets(): void
    {
        $this->require_scan_access();
        $raw = isset($_POST['types']) ? wp_unslash((string) $_POST['types']) : '[]';
        $types = json_decode($raw, true);
        wp_send_json_success(['urls' => Scanner::targets_from(is_array($types) ? $types : [])]);
    }

    public function handle_scan_db(): void
    {
        $this->require_scan_access();
        try {
            $offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;
            wp_send_json_success((new \LRob\CookieConsent\Scanning\LocalScanner())->scan_content($offset));
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function handle_scan_url(): void
    {
        $this->require_scan_access();
        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash((string) $_POST['url'])) : '';
        $insecure = !empty($_POST['insecure']);

        // SSRF guard: only ever scan this site's own URLs.
        $site_host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        if ($url === '' || wp_parse_url($url, PHP_URL_HOST) !== $site_host) {
            wp_send_json_error(['message' => __('Only this site\'s own pages can be scanned.', 'lrob-cookie-consent')], 400);
        }

        try {
            wp_send_json_success((new \LRob\CookieConsent\Scanning\LocalScanner())->scan_url($url, $insecure));
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    private function require_scan_access(): void
    {
        if (!current_user_can(LROB_CC_CAPABILITY)) {
            wp_send_json_error(['message' => __('Not allowed.', 'lrob-cookie-consent')], 403);
        }
        check_ajax_referer('lrob_cc_scan', 'nonce');
    }

    public function add_menu(): void
    {
        $this->hook_suffix = (string) add_options_page(
            __('LRob Cookie Consent', 'lrob-cookie-consent'),
            __('Cookie Consent', 'lrob-cookie-consent'),
            LROB_CC_CAPABILITY,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function register_setting(): void
    {
        register_setting(self::GROUP, Options::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== $this->hook_suffix) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style('lrob-cc-admin', LROB_CC_URL . 'assets/css/admin.css', [], lrob_cc_asset_ver('assets/css/admin.css'));
        wp_enqueue_style('lrob-cc-banner', LROB_CC_URL . 'assets/css/banner.css', [], lrob_cc_asset_ver('assets/css/banner.css'));
        wp_enqueue_media();

        wp_enqueue_script(
            'lrob-cc-admin',
            LROB_CC_URL . 'assets/js/admin-preview.js',
            ['wp-color-picker', 'jquery'],
            lrob_cc_asset_ver('assets/js/admin-preview.js'),
            true
        );
        wp_localize_script('lrob-cc-admin', 'lrobCcAdmin', [
            'optionName' => Options::OPTION_KEY,
            'optional'   => Categories::optional(),
            'catList'    => array_map(static fn (string $s): array => ['slug' => $s, 'label' => Categories::labels()[$s]['title'] ?? $s], Categories::optional()),
            'catChoices' => self::category_choices(),
            'palettes'   => Appearance::palettes(),
            'scales'     => Appearance::scales(),
            'colorPresets' => Presets::styles()['colors'] ?? [],
            'texts'      => Presets::text(),
            'services'   => Services::common(),
            'wizard'     => Services::wizard(),
            'wizardSettings' => [
                'tone' => [
                    'question' => __('Pick a tone for your banner text', 'lrob-cookie-consent'),
                    'hint'     => __('You can fine-tune the wording afterwards.', 'lrob-cookie-consent'),
                ],
                'look' => [
                    'question'  => __('Choose how the banner looks', 'lrob-cookie-consent'),
                    'colors'    => [
                        ['v' => 'auto', 'l' => __('Auto (follow theme)', 'lrob-cookie-consent')],
                        ['v' => 'light', 'l' => __('Light', 'lrob-cookie-consent')],
                        ['v' => 'dark', 'l' => __('Dark', 'lrob-cookie-consent')],
                        ['v' => 'midnight', 'l' => __('Midnight', 'lrob-cookie-consent')],
                        ['v' => 'ocean', 'l' => __('Ocean', 'lrob-cookie-consent')],
                        ['v' => 'sand', 'l' => __('Sand', 'lrob-cookie-consent')],
                    ],
                    'positions' => [
                        ['v' => 'bottom', 'l' => __('Bottom', 'lrob-cookie-consent')],
                        ['v' => 'bottom-right', 'l' => __('Bottom right', 'lrob-cookie-consent')],
                        ['v' => 'bottom-left', 'l' => __('Bottom left', 'lrob-cookie-consent')],
                        ['v' => 'center', 'l' => __('Center', 'lrob-cookie-consent')],
                    ],
                    'colorsLabel'   => __('Colors', 'lrob-cookie-consent'),
                    'positionLabel' => __('Position', 'lrob-cookie-consent'),
                ],
                'logging' => [
                    'question' => __('Keep a record of consent decisions?', 'lrob-cookie-consent'),
                    'hint'     => __('Recommended for GDPR accountability. Stored locally, IP anonymised.', 'lrob-cookie-consent'),
                ],
            ],
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'scanNonce'  => wp_create_nonce('lrob_cc_scan'),
            'i18n'       => [
                'confirmPurge' => __('Delete all consent log entries? This cannot be undone.', 'lrob-cookie-consent'),
                'confirm'      => __('Confirm', 'lrob-cookie-consent'),
                'cancel'       => __('Cancel', 'lrob-cookie-consent'),
                'removeRow'    => __('Remove', 'lrob-cookie-consent'),
                'catSlug'      => __('slug', 'lrob-cookie-consent'),
                'catLabel'     => __('Label', 'lrob-cookie-consent'),
                'catDesc'      => __('Description', 'lrob-cookie-consent'),
                'selectLogo'   => __('Select logo', 'lrob-cookie-consent'),
                'watermark'    => __('Cookie Consent by LRob', 'lrob-cookie-consent'),
                'serviceName'  => __('Service name (shown to visitors)', 'lrob-cookie-consent'),
                'wizExisting'  => __('You already have block rules. How do you want to run the wizard?', 'lrob-cookie-consent'),
                'wizAddTo'     => __('Add to my current rules', 'lrob-cookie-consent'),
                'wizFresh'     => __('Clear them and start fresh', 'lrob-cookie-consent'),
                'wizYes'       => __('Yes', 'lrob-cookie-consent'),
                'wizNo'        => __('No / skip', 'lrob-cookie-consent'),
                'wizBack'      => __('Back', 'lrob-cookie-consent'),
                'wizNext'      => __('Next', 'lrob-cookie-consent'),
                'wizFinish'    => __('Finish & save', 'lrob-cookie-consent'),
                'wizClose'     => __('Close', 'lrob-cookie-consent'),
                'wizYesKeep'   => __('Yes, keep proof', 'lrob-cookie-consent'),
                'wizNoKeep'    => __('No', 'lrob-cookie-consent'),
                'wizKeepCurrent' => __('Keep current', 'lrob-cookie-consent'),
                /* translators: %1$d: current step number, %2$d: total steps. */
                'wizStep'      => __('Step %1$d of %2$d', 'lrob-cookie-consent'),
                'scanning'     => __('Scanning…', 'lrob-cookie-consent'),
                'scanAgain'    => __('Scan again', 'lrob-cookie-consent'),
                'scanError'    => __('Scan failed. Try again.', 'lrob-cookie-consent'),
                'noneFound'    => __('No third-party resources found on the scanned pages.', 'lrob-cookie-consent'),
                'addSelected'  => __('Add selected as rules', 'lrob-cookie-consent'),
                'known'        => __('known', 'lrob-cookie-consent'),
                'unknown'      => __('review', 'lrob-cookie-consent'),
                'cookiesSeen'  => __('Cookies set on scanned pages:', 'lrob-cookie-consent'),
                'scannedUrls'  => __('Scanned:', 'lrob-cookie-consent'),
                'selectAll'    => __('Select all', 'lrob-cookie-consent'),
                'scanPartial'  => __('Still scanning — results so far:', 'lrob-cookie-consent'),
                'alreadyAdded' => __('added', 'lrob-cookie-consent'),
                'foundOn'      => __('Found on these pages', 'lrob-cookie-consent'),
                /* translators: %d: number of additional pages not listed. */
                'andMore'      => __('…and %d more', 'lrob-cookie-consent'),
                /* translators: %d: number of pages scanned. */
                'scannedCount' => __('Scanned %d pages.', 'lrob-cookie-consent'),
                /* translators: %1$d: current page, %2$d: total pages. */
                'scanProgress' => __('Scanning page %1$d of %2$d…', 'lrob-cookie-consent'),
                'sslFailed'    => __('Some pages could not be reached due to an SSL certificate error.', 'lrob-cookie-consent'),
                'sslRetry'     => __('Retry ignoring SSL', 'lrob-cookie-consent'),
                /* translators: %d: estimated seconds remaining. */
                'secondsLeft'  => __('~%ds left', 'lrob-cookie-consent'),
                /* translators: %d: number of pages to scan. */
                'pagesToScan'  => __('%d pages to scan.', 'lrob-cookie-consent'),
                'hostSlowdown' => __('Your host is limiting requests — slowing the scan down.', 'lrob-cookie-consent'),
                'hostFailed'   => __('Your host could not complete the page-visit scan (it may have very limited resources). The results found so far are still valid.', 'lrob-cookie-consent'),
                'scanComplete' => __('Scan complete.', 'lrob-cookie-consent'),
            ],
        ]);
    }

    public function render(): void
    {
        if (!current_user_can(LROB_CC_CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to access this page.', 'lrob-cookie-consent'));
        }

        $o = Options::all();
        $defaults = Options::defaults();
        $texts = Banner::texts();
        $text_defaults = Banner::default_texts();
        $labels = Banner::category_labels();
        $optional = Categories::optional();
        $default_categories = Categories::defaults();
        $category_rows = Categories::custom(); // only customs are editable; defaults are immutable
        $text_presets = Presets::text();
        $color_presets = Presets::styles()['colors'] ?? [];
        $services = Services::common();
        $scan_types = Scanner::scan_types();
        $log = $this->log;
        $option = Options::OPTION_KEY;
        $log_table = new ConsentLogTable($this->log);
        $log_table->prepare_items();
        $banner_versions = \LRob\CookieConsent\Consent\BannerVersion::all();

        include LROB_CC_PATH . 'views/admin-settings.php';
    }

    /**
     * @param mixed $input
     * @return array<string,mixed>
     */
    public function sanitize($input): array
    {
        $in = is_array($input) ? $input : [];
        $d = Options::defaults();
        $out = $d;

        $bool = ['enabled', 'respect_dnt', 'dnt_hide_banner', 'show_to_logged_in', 'block_iframes',
            'reprompt_on_rule_change', 'log_consent', 'store_user_agent', 'store_wp_user', 'show_accept',
            'show_deny', 'show_save', 'show_customize', 'categories_collapsed', 'revisit_button',
            'show_sources', 'watermark', 'anim_fade', 'keep_data_on_uninstall'];
        foreach ($bool as $key) {
            $out[$key] = empty($in[$key]) ? 0 : 1;
        }
        // Guardrail: the visitor must keep a way to give consent. With "Accept"
        // hidden in the collapsed banner, the only positive path is Customize →
        // Save, so keep those available.
        if ($out['show_accept'] === 0 && $out['categories_collapsed'] === 1) {
            $out['show_customize'] = 1;
            $out['show_save'] = 1;
        }

        $out['consent_type'] = 'optin';
        $out['ip_storage'] = in_array($in['ip_storage'] ?? '', ['hashed', 'full'], true) ? $in['ip_storage'] : 'hashed';
        // Emptying a duration field restores its default instead of falling to 0/1.
        $cd = (string) ($in['cookie_days'] ?? '');
        $out['cookie_days'] = $cd === '' ? $d['cookie_days'] : max(1, (int) $cd);
        $lr = (string) ($in['log_retention_days'] ?? '');
        $out['log_retention_days'] = $lr === '' ? $d['log_retention_days'] : max(0, (int) $lr);
        $out['rules_mode'] =in_array($in['rules_mode'] ?? '', ['structured', 'raw'], true) ? $in['rules_mode'] : 'structured';

        $out['categories'] = [];
        if (isset($in['categories']) && is_array($in['categories'])) {
            $seen = [];
            foreach ($in['categories'] as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $slug = sanitize_key((string) ($c['slug'] ?? ''));
                if ($slug === '' || Categories::is_default($slug) || isset($seen[$slug])) {
                    continue; // defaults are immutable and never stored
                }
                $seen[$slug] = true;
                $out['categories'][] = [
                    'slug'  => $slug,
                    'label' => sanitize_text_field((string) ($c['label'] ?? '')),
                    'desc'  => sanitize_text_field((string) ($c['desc'] ?? '')),
                ];
            }
        }
        // Description overrides for built-in categories (only descriptions, only defaults).
        $out['cat_desc_overrides'] = [];
        if (isset($in['cat_desc_overrides']) && is_array($in['cat_desc_overrides'])) {
            foreach ($in['cat_desc_overrides'] as $slug => $desc) {
                $slug = sanitize_key((string) $slug);
                $desc = sanitize_text_field((string) $desc);
                if ($slug !== '' && $desc !== '' && Categories::is_default($slug)) {
                    $out['cat_desc_overrides'][$slug] = $desc;
                }
            }
        }

        $out['block_rules'] = sanitize_textarea_field((string) ($in['block_rules'] ?? ''));

        $out['inline_scripts'] = [];
        if (isset($in['inline_scripts']) && is_array($in['inline_scripts'])) {
            foreach ($in['inline_scripts'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = trim((string) ($row['code'] ?? ''));
                $category = sanitize_key((string) ($row['category'] ?? ''));
                if ($code === '' || !Categories::is_valid($category) || $category === Categories::FUNCTIONAL) {
                    continue;
                }
                // Trusted (manage_lrob_cc) input, injected as inert text/plain until consent.
                $out['inline_scripts'][] = [
                    'code' => $code,
                    'category' => $category,
                    'name' => sanitize_text_field((string) ($row['name'] ?? '')),
                ];
            }
        }

        $positions = ['top-left', 'top', 'top-right', 'center', 'bottom-left', 'bottom', 'bottom-right'];
        $out['position'] = in_array($in['position'] ?? '', $positions, true) ? $in['position'] : 'bottom-right';
        foreach (['align_title', 'align_text', 'align_buttons'] as $key) {
            $out[$key] = in_array($in[$key] ?? '', ['left', 'center', 'right'], true) ? $in[$key] : 'left';
        }
        $out['align_footer'] = in_array($in['align_footer'] ?? '', ['left', 'center', 'right'], true) ? $in['align_footer'] : 'center';
        $out['footer_links'] = [];
        if (isset($in['footer_links']) && is_array($in['footer_links'])) {
            foreach ($in['footer_links'] as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $label = sanitize_text_field((string) ($link['label'] ?? ''));
                $url = esc_url_raw((string) ($link['url'] ?? ''));
                if ($label !== '' && $url !== '') {
                    $out['footer_links'][] = ['label' => $label, 'url' => $url];
                }
            }
        }
        $themes = array_merge(['auto', 'custom'], array_keys(\LRob\CookieConsent\Frontend\Appearance::palettes()));
        $out['theme'] = in_array($in['theme'] ?? '', $themes, true) ? $in['theme'] : 'auto';
        $out['popup_size'] = in_array($in['popup_size'] ?? '', ['small', 'medium', 'large'], true) ? $in['popup_size'] : 'small';
        $out['density'] = in_array($in['density'] ?? '', ['compact', 'cozy', 'comfortable'], true) ? $in['density'] : 'cozy';
        $out['font_size'] = in_array($in['font_size'] ?? '', ['small', 'medium', 'large'], true) ? $in['font_size'] : 'medium';
        $out['shape'] = in_array($in['shape'] ?? '', ['square', 'rounded', 'pill'], true) ? $in['shape'] : 'rounded';
        $out['backdrop_blur'] = min(30, max(0, (int) ($in['backdrop_blur'] ?? $d['backdrop_blur'])));
        $out['offset_preset'] = in_array($in['offset_preset'] ?? '', ['snug', 'default', 'spacious', 'custom'], true) ? $in['offset_preset'] : 'default';
        $out['offset_x'] = min(200, max(0, (int) ($in['offset_x'] ?? $d['offset_x'])));
        $out['offset_y'] = min(200, max(0, (int) ($in['offset_y'] ?? $d['offset_y'])));
        $out['offset_unit'] = in_array($in['offset_unit'] ?? '', ['px', 'rem', 'em', 'vw', '%'], true) ? $in['offset_unit'] : 'px';
        $out['logo'] = esc_url_raw((string) ($in['logo'] ?? ''));
        $out['logo_height'] = min(200, max(12, (int) ($in['logo_height'] ?? $d['logo_height'])));

        $sd = (string) ($in['show_delay'] ?? '');
        $out['show_delay'] = $sd === '' ? $d['show_delay'] : min(20000, max(0, (int) $sd));
        $out['anim_move'] = in_array($in['anim_move'] ?? '', ['none', 'slide', 'zoom'], true) ? $in['anim_move'] : 'none';
        $out['anim_direction'] = in_array($in['anim_direction'] ?? '', ['top', 'bottom', 'left', 'right'], true) ? $in['anim_direction'] : 'bottom';
        $as = (string) ($in['anim_speed'] ?? '');
        $out['anim_speed'] = $as === '' ? $d['anim_speed'] : min(2000, max(0, (int) $as));

        foreach (['color_bg', 'color_text', 'color_title', 'color_border', 'color_btn_bg',
            'color_btn_text', 'color_btn_deny_bg', 'color_btn_deny_text'] as $key) {
            $color = sanitize_hex_color((string) ($in[$key] ?? ''));
            $out[$key] = $color ?: $d[$key];
        }
        // Hover colours may be left empty (= auto-darken default).
        foreach (['color_btn_hover_bg', 'color_btn_deny_hover_bg'] as $key) {
            $raw = (string) ($in[$key] ?? '');
            $out[$key] = $raw === '' ? '' : (sanitize_hex_color($raw) ?: '');
        }

        $out['text_header'] = sanitize_text_field((string) ($in['text_header'] ?? ''));
        $out['text_accept'] = sanitize_text_field((string) ($in['text_accept'] ?? ''));
        $out['text_deny'] = sanitize_text_field((string) ($in['text_deny'] ?? ''));
        $out['text_save'] = sanitize_text_field((string) ($in['text_save'] ?? ''));
        $out['text_customize'] = sanitize_text_field((string) ($in['text_customize'] ?? ''));
        $out['text_continue'] = sanitize_text_field((string) ($in['text_continue'] ?? ''));
        $out['deny_style'] = in_array($in['deny_style'] ?? '', ['button', 'link'], true) ? $in['deny_style'] : 'button';
        $out['deny_link_position'] = in_array($in['deny_link_position'] ?? '', ['under-buttons', 'under-box', 'top', 'near-close'], true) ? $in['deny_link_position'] : 'under-buttons';
        $out['text_message'] = wp_kses_post((string) ($in['text_message'] ?? ''));
        $out['revisit_text'] = sanitize_text_field((string) ($in['revisit_text'] ?? ''));
        $out['text_preset'] = sanitize_text_field((string) ($in['text_preset'] ?? ''));

        // Rule/category changes alter what's blocked → drop the compiled cache.
        Rules::flush();

        return $out;
    }

    public function handle_export(): void
    {
        if (!current_user_can(LROB_CC_CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to do this.', 'lrob-cookie-consent'));
        }
        check_admin_referer('lrob_cc_export_log');
        $this->log->stream_csv();
    }

    public function handle_purge(): void
    {
        if (!current_user_can(LROB_CC_CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to do this.', 'lrob-cookie-consent'));
        }
        check_admin_referer('lrob_cc_purge_log');
        $this->log->purge_all();
        wp_safe_redirect(add_query_arg(
            ['page' => self::SLUG, 'tab' => 'log', 'purged' => '1'],
            admin_url('options-general.php')
        ));
        exit;
    }
}
