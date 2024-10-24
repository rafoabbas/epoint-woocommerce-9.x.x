<?php

// sending POST-request from script
if (isset($_POST['refund_amount']) && $_POST['refund_amount'] && is_user_logged_in()) {
	$order_id = (int) $_POST['order_id'];
	$payment = new WC_Gateway_Epoint();
	$order_meta = get_post_meta($order_id);


	$amount = $_POST['refund_amount'];
	$orderId = $order_meta['order_order_id'][0];
	$sessionId = $order_meta['order_transaction_id'][0];

	if (!empty($orderId) && !empty($sessionId)) {
		

		// Sending request to our system using CURL-based function
		$result = $payment->reverse($order_id, $orderId, $sessionId, $amount);


		// Adding order note.
		if ($result['status']) {
			$note = __('Success! The payment was refunded.', 'epoint');
		}else{
			$note = __('Something wrong. The payment wasn\'t refunded. Please, try later.', 'epoint');
		}

		$commentdata = apply_filters( 'woocommerce_new_order_note_data', array(
			'comment_post_ID'      => $order_id,
			'comment_content'      => $note,
			'comment_agent'        => 'WooCommerce',
			'comment_type'         => 'order_note',
			'comment_parent'       => 0,
			'comment_approved'     => 1,
		), array( 'order_id' => $order_id, 'is_customer_note' => 0 ) );

		$comment_id = wp_insert_comment( $commentdata );

		if (!$result['status']) {
			exit();
		}
	}
}