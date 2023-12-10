<?php

/**
 * Thankyou page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/thankyou.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.7.0
 */

defined('ABSPATH') || exit;


// /* STRIPE INTEGRATON */

// Get the current directory of the script
$baseDir = __DIR__;

// // Enable error reporting for debugging
// error_reporting(E_ALL);
// ini_set('display_errors', '1');

// Require WooCommerce
if (!class_exists('WooCommerce')) {
	require dirname(__DIR__, 4) . '/plugins/woocommerce/woocommerce.php';
}
// Require Stripe PHP library
require dirname(__DIR__, 4) . '/plugins/STRIPE-INTEGRATION/vendor/autoload.php';


$stripe_integration = WC()->payment_gateways->payment_gateways()['stripe_integration'];
if (!empty($_GET['session_id'])) {
	$session_id = $_GET['session_id'];
	$stripe = new \Stripe\StripeClient($stripe_integration->private_key);

	try {
		$checkout_session = $stripe->checkout->sessions->retrieve($session_id);
	} catch (Exception $e) {
		$api_error = $e->getMessage();
		// echo nl2br("Error (checkout_session): " . $api_error . "\n");
	}

	if (empty($api_error) && $checkout_session) {
		try {
			$paymentIntent = $stripe->paymentIntents->retrieve($checkout_session->payment_intent);
		} catch (\Stripe\Exception\ApiErrorException $e) {
			$api_error = $e->getMessage();
			// echo nl2br("Error (paymentIntent)" . $api_error . "\n");
		}
	}

	if (empty($api_error) && $paymentIntent) {
		if (!empty($paymentIntent) && $paymentIntent->status === "succeeded") {
			if ($order) {
				// echo nl2br("Success, payment status: ".$paymentIntent->status."\n");
				$order->update_status('processing');
			}
		}
	}

}
/* STRIPE INTEGRATON */
?>

<div class="woocommerce-order">

	<?php
	if ($order):
		do_action('woocommerce_before_thankyou', $order->get_id());
		?>

		<?php if ($order->has_status('failed')): ?>

			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed">
				<?php esc_html_e('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce'); ?>
			</p>

			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
				<a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="button pay">
					<?php esc_html_e('Pay', 'woocommerce'); ?>
				</a>
				<?php if (is_user_logged_in()): ?>
					<a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="button pay">
						<?php esc_html_e('My account', 'woocommerce'); ?>
					</a>
				<?php endif; ?>
			</p>

		<?php else: ?>

			<div class="thankyou__sub-title">Our manager will contact you very soon.</div>

			<div class="thankyou__block" id="orderData">

				<div class="order-data">

					<div class="order-data__left">
						<div class="order-data__id">Order №
							<?= $order->id ?>
						</div>
						<div class="order-data__price">
							<?= $order->get_formatted_order_total() ?>
						</div>
					</div>

					<div class="order-data__right">

						<div class="order-data__item">
							<div class="order-data__name">Registration date</div>
							<div class="order-data__value">
								<?= wc_format_datetime($order->get_date_created()) ?>
							</div>
						</div>

						<?
						$deliveryPrice = get_order_shipping_cost($order->id);
						?>
						<div class="order-data__item">
							<div class="order-data__name">Delivery costs</div>
							<div class="order-data__value">
								<?= $deliveryPrice == 0 ? 'Free' : $deliveryPrice ?>
							</div>
						</div>

						<div class="order-data__item">
							<div class="order-data__name">Delivery to</div>
							<div class="order-data__value">
								<?= $order->get_billing_address_1() ?>,
								<?= ($order->get_billing_address_2()) ? $order->get_billing_address_2() . ',' : '' ?>
								<?= $order->get_billing_city() ?>
							</div>
						</div>

						<div class="order-data__item">
							<div class="order-data__name">Contact information</div>
							<div class="order-data__value">
								<?= $order->get_billing_first_name() ?>,
								<?= $order->get_billing_phone() ?>
							</div>
						</div>

						<div class="order-data__item">
							<div class="order-data__name">Email</div>
							<div class="order-data__value">
								<?= $order->get_billing_email() ?>
							</div>
						</div>

						<div class="order-data__item">
							<div class="order-data__name">Pay method</div>
							<div class="order-data__value">
								<?= wp_kses_post($order->get_payment_method_title()) ?>
							</div>
						</div>

						<div class="order-data__item">
							<div class="order-data__name">Items,
								<?= $order->get_item_count() ?> ed
							</div>
							<div class="order-data__value">
								<?= $order->get_formatted_order_total() ?>
							</div>
						</div>

					</div>

				</div>

				<?
				$products = get_order_items($order->id);
				?>
				<div class="order-products">

					<? foreach ($products as $product): ?>
						<div class="order-products__item">
							<div class="order-products__item-image">
								<img src="<?= $product->image ?>" alt="">
							</div>
							<div class="order-products__item-data">
								<div class="order-products__item-left">
									<div class="order-products__item-name">
										<?= $product->name ?>
									</div>
									<? if (!empty($product->sku)): ?>
										<div class="order-products__item-article">art.
											<?= $product->sku ?>
										</div>
									<? endif; ?>
								</div>
								<div class="order-products__item-right">
									<div class="order-products__item-price">
										<?= $product->quantity ?> *
										<?= $product->price ?>
									</div>
								</div>
							</div>
						</div>
					<? endforeach; ?>

				</div>

			</div>

			<div class="button continue-shopping" data-href="/furniture">Continue shopping</div>

		<?php endif; ?>

	<?php else: ?>

		<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">
			<?php echo apply_filters('woocommerce_thankyou_order_received_text', esc_html__('Thank you. Your order has been received.', 'woocommerce'), null); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
		</p>

	<?php endif; ?>

</div>