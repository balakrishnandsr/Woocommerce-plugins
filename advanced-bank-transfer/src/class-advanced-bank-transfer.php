<?php

class AdvancedBankTransfer
{
    /**
     * @var null | AdvancedBankTransfer
     */
    static $_app = null;

    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * @return AdvancedBankTransfer|null
     */
    public static function init()
    {
        if (is_null(self::$_app)) {
            self::$_app = new AdvancedBankTransfer();
        }
        return self::$_app;
    }

    /**
     * init all the plugin related hooks
     */
    public function init_hooks()
    {
        add_action('woocommerce_init', array($this, 'woocommerce_init'));
    }

    /**
     * init all woocommerce related hooks
     */
    public function woocommerce_init()
    {
        include ABACS_DIR . '/includes/class-wc-gateway-abacs.php';
        add_filter('woocommerce_payment_gateways',function ($gateways){
            $gateways[] = 'WC_Gateway_ABACS';
            return $gateways;
        });
    }
}