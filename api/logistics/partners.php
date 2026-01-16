<?php
header('Content-Type: application/json');
require_once '../../config/logistics.php';

$partners = [
    [
        'id' => 'sendy',
        'name' => 'Sendy',
        'coverage' => ['Lagos', 'Abuja', 'Port Harcourt', 'Ibadan'],
        'delivery_time' => '1-3 days',
        'support_phone' => '+234-800-000-0000'
    ],
    [
        'id' => 'max_ng',
        'name' => 'MAX.NG',
        'coverage' => ['Lagos', 'Abuja'],
        'delivery_time' => '1-2 days', 
        'support_phone' => '+234-700-000-0000'
    ],
    [
        'id' => 'gig_logistics',
        'name' => 'GIG Logistics',
        'coverage' => ['All major states'],
        'delivery_time' => '2-5 days',
        'support_phone' => '+234-900-000-0000'
    ]
];

echo json_encode(['partners' => $partners]);
?>