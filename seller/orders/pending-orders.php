<?php
/**
 * seller/orders/pending-orders.php
 *
 * manage-orders.php already fully supports filtering by status (including
 * pagination, search, and the order table itself), so rather than duplicate
 * that entire page here (and risk the two drifting out of sync over time),
 * this just forwards straight into that same view pre-filtered to pending -
 * exactly what the "Pending" sidebar link expects to land on.
 */
require_once '../../includes/auth.php';
requireSeller();

header('Location: manage-orders.php?status=pending');
exit;