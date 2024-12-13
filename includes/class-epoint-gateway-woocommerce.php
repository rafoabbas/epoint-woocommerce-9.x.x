<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gateway_Epoint class.
 *
 * @since 2.0.0
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Epoint extends WC_Payment_Gateway
{

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = 'epoint';
        $this->method_title = 'Epoint Gateway';
        $this->has_fields = false;
        $this->hpos = $this->checkHpos();

        $this->init_form_fields();

        $this->init_settings();

        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->currency = $this->settings['currency'];
        $this->icon = WC_EPOINT_PLUGIN_URL . '/images/epoint-logo.png';

        $this->merch_name = get_bloginfo('name');
        $this->email = get_bloginfo('admin_email');
        $this->callback = get_home_url() . '/wc-api/epoint_callback';

        $this->msg['message'] = "";
        $this->msg['class'] = "";

        $this->response_statuses = [
            'success' => ['APPROVED', 'SETTLED', 'SETTLE_PENDING'],
            'error' => ['DECLINED', 'EXPIRED'],
            'created' => ['CREATED']
        ];

        add_action('woocommerce_api_epoint_callback', array($this, 'epoint_callback'));

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
        } else {
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
        }

    }

    public function init_form_fields(): void
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'epoint'),
                'type' => 'checkbox',
                'label' => __('Enable Epoing Payment Module.', 'epoint'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title:', 'epoint'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'epoint'),
                'default' => __('Epoint', 'epoint')
            ),
            'description' => array(
                'title' => __('Description:', 'epoint'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'epoint'),
                'default' => __('Pay securely by Credit or Debit card or internet banking through Epoint Secure Servers.', 'epoint')
            ),
            'currency' => array(
                'title' => __('Select Currency', 'epoint'),
                'type' => 'select',
                'description' => __('Select your bank accounts currency.', 'epoint'),
                'options' => array(
                    'AZN' => 'AZN',
                    'USD' => 'USD',
                    'EUR' => 'EUR')
            ),
            'currency_usd_convert_azn' => array(
                'title' => __('USD convert to AZN', 'epoint'),
                'type' => 'text',
                'class' => 'production-mode',
                'description' => __('If you want to convert USD to AZN, you can set the rate here.'),
                'default' => __('1.70', 'epoint'),
                'placeholder' => __('1.70', 'epoint')
            ),
            'currency_eur_convert_azn' => array(
                'title' => __('EUR convert to AZN', 'epoint'),
                'type' => 'text',
                'class' => 'production-mode',
                'description' => __('If you want to convert EUR to AZN, you can set the rate here.'),
                'default' => __('1.78', 'epoint'),
                'placeholder' => __('1.78', 'epoint')
            ),
            'public_key' => array(
                'title' => __('Public Key', 'epoint'),
                'type' => 'text',
                'class' => 'production-mode',
                'description' => __('This public key use at Epoint.'),
                'default' => __('', 'epoint'),
                'placeholder' => __('i000000000', 'epoint')
            ),
            'private_key' => array(
                'title' => __('Private Key', 'epoint'),
                'type' => 'text',
                'class' => 'production-mode',
                'description' => __('This private key use at Epoint.'),
                'default' => __('', 'epoint'),
                'placeholder' => __('***********************', 'epoint')
            ),

        );
    }

    public function get_admin_settings()
    {
        return [
            'currency' => $this->settings['currency'],
            'public_key' => $this->settings['public_key'],
            'private_key' => $this->settings['private_key'],
            'callback' => $this->callback,
            'currency_usd_convert_azn' => $this->settings['currency_usd_convert_azn'],
            'currency_eur_convert_azn' => $this->settings['currency_eur_convert_azn'],
        ];
    }

    // get all pages
    public function get_callback_pages($title = false, $indent = true)
    {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }

    /**
     * Process the payment and return the result
     **/
    public function process_payment($order_id)
    {
        session_set_cookie_params(0, '/; samesite=None', null, true, false);

        $order = wc_get_order($order_id);

        $request = $this->create_order($order_id);

        if ($request['status'] == 0) {
            // Mark as on-hold (we're awaiting the cheque)
            $order->update_status('on-hold', _x('Error', 'Check payment method', 'epoint'));

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'transaction_id' => $request['transaction_id'],
                'result' => 'success',
                'redirect' => $request['payment_url'],
            );
        }

        // Mark as pending-payment (we're awaiting the cheque)
        $order->update_status('pending-payment', _x('Awaiting check payment', 'Check payment method', 'epoint'));

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);

        // Remove cart
        WC()->cart->empty_cart();

        define('WP_SAMESITE_COOKIE', 'None');

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $request['payment_url'],
        );
    }

    public function create_order($order_id)
    {

        $admin_settings = $this->get_admin_settings();

        $order = new WC_Order($order_id);

        $epoint = $this->registerEpoint($admin_settings);

        $result = [];

        $result['status'] = 0;

        $total = (float) $order->total;

        if ($admin_settings['currency'] === 'USD') {
            $total = $total * (float) $admin_settings['currency_usd_convert_azn'];
        } else if ($admin_settings['currency'] === 'EUR') {
            $total = $total * (float) $admin_settings['currency_eur_convert_azn'];
        }

        $response = $epoint->request('1/request', $epoint->payload([
            'public_key' => $admin_settings['public_key'],
            'amount' => (float) $total,
            'currency' => $admin_settings['currency'],
            'language' => 'az',
            'order_id' => $order_id,
            'description' => 'Order #' . $order_id,
            "success_redirect_url" => $admin_settings['callback'] . "?order_id=" . $order_id,
            "error_redirect_url" => $admin_settings['callback'] . "?order_id=" . $order_id
        ]));

        $json = json_decode($response, true);

        if ($json['status'] === 'success') {
            $order->add_order_note(__('Order created: ' . $order_id, 'epoint'));

            if ($this->hpos) {
                $order->add_meta_data('payment_order_id', $json['transaction']);
                $order->save_meta_data();
            } else {
                update_post_meta($order_id, 'order_order_id', $json['transaction']);
            }

            $order->add_order_note(__('Abhipay Order ID: ' . $json['transaction'], 'epoint'));

            $result['status'] = 1;
            $result['transaction_id'] = $json['transaction'];
            $result['payment_url'] = $json['redirect_url'];

        } else {
            $order->add_order_note(__('Error on request', 'epoint'));
        }

        return $result;
    }

    public function reverse($order_id, $orderId, $sessionId, $amount)
    {
        $admin_settings = $this->get_admin_settings();

        $epoint = $this->registerEpoint($admin_settings);

        $order = new WC_Order($order_id);


        $result = array( 'status' => 0);

        $total = (float) $order->total;

        if ($admin_settings['currency'] === 'USD') {
            $total = $total * (float) $admin_settings['currency_usd_convert_azn'];
        } else if ($admin_settings['currency'] === 'EUR') {
            $total = $total * (float) $admin_settings['currency_eur_convert_azn'];
        }


        $response = $epoint->request('1/reverse', $epoint->payload([
            'public_key' => $admin_settings['public_key'],
            'amount' => (float) $total,
            'currency' => $admin_settings['currency'],
            'language' => 'az',
            'transaction' => $orderId,
        ]));

        $json = json_decode($response, true);

        if (isset($json['status'])) {

            $status = $json['status'];

            if ($status === 'success') {
                $result['status'] = 1;

                $order->add_order_note(__('Refund success' . ': ' . $order_id, 'epoint'));
            } else {
                $order->add_order_note(__('Error : ', 'epoint'));
                $result['status'] = 0;

            }
        } else {
            $result['status'] = 0;
            $order->add_order_note(__('Error on request', 'epoint'));
        }
        return $result;
    }

    public function get_order_information($epoint_order_id, $order_id = null)
    {
        //Define woocommerce
        global $woocommerce;

        //Get admin settings
        $admin_settings = $this->get_admin_settings();

        $epoint = $this->registerEpoint($admin_settings);

        if (is_null($order_id) || empty($order_id)) {
            if ($this->hpos) {
                //Get order data by post meta
                $order_data = $this->get_order_by_meta($epoint_order_id);
            } else {
                $order_data = $this->get_post_by_meta($epoint_order_id);
            }

            //Define order id
            $order_id = $order_data->get_id();
        }


        //Create object from Order class
        $order = new WC_Order($order_id);

        $response = $epoint->request('1/get-status', $epoint->payload([
            'public_key' => $admin_settings['public_key'],
            'transaction' => $epoint_order_id,
        ]));

        $json = json_decode($response, true);

        if (isset($json['status'])) {

            $status = $json['status'];

            if ($status === 'success') {
                $this->msg['message'] = __('Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.', 'epoint');
                $this->msg['class'] = 'woocommerce_message';

                $order->update_status('processing');
                $order->add_order_note(__('Abhipay payment successful', 'epoint'));
                $woocommerce->cart->empty_cart();
            } else if ($status === 'new') {
                $order->update_status('pending-payment');
                $order->add_order_note(__('Abhipay payment pending.Status: ' . $status, 'epoint'));
            } else {
                $order->update_status('failed');
                $order->add_order_note(__('Abhipay payment failed.Status: ' . $status, 'epoint'));
            }

        }else {
            $this->msg['message'] = __('Payment not accepted.', 'epoint');
            $this->msg['class'] = 'woocommerce_error';

            $order->update_status('failed');
            $order->add_order_note(__('Code : ' , 'epoint'));
            $order->add_order_note(__('Error Message : ', 'epoint'));
        }
    }

    public function registerEpoint($admin_settings)
    {
        require_once __DIR__ . '/class-epoint.php';

        $epoint = new Epoint();

        $epoint
            ->setPublicKey($admin_settings['public_key'])
            ->setPrivateKey($admin_settings['private_key']);

        return $epoint;
    }

    // Add custom endpoint for callback
    public function epoint_callback()
    {
        if (isset($_GET['order_id']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $order_id = (int) $_GET['order_id'];

            $order = new WC_Order($order_id);

            $epoint_order_id = $order->get_meta('payment_order_id', true);

            $this->get_order_information($epoint_order_id, $order_id);

            $return_url = $order->get_checkout_order_received_url();

            header("Location: " . $return_url);

            exit();
        }
    }

    public function get_order_by_meta($order_id)
    {
        $args = [
            'status' => 'wc-pending',
            'meta_key' => 'payment_order_id',
            'meta_value' => $order_id,
            'meta_compare' => '='
        ];

        $orders = wc_get_orders($args);

        if (empty($orders)) {
            exit();
        }

        return $orders[0];
    }

    public function get_post_by_meta($order_id)
    {
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => 'wc-pending-payment',
            'meta_query' => array(
                array(
                    'key' => 'order_order_id',
                    'value' => $order_id,
                    'compare' => '=',
                )
            ),
        );

        $query = new WP_Query($args);

        $posts = $query->posts;

        if (empty($posts)) {
            exit();
        }

        return $posts[0];
    }

    public function checkHpos()
    {
        $woocommerce_settings = array();
        $all_options = wp_load_alloptions();
        foreach ($all_options as $name => $value) {
            if (strpos($name, 'woocommerce_') === 0) {
                $woocommerce_settings[$name] = $value;
            }
        }

        if (isset($woocommerce_settings['woocommerce_custom_orders_table_enabled'])) {
            // Custom API key indicates potential HPOS mode
            return true;
        }

        return false;
    }
}

