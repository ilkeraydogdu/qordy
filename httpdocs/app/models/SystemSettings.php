<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class SystemSettings extends \App\Core\Model {
    protected $table = 'system_settings';
    
    public function getSettings() {
        return $this->query()
            ->limit(1)
            ->first();
    }
    
    public function updateSettings($data) {
        $settings = $this->getSettings();
        
        if ($settings) {
            return $this->query()
                ->where('id', $settings['id'])
                ->update($data);
        } else {
            return $this->query()
                ->insert($data);
        }
    }
    
    public function getServiceChargeRate() {
        $settings = $this->getSettings();
        return $settings ? $settings['service_charge_rate'] : 0;
    }
    
    public function getCoverCharge() {
        $settings = $this->getSettings();
        return $settings ? $settings['cover_charge'] : 0;
    }
    
    public function getCurrency() {
        $settings = $this->getSettings();
        return $settings ? $settings['currency'] : 'TRY';
    }
    
    public function getFormattedCurrency() {
        $currency = $this->getCurrency();
        
        // Try to get from system_labels, fallback to hardcoded
        try {
            require_once __DIR__ . '/SystemLabel.php';
            $labelModel = new \App\Models\SystemLabel();
            $currencyLabel = $labelModel->getLabel('currency_symbol', $currency);
            if ($currencyLabel) {
                return $currencyLabel['label_value_tr'] ?? $currency;
            }
        } catch (\Exception $e) {
            // Fallback to hardcoded
        }
        
        // Fallback currency symbols (standard currencies)
        $currencies = [
            'TRY' => '₺',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£'
        ];
        
        return $currencies[$currency] ?? $currency;
    }
}