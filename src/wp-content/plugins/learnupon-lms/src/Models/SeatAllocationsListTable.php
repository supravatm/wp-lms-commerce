<?php

declare(strict_types=1);

namespace LearnUponLMS\Models;

use LearnUponLMS\Models\SeatAllocationRepository;

final class SeatAllocationsListTable extends \WP_List_Table
{
    private SeatAllocationRepository $repository;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'seat_allocation',
            'plural'   => 'seat_allocations',
            'ajax'     => false,
        ]);

        $this->repository = new SeatAllocationRepository();
    }

    public function get_columns(): array
    {
        return [
            'order_id'        => __('Order', 'learnupon-lms'),
            'order_item_id'   => __('Order Item', 'learnupon-lms'),
            'head_manager_user_id' => __('Head Manager', 'learnupon-lms'),
            'product_name'    => __('Product / Course', 'learnupon-lms'),
            'purchased_seats' => __('Purchased', 'learnupon-lms'),
            'allocated_seats' => __('Allocated', 'learnupon-lms'),
            'available_seats' => __('Available', 'learnupon-lms'),
            'status'          => __('Status', 'learnupon-lms'),
            'updated_at'      => __('Last Updated', 'learnupon-lms'),
        ];
    }

    public function get_sortable_columns(): array
    {
        return [
            'order_id' => ['order_id', false],
            'purchased_seats' => ['purchased_seats', false],
            'allocated_seats' => ['allocated_seats', false],
            'updated_at' => ['updated_at', false],
        ];
    }

    public function prepare_items(): void
    {
        $perPage = 20;
        $currentPage = $this->get_pagenum();
        $search = sanitize_text_field(
            wp_unslash($_REQUEST['s'] ?? '')
        );

        $totalItems = $this->repository->countRows($search);

        $this->items = $this->repository->getRows(
            $perPage,
            ($currentPage - 1) * $perPage,
            $search
        );

        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($totalItems / $perPage),
        ]);

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    public function column_order_id(array $item): string
    {
        return sprintf(
            '<a href="%s">#%d</a>',
            esc_url(
                admin_url(
                    'admin.php?page=wc-orders&action=edit&id=' .
                        absint($item['order_id'])
                )
            ),
            absint($item['order_id'])
        );
    }

    public function column_head_manager_user_id(array $item): string
    {
        // $name = $item['head_manager_name'] ?: __('Unknown user', 'learnupon-lms');
        $email = $item['head_manager_email'] ?? '';

        return sprintf('%s', esc_html($email));
    }

    public function column_product_name(array $item): string
    {
        $name = $item['product_name'] ?: __('Unknown product', 'learnupon-lms');
        $courseId = $item['course_id'] ?? '';

        if ($courseId !== '') {
            $name .= ' (' . sprintf(
                /* translators: %s is a LearnUpon course ID. */
                __('Course ID: %s', 'learnupon-lms'),
                $courseId
            ) . ')';
        }

        return esc_html($name);
    }

    public function column_available_seats(array $item): int
    {
        return max(
            0,
            (int) $item['purchased_seats'] - (int) $item['allocated_seats']
        );
    }

    /**
     * Render the default column value.
     *
     * @param array  $item        Current row data.
     * @param string $columnName Column name.
     * @return string
     */
    protected function column_default($item, $columnName)
    {
        if (in_array(
            $columnName,
            ['purchased_seats', 'allocated_seats', 'order_item_id'],
            true
        )) {
            return esc_html((string) ($item[$columnName] ?? 0));
        }

        if ($columnName === 'status') {
            return esc_html((string) $item['status']);
        }

        if ($columnName === 'updated_at') {
            return esc_html((string) $item['updated_at']);
        }

        return '—';
    }

    public function no_items(): void
    {
        esc_html_e(
            'No seat allocations found.',
            'learnupon-lms'
        );
    }
}
