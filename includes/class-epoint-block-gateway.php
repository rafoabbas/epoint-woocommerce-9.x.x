<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;


class Epoint_WooCommerce_Blocks extends AbstractPaymentMethodType
{

	protected $name = 'epoint_woocommerce_blocks_gateway';
	private $gateways = [];

	public function initialize()
	{
		$this->gateways['epoint'] = 'Epoint_Checkout_For_WooCommerce_Gateway';
	}

	public function is_active()
	{
		$payment_methods = $this->get_payment_method_infos();

		if (count($payment_methods) > 0) {
			return true;
		}

		return false;
	}

	public function get_payment_method_script_handles()
	{
		wp_register_script(
			'epoint_woocommerce_blocks_gateway_script',
			plugin_dir_url(__DIR__).'/assets/js/checkout.js',
			[
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			],
			'1.5',
			true
		);

		if (function_exists('wp_set_script_translations')) {
			wp_set_script_translations('epoint_woocommerce_blocks_gateway_script', 'woocommerce-epoint');
		}


		return ['epoint_woocommerce_blocks_gateway_script'];
	}

	public function get_payment_method_data()
	{
		$data = $this->get_payment_method_infos();

		return $data;
	}

	public function get_payment_method_infos()
	{
		$payment_gateways = WC_Payment_Gateways::instance();
		$available_gateways = $payment_gateways->payment_gateways();


		$data = [];

		foreach ($available_gateways as $gateway) {
			if (in_array($gateway->id, array_keys($this->gateways))) {
				if ($gateway->enabled === 'yes') {
					$data[$gateway->id] = [
						'title' => $gateway->title ? $gateway->title : "!",
						'description' => $gateway->description,
						'logo_url' => plugin_dir_url(__DIR__) . "images/epoint-logo.png"
					];
				}
			}
		}

		return $data;
	}
}
