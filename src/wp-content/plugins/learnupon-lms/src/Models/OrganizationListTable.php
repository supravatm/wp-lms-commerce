<?php

declare(strict_types=1);

namespace LearnUponLMS\Models;

final class OrganizationListTable extends \WP_List_Table
{
    private OrganizationListRepository $repository;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'staff_assignment',
            'plural'   => 'staff_assignments',
            'ajax'     => false,
        ]);

        $this->repository = new OrganizationListRepository();
    }

    public function get_columns(): array
    {
        return [
            'organization_id' => __('Organization ID', 'learnupon-lms'),
            'organization_name' => __('Organization', 'learnupon-lms'),
            'facility_name' => __('Facility', 'learnupon-lms'),
            'facility_type' => __('Facility Type', 'learnupon-lms'),
            'staff_user_id' => __('User ID', 'learnupon-lms'),
            'staff_email' => __('Staff Email', 'learnupon-lms'),
            'staff_role' => __('Role', 'learnupon-lms'),
            'staff_categories' => __('Categories', 'learnupon-lms'),
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
            [],
        ];
    }

    public function column_organization_id(array $item): string
    {
        return esc_html(
            (string) absint($item['organization_id'] ?? 0)
        );
    }

    public function column_organization_name(array $item): string
    {
        return esc_html(
            (string) ($item['organization_name'] ?? '')
        );
    }

    public function column_facility_name(array $item): string
    {
        return esc_html(
            (string) ($item['facility_name'] ?? '—')
        );
    }

    public function column_facility_type(array $item): string
    {
        return esc_html(
            (string) ($item['facility_type'] ?? '—')
        );
    }

    public function column_staff_user_id(array $item): string
    {
        return esc_html(
            (string) absint($item['staff_user_id'] ?? 0)
        );
    }

    public function column_staff_email(array $item): string
    {
        return esc_html(
            (string) ($item['staff_email'] ?? '')
        );
    }

    public function column_staff_role(array $item): string
    {
        return esc_html(
            (string) ($item['staff_role'] ?? '')
        );
    }

    public function column_staff_categories(array $item): string
    {
        $categories = (string) ($item['staff_categories'] ?? '');

        return $categories !== ''
            ? esc_html($categories)
            : '—';
    }

    protected function column_default($item, $columnName): string
    {
        return esc_html(
            (string) ($item[$columnName] ?? '—')
        );
    }

    public function no_items(): void
    {
        esc_html_e(
            'No Organization staff assignments found.',
            'learnupon-lms'
        );
    }
}
