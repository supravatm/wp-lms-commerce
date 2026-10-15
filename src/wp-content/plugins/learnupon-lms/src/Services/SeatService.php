<?php

declare(strict_types=1);

namespace LearnUponLMS\Services;

/**
 * Business rules for seats: creating trackers from an order, allocating seats
 * to group managers, assigning seats to learners.
 *
 * TODO: pull the logic out of Controllers\AdminBulkOrder::mark_bulk_order().
 * Wrap multi-item creation in a DB transaction and keep
 * allocated_seats on the tracker in sync with assignments.
 */
final class SeatService
{
}
