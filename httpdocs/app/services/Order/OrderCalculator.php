<?php
namespace App\Services\Order;

use App\Core\DependencyFactory;

/**
 * Order Calculator
 * Handles order total calculations, tax calculations, and pricing logic
 */
class OrderCalculator {
    private $menuItemService;
    private $settingsService;
    
    public function __construct() {
        $this->menuItemService = DependencyFactory::getMenuItemService();
        $this->settingsService = DependencyFactory::getSystemSettingsService();
    }
    
    /**
     * Calculate total amount for order items
     * @param array $items Array of order items with menu_item_id, quantity, price, etc.
     * @return float Total amount
     */
    public function calculateOrderTotal(array $items): float {
        $total = 0.0;
        
        foreach ($items as $item) {
            $quantity = floatval($item['quantity'] ?? 1);
            $price = floatval($item['price'] ?? 0);
            
            // Calculate item subtotal
            $itemSubtotal = $quantity * $price;
            
            // Add extras if any
            if (isset($item['selected_extras']) && is_array($item['selected_extras'])) {
                foreach ($item['selected_extras'] as $extra) {
                    if (isset($extra['price'])) {
                        $itemSubtotal += floatval($extra['price']) * $quantity;
                    }
                }
            }
            
            $total += $itemSubtotal;
        }
        
        return round($total, 2);
    }
    
    /**
     * Calculate tax amount
     * @param float $amount Base amount
     * @param float|null $taxRate Tax rate (percentage). If null, gets from settings
     * @return float Tax amount
     */
    public function calculateTax(float $amount, ?float $taxRate = null): float {
        if ($taxRate === null) {
            $settings = $this->settingsService->getSettings();
            $taxRate = floatval($settings['tax_rate'] ?? 0);
        }
        
        if ($taxRate <= 0) {
            return 0.0;
        }
        
        return round($amount * ($taxRate / 100), 2);
    }
    
    /**
     * Calculate service charge
     * @param float $amount Base amount
     * @param float|null $serviceChargeRate Service charge rate (percentage). If null, gets from settings
     * @return float Service charge amount
     */
    public function calculateServiceCharge(float $amount, ?float $serviceChargeRate = null): float {
        if ($serviceChargeRate === null) {
            $settings = $this->settingsService->getSettings();
            $serviceChargeRate = floatval($settings['service_charge_rate'] ?? 0);
        }
        
        if ($serviceChargeRate <= 0) {
            return 0.0;
        }
        
        return round($amount * ($serviceChargeRate / 100), 2);
    }
    
    /**
     * Calculate final total with tax and service charge
     * @param float $subtotal Base subtotal
     * @param float|null $taxRate Tax rate (percentage)
     * @param float|null $serviceChargeRate Service charge rate (percentage)
     * @return array ['subtotal' => float, 'tax' => float, 'service_charge' => float, 'total' => float]
     */
    public function calculateFinalTotal(float $subtotal, ?float $taxRate = null, ?float $serviceChargeRate = null): array {
        $tax = $this->calculateTax($subtotal, $taxRate);
        $serviceCharge = $this->calculateServiceCharge($subtotal, $serviceChargeRate);
        $total = $subtotal + $tax + $serviceCharge;
        
        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'service_charge' => round($serviceCharge, 2),
            'total' => round($total, 2)
        ];
    }
    
    /**
     * Get item price from menu item
     * @param string $menuItemId Menu item ID
     * @return float Item price
     */
    public function getItemPrice(string $menuItemId): float {
        $menuItem = $this->menuItemService->getById($menuItemId);
        if ($menuItem && isset($menuItem['price'])) {
            return floatval($menuItem['price']);
        }
        return 0.0;
    }
}

