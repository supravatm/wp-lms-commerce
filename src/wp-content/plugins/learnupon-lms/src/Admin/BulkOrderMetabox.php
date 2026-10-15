<?php

declare(strict_types=1);

namespace LearnUponLMS\Admin;

/**
 * WooCommerce order metabox and the "mark as bulk order" action.
 *
 * TODO: move from Controllers\AdminBulkOrder. Delegate seat creation to
 * Services\SeatService and wrap it in a transaction so retries are safe.
 */
final class BulkOrderMetabox
{
    public function register(): void
    {
        // Register legacy and HPOS metaboxes here.
    }
}
