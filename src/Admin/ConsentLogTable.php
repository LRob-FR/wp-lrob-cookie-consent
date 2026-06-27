<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Admin;

use LRob\CookieConsent\Consent\LogRepository;

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/** Audit browser for consent records: sortable, paginated, per-row + bulk delete. */
final class ConsentLogTable extends \WP_List_Table
{
    public function __construct(private LogRepository $repo)
    {
        parent::__construct(['singular' => 'consent', 'plural' => 'consents', 'ajax' => false]);
    }

    /** @return array<string,string> */
    public function get_columns(): array
    {
        return [
            'cb'             => '<input type="checkbox" />',
            'created_at'     => __('Date (UTC)', 'lrob-cookie-consent'),
            'consent_id'     => __('Visitor ID', 'lrob-cookie-consent'),
            'ip'             => __('IP', 'lrob-cookie-consent'),
            'user_id'        => __('WP user', 'lrob-cookie-consent'),
            'event_type'     => __('Event', 'lrob-cookie-consent'),
            'method'         => __('Act', 'lrob-cookie-consent'),
            'choices'        => __('Choices', 'lrob-cookie-consent'),
            'banner_version' => __('Cookie consent version', 'lrob-cookie-consent'),
            'expires_at'     => __('Renew by', 'lrob-cookie-consent'),
        ];
    }

    /** @return array<string,array{0:string,1:bool}> */
    public function get_sortable_columns(): array
    {
        return [
            'created_at' => ['created_at', true],
            'event_type' => ['event_type', false],
            'method'     => ['method', false],
        ];
    }

    protected function get_bulk_actions(): array
    {
        return ['delete' => __('Delete', 'lrob-cookie-consent')];
    }

    /** @param array<string,mixed> $item */
    protected function column_cb($item): string
    {
        return sprintf('<input type="checkbox" name="consent[]" value="%d" />', (int) $item['id']);
    }

    /** @param array<string,mixed> $item */
    protected function column_created_at($item): string
    {
        $url = add_query_arg([
            'page'     => 'lrob-cookie-consent',
            'tab'      => 'log',
            'action'   => 'delete',
            'consent'  => (int) $item['id'],
            '_wpnonce' => wp_create_nonce('lrob_cc_log_row_' . (int) $item['id']),
        ], admin_url('options-general.php'));
        $actions = ['delete' => sprintf('<a href="%s" class="submitdelete">%s</a>', esc_url($url), esc_html__('Delete', 'lrob-cookie-consent'))];
        return esc_html((string) $item['created_at']) . $this->row_actions($actions);
    }

    /** @param array<string,mixed> $item */
    protected function column_ip($item): string
    {
        $ip = (string) ($item['ip'] ?? '');
        if ($ip === '') {
            return '—';
        }
        // Full IP shows as-is; a stored hash shows in full (it's the whole value).
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return esc_html($ip);
        }
        return '<code title="' . esc_attr__('Stored as a salted hash', 'lrob-cookie-consent') . '">' . esc_html($ip) . '</code>';
    }

    /** @param array<string,mixed> $item */
    protected function column_user_id($item): string
    {
        $uid = (int) ($item['user_id'] ?? 0);
        if ($uid <= 0) {
            return '—';
        }
        $user = get_userdata($uid);
        if (!$user) {
            return '#' . $uid;
        }
        $url = get_edit_user_link($uid);
        $name = $user->display_name !== '' ? $user->display_name : $user->user_login;
        return $url ? sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($name)) : esc_html($name);
    }

    /** @param array<string,mixed> $item */
    protected function column_choices($item): string
    {
        $choices = json_decode((string) ($item['choices'] ?? ''), true);
        if (!is_array($choices) || $choices === []) {
            return '—';
        }
        $parts = [];
        foreach ($choices as $cat => $allow) {
            $mark = $allow ? '✓' : '✗';
            $parts[] = '<span class="lrob-cc-choice">' . esc_html((string) $cat) . ' ' . $mark . '</span>';
        }
        return implode(' ', $parts);
    }

    /** @param array<string,mixed> $item */
    protected function column_banner_version($item): string
    {
        $hash = (string) ($item['banner_version'] ?? '');
        if ($hash === '') {
            return '—';
        }
        return sprintf(
            '<a href="#lrob-cc-ver-%1$s" class="lrob-cc-ver-link"><code>%2$s</code></a>',
            esc_attr($hash),
            esc_html(substr($hash, 0, 12))
        );
    }

    /**
     * @param array<string,mixed> $item
     * @param string $column_name
     */
    protected function column_default($item, $column_name): string
    {
        if ($column_name === 'consent_id') {
            return '<code>' . esc_html(substr((string) ($item[$column_name] ?? ''), 0, 12)) . '</code>';
        }
        return esc_html((string) ($item[$column_name] ?? ''));
    }

    public function prepare_items(): void
    {
        $this->process_actions();

        $per_page = 30;
        $offset = ($this->get_pagenum() - 1) * $per_page;
        $orderby = isset($_GET['orderby']) ? sanitize_key((string) $_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_key((string) $_GET['order']) : 'desc';

        $res = $this->repo->query($per_page, $offset, $orderby, $order);
        $this->items = $res['rows'];
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'created_at'];
        $this->set_pagination_args(['total_items' => $res['total'], 'per_page' => $per_page]);
    }

    private function process_actions(): void
    {
        if ($this->current_action() !== 'delete') {
            return;
        }
        $consent = $_REQUEST['consent'] ?? null;
        if (is_array($consent)) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $this->repo->delete_ids(array_map('intval', $consent));
        } elseif ($consent !== null) {
            $id = (int) $consent;
            check_admin_referer('lrob_cc_log_row_' . $id);
            $this->repo->delete($id);
        }
    }
}
