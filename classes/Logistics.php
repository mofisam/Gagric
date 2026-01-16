<?php
class Logistics {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function calculateCost($origin, $destination, $weight, $partner = 'sendy') {
        require_once '../config/logistics.php';
        return LogisticsAPI::calculateShipping($origin, $destination, $weight, $partner);
    }

    public function createShipment($orderId, $partner = 'sendy') {
        $order = $this->db->fetchOne(
            "SELECT o.*, os.* FROM orders o 
             JOIN order_shipping_details os ON o.id = os.order_id 
             WHERE o.id = ?",
            [$orderId]
        );

        if (!$order) {
            throw new Exception("Order not found");
        }

        $shipmentData = [
            'order_id' => $orderId,
            'recipient' => $order['shipping_name'],
            'address' => $order['address_line'],
            'city' => $order['city_id'],
            'state' => $order['state_id'],
            'phone' => $order['shipping_phone']
        ];

        $result = LogisticsAPI::createShipment($shipmentData, $partner);
        
        // Update order with tracking info
        $this->db->update(
            'order_shipping_details',
            [
                'tracking_number' => $result['tracking_number'],
                'logistics_partner' => $partner
            ],
            'order_id = ?',
            [$orderId]
        );

        return $result;
    }

    public function trackShipment($trackingNumber) {
        require_once '../../config/logistics.php';
        
        $shipment = $this->db->fetchOne(
            "SELECT * FROM order_shipping_details WHERE tracking_number = ?",
            [$trackingNumber]
        );

        if (!$shipment) {
            return null;
        }

        // In production, integrate with actual logistics API
        $status = [
            'tracking_number' => $trackingNumber,
            'status' => 'in_transit',
            'estimated_delivery' => $shipment['estimated_delivery'],
            'partner' => $shipment['logistics_partner']
        ];

        return $status;
    }

    public function getAvailablePartners($state) {
        require_once '../config/logistics.php';
        return LogisticsAPI::getPartnersByState($state);
    }
}
?>