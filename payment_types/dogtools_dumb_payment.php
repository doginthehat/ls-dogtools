<?php

class DogTools_Dumb_Payment extends Shop_PaymentType {
	
	/**
	 * Returns information about the payment type
	 * Must return array: array(
	 *		'name'=>'Authorize.net', 
	 *		'custom_payment_form'=>false,
	 *		'offline'=>false,
	 *		'pay_offline_message'=>null
	 * ).
	 * Use custom_paymen_form key to specify a name of a partial to use for building a back-end
	 * payment form. Usually it is needed for forms which ACTION refer outside web services, 
	 * like PayPal Standard. Otherwise override build_payment_form method to build back-end payment
	 * forms.
	 * If the payment type provides a front-end partial (containing the payment form), 
	 * it should be called in following way: payment:name, in lower case, e.g. payment:authorize.net
	 *
	 * Set index 'offline' to true to specify that the payments of this type cannot be processed online 
	 * and thus they have no payment form. You may specify a message to display on the payment page
	 * for offline payment type, using 'pay_offline_message' index.
	 *
	 * @return array
	 */
	public function get_info()
	{
		return array(
			'name'=>'Dumb Gateway',
			'description'=>'Gateway is a big word here, your data ain\'t going nowhere. Dumb gateway does as it pleases.'
		);
	}
	
	/**
	 * Builds the payment type administration user interface 
	 * For drop-down and radio fields you should also add methods returning 
	 * options. For example, of you want to have Sizes drop-down:
	 * public function get_sizes_options();
	 * This method should return array with keys corresponding your option identifiers
	 * and values corresponding its titles.
	 * 
	 * @param $host_obj ActiveRecord object to add fields to
	 * @param string $context Form context. In preview mode its value is 'preview'
	 */
	public function build_config_ui($host_obj, $context = null)
	{

		$host_obj->add_field('mood', 'Gateway is feeling')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select the mood of the gateway.', 'above');

		$host_obj->add_field('order_status', 'Order Status')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');

		
	}
	
	public function get_mood_options($current_key_value = -1)
	{
		$options = array(
			'HAPPY'=>'Happy',
			'SAD'=>'Sad',
			'NOTSURE'=>'Undecided',
		);
		
		if ($current_key_value == -1)
			return $options;

		return isset($options[$current_key_value]) ? $options[$current_key_value] : 'HAPPY';
	}
	
	public function get_order_status_options($current_key_value = -1)
	{
		if ($current_key_value == -1)
			return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

		return Shop_OrderStatus::create()->find($current_key_value)->name;
	}

	/**
	 * Validates configuration data before it is saved to database
	 * Use host object field_error method to report about errors in data:
	 * $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');
	 * @param $host_obj ActiveRecord object containing configuration fields values
	 */
	public function validate_config_on_save($host_obj)
	{
	}

	/**
	 * Validates configuration data after it is loaded from database
	 * Use host object to access fields previously added with build_config_ui method.
	 * You can alter field values if you need
	 * @param $host_obj ActiveRecord object containing configuration fields values
	 */
	public function validate_config_on_load($host_obj)
	{
	}

	/**
	 * Initializes configuration data when the payment method is first created
	 * Use host object to access and set fields previously added with build_config_ui method.
	 * @param $host_obj ActiveRecord object containing configuration fields values
	 */
	public function init_config_data($host_obj)
	{
		$host_obj->order_status = Shop_OrderStatus::get_status_paid()->id;
		$host_obj->mood = 'NOTSURE';
	}


	/**
	 * Builds the back-end payment form 
	 * For drop-down and radio fields you should also add methods returning 
	 * options. For example, of you want to have Sizes drop-down:
	 * public function get_sizes_options();
	 * This method should return array with keys corresponding your option identifiers
	 * and values corresponding its titles.
	 * 
	 * @param $host_obj ActiveRecord object to add fields to
	 */
	public function build_payment_form($host_obj)
	{
		$host_obj->add_field('ACCT', 'Credit Card Number', 'left')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a credit card number');
		$host_obj->add_field('CVV2', 'Card Code', 'right')->renderAs(frm_text)->validation()->fn('trim')->numeric();

		$host_obj->add_field('EXPDATE_MONTH', 'Expiration Month', 'left')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration month')->numeric();
		$host_obj->add_field('EXPDATE_YEAR', 'Expiration Year', 'right')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration year')->numeric();
	}

	/**
	 * This function is called before an order status deletion.
	 * Use this method to check whether the payment method
	 * references an order status. If so, throw Phpr_ApplicationException 
	 * with explanation why the status cannot be deleted.
	 * @param $host_obj ActiveRecord object containing configuration fields values
	 * @param Shop_OrderStatus $status Specifies a status to be deleted
	 */
	public function status_deletion_check($host_obj, $status)
	{
		if ($host_obj->order_status == $status->id)
			throw new Phpr_ApplicationException('Status cannot be deleted because it is used in St George IPG Gateway Payments method.');
	}


	/**
	 * Processes payment using passed data
	 * @param array $data Posted payment form data
	 * @param $host_obj ActiveRecord object containing configuration fields values
	 * @param $order Order object
	 */
	public function process_payment_form($data, $host_obj, $order, $back_end = false)
	{
	
		/*
		 * Validate input data
		 */
		$validation = new Phpr_Validation();
		$validation->add('EXPDATE_MONTH', 'Expiration month')->fn('trim')->required('Please specify a card expiration month.')->regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');
		$validation->add('EXPDATE_YEAR', 'Expiration year')->fn('trim')->required('Please specify a card expiration year.')->regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');

		$validation->add('ACCT', 'Credit card number')->fn('trim')->required('Please specify a credit card number.')->regexp('/^[0-9 \-]*$/', 'Please specify a valid credit card number. Credit card number can contain only digits.');
		$validation->add('CVV2', 'Card code')->fn('trim')->regexp('/^[0-9]*$/', 'Card code can contain only digits.');
		
		try
		{
			if (!$validation->validate($data))
				$validation->throwException();
		} catch (Exception $ex)
		{
			$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), array(), null);
			throw $ex;
		}

		$mood = $host_obj->mood;
		
		if ($mood == 'NOTSURE') {
			srand(time());
			$mood = rand(0,1) == 1 ? 'HAPPY' : 'SAD';
		}
			
		$fields = array(
			'DUMB_GATEWAY_IS' => $mood
		);

		$response_fields = array(
			'DUMB_GATEWAY_WAS' => $mood,
			'RESPONSETEXT' 	=> '',
		);
		
		$response = '';

		if ($mood == 'HAPPY') {

			$this->log_payment_attempt($order, 'Successful payment', 1, $this->prepare_fields_log($fields), $this->prepare_fields_log($response_fields), $this->prepare_response_log($response));

			Shop_OrderStatusLog::create_record($host_obj->order_status, $order);

			$order->set_payment_processed();
			
		} else {
		
			$response_fields['RESPONSETEXT']=$this->gimme_error();
		
			$this->log_payment_attempt($order, $response_fields['RESPONSETEXT'], 0, $fields, $this->prepare_fields_log($response_fields), $this->prepare_response_log($response));
			
			throw new Phpr_ApplicationException($response_fields['RESPONSETEXT']);
		}

		
		
	}

	private function prepare_fields_log($fields)
	{
		if (isset($fields['CVC2']))
			unset($fields['CVC2']);

		if (isset($fields['CARDDATA']))
			$fields['CARDDATA'] = '...'.substr($fields['CARDDATA'], -4);
		
		return $fields;
	}
	
	private function prepare_response_log($response)
	{
		return $response;
	}
	

	function gimme_error()
	{
		srand(time());
		
		$errors = array(
			"The expiry date you have selected is not valid.", 
			"This card seems to have expired.", 
			"There is a problem processing your card. Please check with your bank.",
			"The CVN entered is incorrect.",
			"The Card Number you have entered is not valid.",
			"The card is suspected of being fraudulent. Please contact your bank.",
			"The transaction could not be completed by our payment processing facility at this time. Please try again.",
			"The card has insufficient funds or will exceed its limit for this transaction. Please contact your bank.",
			"Payment processing facility is unable to contact your bank at this time. Please allow 30 seconds of delay and try again.",
			"Unknown Error Code. Payment process facility was unable to process your order at this time. "
		);
		
		return $errors[rand(0,count($errors)-1)];
	}




}



