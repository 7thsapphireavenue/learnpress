<?php

/**
 * Class LP_Checkout
 *
 * @author  ThimPress
 * @package LearnPress/Classes
 * @version 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LP_Checkout {

	/**
	 * @var LP_Checkout object instance
	 * @access protected
	 */
	static protected $_instance = null;

	/**
	 * Payment method
	 *
	 * @var string
	 */
	public $payment_method = null;

	/**
	 * @var array|mixed|null|void
	 */
	protected $checkout_fields = array();

	/**
	 * @var null
	 */
	public $user_login = null;

	/**
	 * @var null
	 */
	public $user_pass = null;

	/**
	 * @var null
	 */
	public $order_comment = null;

	/**
	 * Handle the errors when checking out.
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'learn_press_checkout_validate_field', array( $this, 'validate_fields' ), 10, 3 );
	}

	public function get_checkout_fields() {
		if ( ! is_user_logged_in() ) {
			$this->checkout_fields['user_login']    = __( 'Username', 'learnpress' );
			$this->checkout_fields['user_password'] = __( 'Password', 'learnpress' );
		}
		$this->checkout_fields = apply_filters( 'learn_press_checkout_fields', $this->checkout_fields );

		return $this->checkout_fields;
	}

	/**
	 * Check if an order is pending or failed.
	 *
	 * @param $order_id
	 *
	 * @return LP_Order|bool
	 */
	protected function _is_resume_order( $order_id ) {
		if ( $order_id > 0 && ( $order = learn_press_get_order( $order_id ) ) && $order->has_status( array(
				'pending',
				'failed'
			) )
		) {
			return $order;
		}

		return false;
	}

	/**
	 * Creates temp new order if needed
	 *
	 * @return mixed|WP_Error
	 * @throws Exception
	 */
	public function create_order() {
		global $wpdb;
		// Third-party can be controls to create a order
		$order_id = apply_filters( 'learn-press/checkout/create-order', null, $this );

		// @deprecated
		$order_id = apply_filters( 'learn_press_create_order', null, $this );

		if ( $order_id ) {
			return $order_id;
		}
		$cart = LP()->get_cart();
		try {
			// Start transaction if available
			$wpdb->query( 'START TRANSACTION' );

			$order_data = array(
				'status'      => learn_press_default_order_status(),
				'user_id'     => get_current_user_id(),
				'user_note'   => $this->order_comment,
				'created_via' => 'checkout'
			);

			// Insert or update the post data
			$order_id = absint( LP()->session->get( 'order_awaiting_payment' ) );

			// Resume the unpaid order if its pending
			if ( $order = $this->_is_resume_order( $order_id ) ) {

//				$order_data['ID'] = $order_id;
//				$order            = learn_press_update_order( $order_data );

				if ( is_wp_error( $order ) ) {
					throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'learnpress' ), 401 ) );
				}

				$order->remove_order_items();
				do_action( 'learn-press/checkout/resume-order', $order_id );

			} else {
				$order = new LP_Order();
				if ( is_wp_error( $order ) ) {
					throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'learnpress' ), 400 ) );
				}

				$order_id = $order->get_id();
				do_action( 'learn-press/checkout/new-order', $order_id );
			}

			$order->set_customer_note( $this->order_comment );
			$order->set_status( learn_press_default_order_status( 'lp-' ) );
			$order->set_total( $cart->total );
			$order->set_subtotal( $cart->subtotal );
			$order->set_created_via( 'checkout' );
			$order->set_user_id( apply_filters( 'learn-press/checkout/default-user', get_current_user_id() ) );

			$order_id = $order->save();

			// Store the line items to the new/resumed order
			foreach ( $cart->get_items() as $item ) {
				if ( empty( $item['order_item_name'] ) && ! empty( $item['item_id'] ) && ( $course = LP_Course::get_course( $item['item_id'] ) ) ) {
					$item['order_item_name'] = $course->get_title();
				} else {
					throw new Exception( sprintf( __( 'Item does not exists!', 'learnpress' ), 402 ) );
				}

				$item_id = $order->add_item( $item );

				if ( ! $item_id ) {
					throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'learnpress' ), 402 ) );
				}

				// Allow plugins to add order item meta
				do_action( 'learn_press_add_order_item_meta', $item_id, $item );

				// @since 3.x.x
				do_action( 'learn-press/checkout/add-order-item-meta', $item_id, $item );
			}

			$order->set_payment_method( $this->payment_method );

			// Update user meta
			if ( ! empty( $this->user_id ) ) {
				do_action( 'learn-press/checkout/update-user-meta', $this->user_id );
			}

			// Third-party add meta data
			do_action( 'learn-press/checkout/update-order-meta', $order_id );

			if ( ! $order_id || is_wp_error( $order_id ) ) {
				learn_press_add_message( __( 'Checkout. Create order failed!', 'learnpress' ) );
			}
			$wpdb->query( 'COMMIT' );

		}
		catch ( Exception $e ) {
			// There was an error adding order data!
			$wpdb->query( 'ROLLBACK' );
			learn_press_add_message( $e->getMessage() );

			return false;
		}


		return $order_id;
	}

	/**
	 * Guest checkout is enable?
	 *
	 * @since 3.x.x
	 *
	 * @return mixed
	 */
	public function is_enable_guest_checkout() {
		return true;// apply_filters( 'learn-press/enabled-guest-checkout', 'yes' === get_option( 'learn_press_enable_guest_checkout' ) );
	}

	/**
	 * Validate fields
	 *
	 * @param bool
	 * @param $field
	 * @param LP_Checkout instance
	 *
	 * @return bool
	 */
	public function validate_fields( $validate, $field, $checkout ) {
		if ( $field['name'] == 'user_login' && empty( $this->user_login ) ) {
			$validate = false;
			learn_press_add_message( __( 'Please enter user login', 'learnpress' ) );
		}
		if ( $field['name'] == 'user_password' && empty( $this->user_pass ) ) {
			$validate = false;
			learn_press_add_message( __( 'Please enter user password', 'learnpress' ) );
		}

		return $validate;
	}

	/**
	 * Process checkout from request
	 */
	public function process_checkout_handler() {
		if ( strtolower( $_SERVER['REQUEST_METHOD'] ) != 'post' ) {
			return;
		}
		/**
		 * Set default fields from request
		 */
		$this->payment_method = ! empty( $_REQUEST['payment_method'] ) ? $_REQUEST['payment_method'] : '';
		$this->user_login     = ! empty( $_POST['user_login'] ) ? $_POST['user_login'] : '';
		$this->user_pass      = ! empty( $_POST['user_password'] ) ? $_POST['user_password'] : '';
		$this->order_comment  = isset( $_REQUEST['order_comments'] ) ? $_REQUEST['order_comments'] : '';

		// do checkout
		return $this->process_checkout();
	}

	/**
	 * Validate fields.
	 *
	 * @return bool
	 */
	public function validate_checkout_fields() {
		$this->errors = array();
		if ( $fields = $this->get_checkout_fields() ) {
			foreach ( $fields as $name => $field ) {
				$error = apply_filters( 'learn-press/validate-checkout-field', $field );
				if ( is_wp_error( $error ) ) {
					$this->errors[ $name ] = $error;
				}
			}
		}

		return ! sizeof( $this->errors );
	}

	public function validate_payment() {
		$cart     = LP()->get_cart();
		$validate = true;
		if ( $cart->needs_payment() ) {

			if ( ! $this->payment_method instanceof LP_Gateway_Abstract ) {
				// Payment Method
				$available_gateways = LP_Gateways::instance()->get_available_payment_gateways();
				if ( ! isset( $available_gateways[ $this->payment_method ] ) ) {
					$this->payment_method = '';
					learn_press_add_message( __( 'Invalid payment method.', 'learnpress' ) );
				} else {
					$this->payment_method = $available_gateways[ $this->payment_method ];
				}
			}
			if ( $this->payment_method ) {
				$validate = $this->payment_method->validate_fields();
			}
		}

		return $validate;
	}

	/**
	 * Process checkout.
	 *
	 * @throws Exception
	 */
	public function process_checkout() {
		$has_error = false;
		try {
			// Prevent timeout
			@set_time_limit( 0 );

			/**
			 * @deprecated
			 */
			do_action( 'learn_press_before_checkout_process' );

			/**
			 * @since 3.x.x
			 */
			do_action( 'learn-press/before-checkout' );

			$cart   = LP()->get_cart();
			$result = false;

			// There is no course in cart
			if ( $cart->is_empty() ) {
				throw new Exception( __( 'Your cart currently is empty.', 'learnpress' ) );
			}

//			if ( ! is_user_logged_in() && isset( $this->checkout_fields['user_login'] ) && isset( $this->checkout_fields['user_password'] ) ) {
//				$creds                  = array();
//				$creds['user_login']    = $this->user_login;
//				$creds['user_password'] = $this->user_pass;
//				$creds['remember']      = true;
//				$user                   = wp_signon( $creds, is_ssl() );
//				if ( is_wp_error( $user ) ) {
//					throw new Exception( $user->get_error_message() );
//					$success = 15;
//				}
//			}

			// Validate courses
			foreach ( $cart->get_items() as $item ) {
				$course = learn_press_get_course( $item['item_id'] );
				if ( ! $course || ! $course->is_purchasable() ) {
					throw new Exception( sprintf( __( 'Item "%s" is not purchasable.', 'learnpress' ), $course->get_title() ) );
				}
			}

			// Validate extra fields
			if ( ! $this->validate_checkout_fields() ) {
				foreach ( $this->errors as $error ) {
					learn_press_add_message( $error );
				}
			} else {

				$this->validate_payment();

				// Create order
				$order_id = $this->create_order();

				if ( is_wp_error( $order_id ) ) {
					throw new Exception( $order_id->get_error_message() );
				}

				// allow Third-party hook
				do_action( 'learn-press/checkout-order-processed', $order_id, $this );

				if ( $this->payment_method ) {
					// Store the order is waiting for payment and each payment method should clear it
					LP()->session->order_awaiting_payment = $order_id;
					// Process Payment
					$result = $this->payment_method->process_payment( $order_id );
					if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
						$result = apply_filters( 'learn-press/payment-successful-result', $result, $order_id );

						if ( learn_press_is_ajax() ) {
							learn_press_send_json( $result );
						} else {
							wp_redirect( $result['redirect'] );
							exit;
						}
					}

				} else {
					// ensure that no order is waiting for payment
					$order = new LP_Order( $order_id );
					if ( $order && $order->payment_complete() ) {

						$result = apply_filters( 'learn-press/checkout-no-payment-result',
							array(
								'result'   => 'success',
								'redirect' => $order->get_checkout_order_received_url()
							),
							$order->get_id()
						);

						if ( learn_press_is_ajax() ) {
							learn_press_send_json( $result );
						} else {
							wp_redirect( $result['redirect'] );
							exit;
						}
					}
				}
			}
		}
		catch ( Exception $e ) {
			$has_error = $e->getMessage();
			learn_press_add_message( $has_error, 'error' );
		}

		if ( learn_press_is_ajax() ) {
			$is_error = ! ! learn_press_message_count( 'error' );
			// Get all messages
			$error_messages = '';
			if ( $is_error ) {
				ob_start();
				learn_press_print_messages();
				$error_messages = ob_get_clean();
			}

			$result = apply_filters( 'learn-press/checkout-error',
				array(
					'result'   => ! $is_error ? 'success' : 'fail',
					'messages' => $error_messages
				)
			);

			learn_press_send_json( $result );
		}
	}

	/**
	 * Get unique instance for this object
	 *
	 * @return LP_Checkout
	 */
	public static function instance() {
		if ( empty( self::$_instance ) ) {
			self::$_instance = new LP_Checkout();
		}

		return self::$_instance;
	}
}

