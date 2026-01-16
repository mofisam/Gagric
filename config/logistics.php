<?php
class LogisticsAPI {
    public static function calculateShipping($origin_state, $destination_state, $weight) {
        $base_cost = 500;
        $weight_cost = $weight * 50;
        $interstate = $origin_state !== $destination_state;
        $distance_cost = $interstate ? 1000 : 300;
        
        return [
            'cost' => $base_cost + $weight_cost + $distance_cost,
            'estimated_days' => $interstate ? 3 : 1,
            'partner' => 'sendy'
        ];
    }
    
    public static function createShipment($order_data) {
        return [
            'tracking_number' => 'TRK' . time() . rand(1000, 9999),
            'status' => 'pending'
        ];
    }
}
?>