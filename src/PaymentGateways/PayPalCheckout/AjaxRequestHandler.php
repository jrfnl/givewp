<?php

namespace Give\PaymentGateways\PayPalCheckout;

/**
 * Class AjaxRequestHandler
 * @package Give\PaymentGateways\PayPalCheckout
 *
 * @sicne 2.8.0
 */
class AjaxRequestHandler {
	/**
	 * Setup hooks
	 *
	 * @since 2.8.0
	 */
	public function boot() {
		add_action( 'wp_ajax_give_paypal_checkout_user_onboarded', [ $this, 'onBoardedUserAjaxRequestHandler' ] );
		add_action( 'wp_ajax_give_paypal_checkout_get_partner_url', [ $this, 'onGetPartnerUrlAjaxRequestHandler' ] );
	}

	/**
	 *  give_paypal_checkout_user_onboarded ajax action handler
	 *
	 * @since 2.8.0
	 */
	public function onBoardedUserAjaxRequestHandler() {

	}

	/**
	 * give_paypal_checkout_get_partner_url action handler
	 *
	 * @since 2.8.0
	 */
	public function onGetPartnerUrlAjaxRequestHandler() {
		$restApiUrl = sprintf(
			'https://connect.givewp.com/paypal?mode=%1$s',
			give_is_test_mode() ? 'sandbox' : 'live'
		);

		$response = wp_remote_get( $restApiUrl );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		wp_send_json_success( $data );
	}
}
