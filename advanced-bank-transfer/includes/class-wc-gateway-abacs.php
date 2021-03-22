<?php
/**
 * Class WC_Gateway_ABACS file.
 *
 * @package WooCommerce\Gateways
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Bank Transfer Payment Gateway.
 *
 * Provides a Bank Transfer Payment Gateway. Based on code by Mike Pepper.
 *
 * @class       WC_Gateway_ABACS
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce\Classes\Payment
 */
class WC_Gateway_ABACS extends WC_Payment_Gateway
{
    /**
     * Array of locales
     *
     * @var array
     */
    public $locale;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id = 'abacs';
        $this->icon = apply_filters('woocommerce_bacs_icon', '');
        $this->has_fields = false;
        $this->method_title = __('Advanced direct bank transfer', 'woocommerce-abacs');
        $this->method_description = __('Take payments in person via ABACS. More commonly known as direct bank/wire transfer', 'woocommerce-abacs');
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        // Define user set variables.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        // BACS account fields shown on the thanks page and in emails.
        $this->account_details = get_option(
            'woocommerce_abacs_accounts',
            array(
                array(
                    'account_name' => $this->get_option('account_name'),
                    'account_number' => $this->get_option('account_number'),
                    'sort_code' => $this->get_option('sort_code'),
                    'bank_name' => $this->get_option('bank_name'),
                    'iban' => $this->get_option('iban'),
                    'bic' => $this->get_option('bic'),
                ),
            )
        );
        // Actions.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'save_account_details'));
        add_action('woocommerce_thankyou_abacs', array($this, 'thankyou_page'));
        // Customer Emails.
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        // Custom css
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-abacs'),
                'type' => 'checkbox',
                'label' => __('Enable bank transfer', 'woocommerce-abacs'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-abacs'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-abacs'),
                'default' => __('Advanced direct bank transfer', 'woocommerce-abacs'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-abacs'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce-abacs'),
                'default' => __('Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.', 'woocommerce-abacs'),
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => __('Instructions', 'woocommerce-abacs'),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce-abacs'),
                'default' => '',
                'desc_tip' => true,
            ),
            'account_details' => array(
                'type' => 'account_details',
            ),
        );
    }

    /**
     * Generate account details html.
     *
     * @return string
     */
    public function generate_account_details_html()
    {
        ob_start();
        $country = WC()->countries->get_base_country();
        $locale = $this->get_country_locale();
        // Get sortcode label in the $locale array and use appropriate one.
        $sortcode = isset($locale[$country]['sortcode']['label']) ? $locale[$country]['sortcode']['label'] : __('Sort code', 'woocommerce-abacs');
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php esc_html_e('Account details:', 'woocommerce-abacs'); ?></th>
            <td class="forminp" id="abacs_accounts">
                <div class="wc_input_table_wrapper">
                    <table class="widefat wc_input_table sortable" cellspacing="0">
                        <thead>
                        <tr>
                            <th class="sort">&nbsp;</th>
                            <th><?php esc_html_e('Account name', 'woocommerce-abacs'); ?></th>
                            <th><?php esc_html_e('Account number', 'woocommerce-abacs'); ?></th>
                            <th><?php esc_html_e('Bank name', 'woocommerce-abacs'); ?></th>
                            <th><?php echo esc_html($sortcode); ?></th>
                            <th><?php esc_html_e('IBAN', 'woocommerce-abacs'); ?></th>
                            <th><?php esc_html_e('BIC / Swift', 'woocommerce-abacs'); ?></th>
                        </tr>
                        </thead>
                        <tbody class="accounts">
                        <?php
                        $i = -1;
                        if ($this->account_details) {
                            foreach ($this->account_details as $account) {
                                $i++;
                                echo '<tr class="account">
										<td class="sort"></td>
										<td><input type="text" value="' . esc_attr(wp_unslash($account['account_name'])) . '" name="abacs_account_name[' . esc_attr($i) . ']" /></td>
										<td><input type="text" value="' . esc_attr($account['account_number']) . '" name="abacs_account_number[' . esc_attr($i) . ']" /></td>
										<td><input type="text" value="' . esc_attr(wp_unslash($account['bank_name'])) . '" name="abacs_bank_name[' . esc_attr($i) . ']" /></td>
										<td><input type="text" value="' . esc_attr($account['sort_code']) . '" name="abacs_sort_code[' . esc_attr($i) . ']" /></td>
										<td><input type="text" value="' . esc_attr($account['iban']) . '" name="abacs_iban[' . esc_attr($i) . ']" /></td>
										<td><input type="text" value="' . esc_attr($account['bic']) . '" name="abacs_bic[' . esc_attr($i) . ']" /></td>
									</tr>';
                            }
                        }
                        ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="7"><a href="#"
                                               class="add button"><?php esc_html_e('+ Add account', 'woocommerce-abacs'); ?></a>
                                <a href="#"
                                   class="remove_rows button"><?php esc_html_e('Remove selected account(s)', 'woocommerce-abacs'); ?></a>
                            </th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
                <script type="text/javascript">
                    jQuery(function () {
                        jQuery('#abacs_accounts').on('click', 'a.add', function () {

                            var size = jQuery('#abacs_accounts').find('tbody .account').length;

                            jQuery('<tr class="account">\
									<td class="sort"></td>\
									<td><input type="text" name="bacs_account_name[' + size + ']" /></td>\
									<td><input type="text" name="bacs_account_number[' + size + ']" /></td>\
									<td><input type="text" name="bacs_bank_name[' + size + ']" /></td>\
									<td><input type="text" name="bacs_sort_code[' + size + ']" /></td>\
									<td><input type="text" name="bacs_iban[' + size + ']" /></td>\
									<td><input type="text" name="bacs_bic[' + size + ']" /></td>\
								</tr>').appendTo('#abacs_accounts table tbody');

                            return false;
                        });
                    });
                </script>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Save account details table.
     */
    public function save_account_details()
    {
        $accounts = array();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
        if (isset($_POST['abacs_account_name']) && isset($_POST['abacs_account_number']) && isset($_POST['abacs_bank_name'])
            && isset($_POST['abacs_sort_code']) && isset($_POST['abacs_iban']) && isset($_POST['abacs_bic'])) {
            $account_names = wc_clean(wp_unslash($_POST['abacs_account_name']));
            $account_numbers = wc_clean(wp_unslash($_POST['abacs_account_number']));
            $bank_names = wc_clean(wp_unslash($_POST['abacs_bank_name']));
            $sort_codes = wc_clean(wp_unslash($_POST['abacs_sort_code']));
            $ibans = wc_clean(wp_unslash($_POST['abacs_iban']));
            $bics = wc_clean(wp_unslash($_POST['abacs_bic']));
            foreach ($account_names as $i => $name) {
                if (!isset($account_names[$i])) {
                    continue;
                }
                $accounts[] = array(
                    'account_name' => $account_names[$i],
                    'account_number' => $account_numbers[$i],
                    'bank_name' => $bank_names[$i],
                    'sort_code' => $sort_codes[$i],
                    'iban' => $ibans[$i],
                    'bic' => $bics[$i],
                );
            }
        }
        // phpcs:enable
        update_option('woocommerce_abacs_accounts', $accounts);
    }

    /**
     * Output for the order received page.
     *
     * @param int $order_id Order ID.
     */
    public function thankyou_page($order_id)
    {
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize(wp_kses_post($this->instructions))));
        }
        $this->bank_details($order_id);
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order Order object.
     * @param bool $sent_to_admin Sent to admin.
     * @param bool $plain_text Email format: plain text or HTML.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if (!$sent_to_admin && 'abacs' === $order->get_payment_method() && $order->has_status('on-hold')) {
            if ($this->instructions) {
                echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
            }
            $this->bank_details($order->get_id());
        }
    }

    /**
     * Get bank details and place into a list format.
     *
     * @param int $order_id Order ID.
     */
    private function bank_details($order_id = '')
    {
        if (empty($this->account_details)) {
            return;
        }
        // Get order and store in $order.
        $order = wc_get_order($order_id);
        // Get the order country and country $locale.
        $country = $order->get_billing_country();
        $locale = $this->get_country_locale();
        // Get sortcode label in the $locale array and use appropriate one.
        $sortcode = isset($locale[$country]['sortcode']['label']) ? $locale[$country]['sortcode']['label'] : __('Sort code', 'woocommerce-abacs');
        $bacs_accounts = apply_filters('woocommerce_abacs_accounts', $this->account_details, $order_id);
        if (!empty($bacs_accounts)) {
            $account_html = '';
            $has_details = false;
            foreach ($bacs_accounts as $bacs_account) {
                $bacs_account = (object)$bacs_account;
                if ($bacs_account->account_name) {
                    $account_html .= '<h3 class="wc-abacs-bank-details-account-name">' . wp_kses_post(wp_unslash($bacs_account->account_name)) . ':</h3>' . PHP_EOL;
                }
                $account_html .= '<ul class="wc-abacs-bank-details order_details bacs_details">' . PHP_EOL;
                // BACS account fields shown on the thanks page and in emails.
                $account_fields = apply_filters(
                    'woocommerce_bacs_account_fields',
                    array(
                        'bank_name' => array(
                            'label' => __('Bank', 'woocommerce-abacs'),
                            'value' => $bacs_account->bank_name,
                        ),
                        'account_number' => array(
                            'label' => __('Account number', 'woocommerce-abacs'),
                            'value' => $bacs_account->account_number,
                        ),
                        'sort_code' => array(
                            'label' => $sortcode,
                            'value' => $bacs_account->sort_code,
                        ),
                        'iban' => array(
                            'label' => __('IBAN', 'woocommerce-abacs'),
                            'value' => $bacs_account->iban,
                        ),
                        'bic' => array(
                            'label' => __('BIC', 'woocommerce-abacs'),
                            'value' => $bacs_account->bic,
                        ),
                    ),
                    $order_id
                );
                foreach ($account_fields as $field_key => $field) {
                    if (!empty($field['value'])) {
                        $account_html .= '<li class="' . esc_attr($field_key) . '">' . wp_kses_post($field['label']) . ': <strong>' . wp_kses_post(wptexturize($field['value'])) . '</strong></li>' . PHP_EOL;
                        $has_details = true;
                    }
                }
                $account_html .= '</ul>';
            }
            if ($has_details) {
                echo '<section class="woocommerce-abacs-bank-details"><h2 class="wc-abacs-bank-details-heading">' . esc_html__('Our bank details', 'woocommerce-abacs') . '</h2>' . wp_kses_post(PHP_EOL . $account_html) . '</section>';
            }
        }
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order->get_total() > 0) {
            // Mark as on-hold (we're awaiting the payment).
            $order->update_status(apply_filters('woocommerce_abacs_process_payment_order_status', 'on-hold', $order), __('Awaiting BACS payment', 'woocommerce-abacs'));
        } else {
            $order->payment_complete();
        }
        // Remove cart.
        WC()->cart->empty_cart();
        // Return thankyou redirect.
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Get country locale if localized.
     *
     * @return array
     */
    public function get_country_locale()
    {
        if (empty($this->locale)) {
            // Locale information to be used - only those that are not 'Sort Code'.
            $this->locale = apply_filters(
                'woocommerce_get_abacs_locale',
                array(
                    'AU' => array(
                        'sortcode' => array(
                            'label' => __('BSB', 'woocommerce-abacs'),
                        ),
                    ),
                    'CA' => array(
                        'sortcode' => array(
                            'label' => __('Bank transit number', 'woocommerce-abacs'),
                        ),
                    ),
                    'IN' => array(
                        'sortcode' => array(
                            'label' => __('IFSC', 'woocommerce-abacs'),
                        ),
                    ),
                    'IT' => array(
                        'sortcode' => array(
                            'label' => __('Branch sort', 'woocommerce-abacs'),
                        ),
                    ),
                    'NZ' => array(
                        'sortcode' => array(
                            'label' => __('Bank code', 'woocommerce-abacs'),
                        ),
                    ),
                    'SE' => array(
                        'sortcode' => array(
                            'label' => __('Bank code', 'woocommerce-abacs'),
                        ),
                    ),
                    'US' => array(
                        'sortcode' => array(
                            'label' => __('Routing number', 'woocommerce-abacs'),
                        ),
                    ),
                    'ZA' => array(
                        'sortcode' => array(
                            'label' => __('Branch code', 'woocommerce-abacs'),
                        ),
                    ),
                )
            );
        }
        return $this->locale;
    }

    /**
     * Custom CSS and JS
     */
    public function payment_scripts()
    {
        // we need JavaScript to process a token only on cart/checkout pages, right?
        if (is_checkout() || is_checkout_pay_page()) {
            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ('no' === $this->enabled) {
                return;
            }
            wp_register_script('woocommerce_abacs', plugins_url('/assets/js/payment.js', ABACS_DIR . '/assets/'), array('jquery'));
            wp_enqueue_script('woocommerce_abacs');
        }
        return;
    }

    public function payment_fields()
    {
        // ok, let's display some description before the payment form
        if ($this->description) {
            // display the description with <p> tags etc.
            echo wpautop(wp_kses_post($this->description));
        }
        wc_get_template('payment_form.php', [], '', ABACS_DIR . '/templates/');
    }
}
