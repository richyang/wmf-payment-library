<?php

/**
 * DonationData
 * This class is responsible for pulling all the data used by DonationInterface 
 * from various sources. Once pulled, DonationData will then normalize and 
 * sanitize the data for use by the various gateway adapters which connect to 
 * the payment gateways, and through those gateway adapters, the forms that 
 * provide the user interface.
 * 
 * DonationData was not written to be instantiated by anything other than a 
 * gateway adapter (or class descended from GatewayAdapter). 
 * 
 * @author khorn
 */

require_once 'DataValidator.php';
 
class DonationData {

	protected $normalized = array( );
	public $boss;
	protected $validationErrors = null;

	/**
	 * DonationData constructor
	 * @param string $owning_class The name of the class that instantiated this 
	 * instance of DonationData. This is used to grab gateway-specific functions 
	 * and values, such as the logging function and gateway-specific global 
	 * variables. 
	 * @param boolean $test Indicates if DonationData has been instantiated in 
	 * testing mode. Default is false.
	 * @param mixed $data An optional array of donation data that will, if 
	 * present, circumvent the usual process of gathering the data from various 
	 * places in $wgRequest, or 'false' to gather the data the usual way. 
	 * Default is false. 
	 */
	function __construct( $owning_class, $test = false, $data = false ) {
		$this->boss = $owning_class;
		$this->gatewayID = $this->getGatewayIdentifier();
		$this->populateData( $test, $data );
	}

	function getRequestVal($key)
	{
	}

	function getText($key)
	{
		//TODO
	}

	function getHeader($key)
	{
	}

	/**
	 * populateData, called on construct, pulls donation data from various 
	 * sources. Once the data has been pulled, it will handle any session data 
	 * if present, normalize the data regardless of the source, and handle the 
	 * caching variables.  
	 * @global Webrequest $wgRequest 
	 * @param boolean $test Indicates if DonationData has been instantiated in 
	 * testing mode. Default is false.
	 * @param mixed $external_data An optional array of donation data that will, 
	 * if present, circumvent the usual process of gathering the data from 
	 * various places in $wgRequest, or 'false' to gather the data the usual way. 
	 * Default is false. 
	 */
	protected function populateData( $test = false, $external_data = false ) {
		$this->normalized = array( );
		if ( is_array( $external_data ) ){
			$this->normalized = $external_data;
		} else {
			$this->normalized = array(
				'amount' => $this->getText( 'amount', null ),
				'amountGiven' => $this->getText( 'amountGiven', null ),
				'amountOther' => $this->getText( 'amountOther', null ),
				'email' => $this->getText( 'emailAdd' ),
				'fname' => $this->getText( 'fname' ),
				'mname' => $this->getText( 'mname' ),
				'lname' => $this->getText( 'lname' ),
				'street' => $this->getText( 'street' ),
				'street_supplemental' => $this->getText( 'street_supplemental' ),
				'city' => $this->getText( 'city' ),
				'state' => $this->getText( 'state' ),
				'zip' => $this->getText( 'zip' ),
				'country' => $this->getText( 'country' ),
				'fname2' => $this->getText( 'fname' ),
				'lname2' => $this->getText( 'lname' ),
				'street2' => $this->getText( 'street' ),
				'city2' => $this->getText( 'city' ),
				'state2' => $this->getText( 'state' ),
				'zip2' => $this->getText( 'zip' ),
				/**
				 * For legacy reasons, we might get a 0-length string passed into the form for country2.  If this happens, we need to set country2
				 * to be 'country' for downstream processing (until we fully support passing in two separate addresses).  I thought about completely
				 * disabling country2 support in the forms, etc but realized there's a chance it'll be resurrected shortly.  Hence this silly hack.
				 */
				'country2' => ( strlen( $this->getText( 'country2' ) ) ) ? $this->getText( 'country2' ) : $this->getText( 'country' ),
				'size' => $this->getText( 'size' ),
				'premium_language' => $this->getText( 'premium_language', null ),
				'card_num' => str_replace( ' ', '', $this->getText( 'card_num' ) ),
				'card_type' => $this->getText( 'card_type' ),
				'expiration' => $this->getText( 'mos' ) . substr( $this->getText( 'year' ), 2, 2 ),
				'cvv' => $this->getText( 'cvv' ),
				//Leave both of the currencies here, in case something external didn't get the memo.
				'currency' => $this->getRequestVal( 'currency' ),
				'currency_code' => $this->getRequestVal( 'currency_code' ),
				'payment_method' => $this->getText( 'payment_method', 'cc' ),
				'payment_submethod' => $this->getText( 'payment_submethod', null ), // Used by GlobalCollect for payment types
				'issuer_id' => $this->getText( 'issuer_id' ),
				'order_id' => $this->getText( 'order_id', null ), //as far as I know, this won't actually ever pull anything back.
				'i_order_id' => $this->getText( 'i_order_id', null ), //internal id for each contribution attempt
				'numAttempt' => $this->getRequestVal( 'numAttempt', '0' ),
				'referrer' => ( $this->getRequestVal( 'referrer' ) ) ? $this->getRequestVal( 'referrer' ) : $this->getHeader( 'referer' ),
				'utm_source' => $this->getText( 'utm_source' ),
				'utm_source_id' => $this->getRequestVal( 'utm_source_id', null ),
				'utm_medium' => $this->getText( 'utm_medium' ),
				'utm_campaign' => $this->getText( 'utm_campaign' ),
				'utm_key' => $this->getText( 'utm_key' ),
				// Pull both of these here. We can logic out which one to use in the normalize bits. 
				'language' => $this->getText( 'language', null ),
				'uselang' => $this->getText( 'uselang', null ),
				'comment-option' => $this->getText( 'comment-option' ),
				'comment' => $this->getText( 'comment' ),
				'email-opt' => $this->getText( 'email-opt' ),
				// test_string has been disabled - may no longer be needed.
				//'test_string' => $this->getText( 'process' ), // for showing payflow string during testing
				'_cache_' => $this->getText( '_cache_', null ),
				'token' => $this->getText( 'token', null ),
				'contribution_tracking_id' => $this->getText( 'contribution_tracking_id' ),
				'data_hash' => $this->getText( 'data_hash' ),
				'action' => $this->getText( 'action' ),
				'gateway' => $this->getText( 'gateway' ), //likely to be reset shortly by setGateway();
				'owa_session' => $this->getText( 'owa_session', null ),
				'owa_ref' => $this->getText( 'owa_ref', null ),
				'descriptor' => $this->getText( 'descriptor', null ),

				'account_name' => $this->getText( 'account_name', null ),
				'account_number' => $this->getText( 'account_number', null ),
				'authorization_id' => $this->getText( 'authorization_id', null ),
				'bank_check_digit' => $this->getText( 'bank_check_digit', null ),
				'bank_name' => $this->getText( 'bank_name', null ),
				'bank_code' => $this->getText( 'bank_code', null ),
				'branch_code' => $this->getText( 'branch_code', null ),
				'country_code_bank' => $this->getText( 'country_code_bank', null ),
				'date_collect' => $this->getText( 'date_collect', null ),
				'direct_debit_text' => $this->getText( 'direct_debit_text', null ),
				'iban' => $this->getText( 'iban', null ),
				'transaction_type' => $this->getText( 'transaction_type', null ),
				'form_name' => $this->getText( 'form_name', null ),
				'ffname' => $this->getText( 'ffname', null ),
				'recurring' => $this->getRequestVal( 'recurring', null ), //boolean type
				'user_ip' => null, //placeholder. We'll make these in a minute.
				'server_ip' => null,
			);
			if ( !$this->wasPosted() ) {
				$this->setVal( 'posted', false );
			}
		}
		
		//if we have saved any donation data to the session, pull them in as well.
		$this->integrateDataFromSession();

		$this->doCacheStuff();

		$this->normalize();

	}
	
	/**
	 * populateData helper function 
	 * If donor session data has been set, pull the fields in the session that 
	 * are populated, and merge that with the data set we already have. 
	 */
	protected function integrateDataFromSession(){
		if ( self::sessionExists() && array_key_exists( 'Donor', $_SESSION ) ) {
			//if the thing coming in from the session isn't already something, 
			//replace it. 
			//if it is: assume that the session data was meant to be replaced 
			//with better data.  
			//...unless it's referrer. 
			foreach ( $_SESSION['Donor'] as $key => $val ){
				if ( !$this->isSomething( $key ) ){
					$this->setVal( $key, $val );
				} else {
					//TODO: Change this to a switch statement if we get more 
					//fields in here. 
					if ( $key === 'referrer' ){
						$this->setVal( $key, $val );
					}
				}
			}
		}
	}

	/**
	 * Returns an array of normalized and escaped donation data
	 * @return array
	 */
	public function getDataEscaped() {
		$escaped = $this->normalized;
		array_walk( $escaped, array( $this, 'sanitizeInput' ) );
		return $escaped;
	}

	/**
	 * Returns an array of normalized (but unescaped) donation data
	 * @return array 
	 */
	public function getDataUnescaped() {
		return $this->normalized;
	}

	/**
	 * Tells you if a value in $this->normalized is something or not. 
	 * @param string $key The field you would like to determine if it exists in 
	 * a usable way or not. 
	 * @return boolean true if the field is something. False if it is null, or 
	 * an empty string. 
	 */
	public function isSomething( $key ) {
		if ( array_key_exists( $key, $this->normalized ) ) {
			if ( is_null($this->normalized[$key]) || $this->normalized[$key] === '' ) {
				return false;
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * getVal_Escaped
	 * @param string $key The data field you would like to retrieve. Pulls the 
	 * data from $this->normalized if it is found to be something. 
	 * @return mixed The normalized and escaped value of that $key. 
	 */
	public function getVal_Escaped( $key ) {
		if ( $this->isSomething( $key ) ) {
			//TODO: If we ever start sanitizing in a more complicated way, we should move this 
			//off to a function and have both getVal_Escaped and sanitizeInput call that. 
			return htmlspecialchars( $this->normalized[$key], ENT_COMPAT, 'UTF-8', false );
		} else {
			return null;
		}
	}
	
	/**
	 * getVal
	 * For Internal Use Only! External objects should use getVal_Escaped.
	 * @param string $key The data field you would like to retrieve directly 
	 * from $this->normalized. 
	 * @return mixed The normalized value of that $key. 
	 */
	protected function getVal( $key ) {
		if ( $this->isSomething( $key ) ) {
			return $this->normalized[$key];
		} else {
			return null;
		}
	}

	/**
	 * Sets a key in the normalized data array, to a new value.
	 * This function should only ever be used for keys that are not listed in 
	 * DonationData::getCalculatedFields().
	 * TODO: If the $key is listed in DonationData::getCalculatedFields(), use 
	 * DonationData::addData() instead. Or be a jerk about it and throw an 
	 * exception. (Personally I like the second one)
	 * @param string $key The key you want to set.
	 * @param string $val The value you'd like to assign to the key. 
	 */
	public function setVal( $key, $val ) {
		$this->normalized[$key] = $val;
	}

	/**
	 * Removes a value from $this->normalized. 
	 * @param type $key 
	 */
	public function expunge( $key ) {
		if ( array_key_exists( $key, $this->normalized ) ) {
			unset( $this->normalized[$key] );
		}
	}
	
	/**
	 * Returns an array of all the fields that get re-calculated during a 
	 * normalize. 
	 * This can be used on the outside when in the process of changing data, 
	 * particularly if any of the recalculted fields need to be restaged by the 
	 * gateway adapter. 
	 * @return array An array of values matching all recauculated fields.  
	 */
	public function getCalculatedFields() {
		$fields = array(
			'utm_source',
			'amount',
			'order_id',
			'i_order_id',
			'gateway',
			'optout',
			'anonymous',
			'language',
			'premium_language',
			'contribution_tracking_id', //sort of...
			'currency_code',
			'user_ip',
		);
		return $fields;
	}

	/**
	 * Normalizes the current set of data, just after it's been 
	 * pulled (or re-pulled) from a data source. 
	 * Care should be taken in the normalize helper functions to write code in 
	 * such a way that running them multiple times on the same array won't cause 
	 * the data to stroll off into the sunset: Normalize will definitely need to 
	 * be called multiple times against the same array. 
	 */
	protected function normalize() {
		if ( !empty( $this->normalized ) ) {
			$this->setUtmSource();
			$this->setNormalizedAmount();
			$this->setNormalizedOrderIDs();
			$this->setGateway();
			$this->setNormalizedOptOuts();
			$this->setLanguage();
			$this->setIPAddresses();
			$this->setCountry(); //must do this AFTER setIPAddress...
			$this->handleContributionTrackingID();
			$this->setCurrencyCode();
			$this->setFormClass();
			$this->renameCardType();
			
			$this->getValidationErrors();
		}
	}
	
	/**
	 * normalize helper function
	 * Sets user_ip and server_ip. 
	 */
	protected function setIPAddresses(){
		//if we are coming in from the orphan slayer, the client ip should 
		//already be populated with something un-local, and we'd want to keep 
		//that.
		if ( !$this->isSomething( 'user_ip' ) || $this->getVal( 'user_ip' ) === '127.0.0.1' ){
			$this->setVal( 'user_ip', WMF_Framework::get_ip() );
		}
		
		if ( array_key_exists( 'SERVER_ADDR', $_SERVER ) ){
			$this->setVal( 'server_ip', $_SERVER['SERVER_ADDR'] );
		} else {
			//command line? 
			$this->setVal( 'server_ip', '127.0.0.1' );
		}
		
		
	}
	
	/**
	 * normalize helper function
	 * Sets the form class we will be using. 
	 * In the case that we are using forms, form_name will be harvested from 
	 * $wgRequest by populateData. If we are coming from somewhere that does not 
	 * use a form interface (like an api call), this logic should be skipped. 
	 * 
	 * For any specified form, if it is enabled and available, the class would 
	 * have been autoloaded at this point. If it is not enabled and available, 
	 * we will check the default for the calling gateway, and failing that, 
	 * form_class will be set to null, which should result in a redirect.
	 *
	 * Do not actually try to load the forms here.
	 * Do determine if the requested forms will load or not.
	 *
	 * @see GatewayForm::displayForm()
	 */
	protected function setFormClass(){

		// Is this the default form
		$default = false;

		if ( $this->isSomething( 'form_name' ) ){
			$class_name = "Gateway_Form_" . $this->getVal( 'form_name' );
		} else {
			$default = true;
			$class_name = "Gateway_Form_" . $this->getGatewayGlobal( 'DefaultForm' );
		}
		
		if ( !class_exists( $class_name ) ) {

			$class_name_orig = $class_name;
			/*
			 * If $class_name is not the default form, then check to see if the
			 * default form is available.
			 */
			if (!$default) {

				$log_message = '"' . $class_name . '"';
				$this->log( '"Form class not found" ' . $log_message , LOG_INFO );

				$class_name = "Gateway_Form_" . $this->getGatewayGlobal( 'DefaultForm' );
			}

			if ( class_exists( $class_name ) ) {
				$this->setVal( 'form_name', $this->getGatewayGlobal( 'DefaultForm' ) );
			} else {
				throw new WmfPaymentAdapterException( 'Could not find form ' . $class_name_orig . ', nor default form ' . $class_name );

				// Unset class name
				$class_name = null;
			}
		}

		$this->setVal( 'form_class', $class_name );		
	}

	/**
	 * munge the legacy card_type field into payment_submethod
	 */
	protected function renameCardType()
	{
		if ($this->getVal('payment_method') == 'cc')
		{
			if ($this->isSomething('card_type'))
			{
				$this->setVal('payment_submethod', $this->getVal('card_type'));
			}
		}
	}
	
	/**
	 * normalize helper function
	 * Setting the country correctly.
	 * If we have no country, we try to get something rational through GeoIP 
	 * lookup.
	 */
	protected function setCountry() {
		if ( !$this->isSomething('country') ){
			// If no country was passed, try to do GeoIP lookup
			// Requires php5-geoip package
			if ( function_exists( 'geoip_country_code_by_name' ) ) {
				$ip = $this->getVal( 'user_ip' );
				if ( WMF_Framework::is_valid_ip( $ip ) ) {
					$country = geoip_country_code_by_name( $ip );
					$this->setVal('country', $country);
				}
			}
		}
	}
	
	/**
	 * normalize helper function
	 * Setting the currency code correctly. 
	 * Historically, this value could come in through 'currency' or 
	 * 'currency_code'. After this fires, we will only have 'currency_code'. 
	 */
	protected function setCurrencyCode() {
		//at this point, we can have either currency, or currency_code. 
		//-->>currency_code has the authority!<<-- 
		$currency = false;
		
		if ( $this->isSomething( 'currency_code' ) ) {
			$currency = $this->getVal( 'currency_code' );
		} elseif ( $this->isSomething( 'currency' ) ) {
			$currency = $this->getVal( 'currency' );
			$this->expunge( 'currency' );
		}
		
		if ( $currency ){
			$this->setVal( 'currency_code', $currency );
		} else {
			//we want this set tu null if neither of them was anything, so 
			//things using this data know to use their own defaults. 
			$this->setVal( 'currency_code', null );
		}
	}
	
	/**
	 * normalize helper function.
	 * Assures that if no contribution_tracking_id is present, a row is created 
	 * in the Contribution tracking table, and that row is assigned to the 
	 * current contribution we're tracking. 
	 * If a contribution tracking id is already present, no new rows will be 
	 * assigned. 
	 */
	protected function handleContributionTrackingID(){
		if ( !$this->isSomething( 'contribution_tracking_id' ) && 
			( !$this->isCaching() ) ){
			$this->saveContributionTracking();
		} 
	}
	
	/**
	 * Tells us if we think we're in caching mode or not. 
	 * @staticvar string $cache Keeps track of the mode so we don't have to 
	 * calculate it from the data fields more than once. 
	 * @return boolean true if we are going to be caching, false if we aren't. 
	 */
	public function isCaching(){
		
		static $cache = null;
		
		if ( is_null( $cache ) ){
			if ( $this->getVal( '_cache_' ) === 'true' ){ //::head. hit. keyboard.::
				if ( $this->isSomething( 'utm_source_id' ) && !is_null( 'utm_source_id' ) ){
					$cache = true;
				}
			}
			if ( is_null( $cache ) ){
				$cache = false;
			}
		}
		
		 //this business could change at any second, and it will prevent us from 
		 //caching, so we're going to keep asking if it's set. 
		if (self::sessionExists()){
			$cache = false;
		}		
		
		return $cache;
	}
	
	/**
	 * normalize helper function.
	 * Takes all possible sources for the intended donation amount, and 
	 * normalizes them into the 'amount' field.  
	 */
	protected function setNormalizedAmount() {
		if ( !($this->isSomething( 'amount' )) || !(preg_match( '/^\d+(\.(\d+)?)?$/', $this->getVal( 'amount' ) ) ) ) {
			if ( $this->isSomething( 'amountGiven' ) && preg_match( '/^\d+(\.(\d+)?)?$/', $this->getVal( 'amountGiven' ) ) ) {
				$this->setVal( 'amount', number_format( $this->getVal( 'amountGiven' ), 2, '.', '' ) );
			} elseif ( $this->isSomething( 'amount' ) && $this->getVal( 'amount' ) == '-1' ) {
				$this->setVal( 'amount', $this->getVal( 'amountOther' ) );
			} else {
				$this->setVal( 'amount', '0.00' );
			}
		}
	}

	/**
	 * normalize helper function.
	 * Ensures that order_id and i_order_id are ready to go, depending on what 
	 * comes in populated or not, and where it came from.
	 * @return null
	 */
	protected function setNormalizedOrderIDs() {
		//basically, we need a new order_id every time we come through here, but if there's an internal already there,
		//we want to use that one internally. So.
		//Exception: If we pass in an order ID in the querystring: Don't mess with it.
		//TODO: I'm pretty sure I'm not supposed to do this directly.
		if ( array_key_exists( 'order_id', $_GET ) ) {
			$this->setVal( 'order_id', $_GET['order_id'] );
			$this->setVal( 'i_order_id', $_GET['order_id'] );
			return;
		}

		$this->setVal( 'order_id', $this->generateOrderId() );
		if ( !$this->isSomething( 'i_order_id' ) ) {
			$this->setVal( 'i_order_id', $this->generateOrderId() );
		}
	}

	/**
	 * Generate an order id exactly once for this go-round.
	 */
	protected static function generateOrderId() {
		static $order_id = null;
		if ( $order_id === null ) {
			$order_id = ( double ) microtime() * 1000000 . mt_rand( 1000, 9999 );
		}
		return $order_id;
	}

	/**
	 * Sanitize user input.
	 *
	 * Intended to be used with something like array_walk.
	 *
	 * @param $value The value of the array
	 * @param $key The key of the array
	 * @param $flags The flag constant for htmlspecialchars
	 * @param $double_encode Whether or not to double-encode strings
	 */
	protected function sanitizeInput( &$value, $key, $flags=ENT_COMPAT, $double_encode=false ) {
		$value = htmlspecialchars( $value, $flags, 'UTF-8', $double_encode );
	}

	/**
	 * log: This grabs the adapter class that instantiated DonationData, and 
	 * uses its log function. 
	 * @param string $message The message to log. 
	 * @param type $log_level 
	 */
	protected function log( $message, $log_level=LOG_INFO ) {
		$c = $this->getAdapterClass();
		if ( $c && is_callable( array( $c, 'log' ) )){
			$c::log( $message, $log_level );
		}
	}

	/**
	 * getGatewayIdentifier
	 * This grabs the adapter class that instantiated DonationData, and returns 
	 * the result of its 'getIdentifier' function. Used for normalizing the 
	 * 'gateway' value, and stashing and retrieving the edit token (and other 
	 * things, where needed) in the session. 
	 * @return type 
	 */
	protected function getGatewayIdentifier() {
		$c = $this->getAdapterClass();
		if ( $c && is_callable( array( $c, 'getIdentifier' ) ) ){
			return $c::getIdentifier();
		} else {
			return 'DonationData';
		}
	}

	/**
	 * getGatewayGlobal
	 * This grabs the adapter class that instantiated DonationData, and returns 
	 * the result of its 'getGlobal' function for the $varname passed in. Used 
	 * to determine gateway-specific configuration settings. 
	 * @param string $varname the global variable (minus prefix) that we want to 
	 * check. 
	 * @return mixed  The value of the gateway global if it exists. Else, the 
	 * value of the Donation Interface global if it exists. Else, null.
	 */
	protected function getGatewayGlobal( $varname ) {
		$c = $this->getAdapterClass();
		if ( $c && is_callable( array( $c, 'getGlobal' ) ) ){
			return $c::getGlobal( $varname );
		} else {
			return false;
		}
	}

	/**
	 * normalize helper function.
	 * Sets the gateway to be the gateway that called this class in the first 
	 * place.
	 */
	protected function setGateway() {
		//TODO: Hum. If we have some other gateway in the form data, should we go crazy here? (Probably)
		$gateway = $this->gatewayID;
		$this->setVal( 'gateway', $gateway );
	}
	
	/**
	 * normalize helper function.
	 * If the language has not yet been set or is not valid, pulls the language code 
	 * from the current global language object. 
	 * Also sets the premium_language as the calculated language if it's not 
	 * already set coming in (had been defaulting to english). 
	 */
	protected function setLanguage() {
		$language = false;
		
		if ( $this->isSomething( 'uselang' ) ) {
			$language = $this->getVal( 'uselang' );
		} elseif ( $this->isSomething( 'language' ) ) {
			$language = $this->getVal( 'language' );
		}
		
		if ( $language == false
			|| !WMF_Framework::is_valid_builtin_language_code( $this->normalized['language'] ) )
		{
			$language = WMF_Framework::get_language_code() ;
		}
		
		$this->setVal( 'language', $language );
		$this->expunge( 'uselang' );
		
		if ( !$this->isSomething( 'premium_language' ) ){
			$this->setVal( 'premium_language', $language );
		}
		
	}

	/**
	 * This function sets the token to the string 'cache' if we're caching, and 
	 * then sets the s-maxage header to whatever you specify for the SMaxAge.
	 * NOTES: The bit where we setSquidMaxage will not work at all, under two 
	 * conditions: 
	 * The user has a session ID.
	 * The mediawiki_session cookie is set in the user's browser.
	 * @global bool $wgUseSquid
	 * @global type $wgOut 
	 */
	protected function doCacheStuff() {
		//TODO: Wow, name.
		// if _cache_ is requested by the user, do not set a session/token; dynamic data will be loaded via ajax
		if ( $this->isCaching() ) {
			self::log( $this->getAnnoyingOrderIDLogLinePrefix() . ' Cache requested', LOG_DEBUG );
			$this->setVal( 'token', 'cache' );

			// if we have squid caching enabled, set the maxage
			$maxAge = $this->getGatewayGlobal( 'SMaxAge' );
			
			if ( WMF_Framework::is_squid() && ( $maxAge !== false ) ) {
				self::log( $this->getAnnoyingOrderIDLogLinePrefix() . ' Setting s-max-age: ' . $maxAge, LOG_DEBUG );
				WMF_Framework::set_squid_maxage( $maxAge );
			}
		}
	}

	/**
	 * getAnnoyingOrderIDLogLinePrefix
	 * Constructs and returns the annoying order ID log line prefix. 
	 * This has moved from being annoyingly all over the place in the edit token 
	 * logging code before it was functionalized, to being annoying to look at 
	 * in the logs because the two numbers in the prefix are frequently 
	 * identical (and large).
	 * TODO: Determine if anything actually looks at both of those numbers, in 
	 * order to make this less annoying. Rename on success. 
	 * @return string Annoying Order ID Log Line Prefix in all its dubious glory. 
	 */
	protected function getAnnoyingOrderIDLogLinePrefix() {
		return $this->getVal( 'order_id' ) . ' ' . $this->getVal( 'i_order_id' ) . ': ';
	}

	/**
	 * Establish an 'edit' token to help prevent CSRF, etc.
	 *
	 * We use this in place of $wgUser->editToken() b/c currently
	 * $wgUser->editToken() is broken (apparently by design) for
	 * anonymous users.  Using $wgUser->editToken() currently exposes
	 * a security risk for non-authenticated users.  Until this is
	 * resolved in $wgUser, we'll use our own methods for token
	 * handling.
	 * 
	 * Public so the api can get to it. 
	 *
	 * @return string
	 */
	public function token_getSaltedSessionToken() {

		// make sure we have a session open for tracking a CSRF-prevention token
		self::ensureSession();

		$gateway_ident = $this->gatewayID;

		if ( !isset( $_SESSION[$gateway_ident . 'EditToken'] ) ) {
			// generate unsalted token to place in the session
			$token = self::token_generateToken();
			$_SESSION[$gateway_ident . 'EditToken'] = $token;
		} else {
			$token = $_SESSION[$gateway_ident . 'EditToken'];
		}

		return $this->token_applyMD5AndSalt( $token );
	}
	
	/**
	 * token_refreshAllTokenEverything
	 * In the case where we have an expired session (token mismatch), we go 
	 * ahead and fix it for 'em for their next post. We do this by refreshing 
	 * everything that has to do with the edit token.
	 */
	protected function token_refreshAllTokenEverything(){
		$unsalted = self::token_generateToken();	
		$gateway_ident = $this->gatewayID;
		self::ensureSession();
		$_SESSION[$gateway_ident . 'EditToken'] = $unsalted;
		$salted = $this->token_getSaltedSessionToken();
		$this->setVal( 'token', $salted );
	}
	
	/**
	 * token_applyMD5AndSalt
	 * Takes a clear-text token, and returns the MD5'd result of the token plus 
	 * the configured gateway salt.
	 * @param string $clear_token The original, unsalted, unencoded edit token. 
	 * @return string The salted and MD5'd token. 
	 */
	protected function token_applyMD5AndSalt( $clear_token ){
		$salt = $this->getGatewayGlobal( 'Salt' );
		
		if ( is_array( $salt ) ) {
			$salt = implode( "|", $salt );
		}
		
		$salted = md5( $clear_token . $salt ) . EDIT_TOKEN_SUFFIX;
		return $salted;
	}


	/**
	 * token_generateToken
	 * Generate a random string to be used as an edit token. 
	 * @var string $padding A string with which we could pad out the random hex 
	 * further. 
	 * @return string
	 */
	public static function token_generateToken( $padding = '' ) {
		$token = dechex( mt_rand() ) . dechex( mt_rand() );
		return md5( $token . $padding );
	}

	/**
	 * token_matchEditToken
	 * Determine the validity of a token by checking it against the salted 
	 * version of the clear-text token we have already stored in the session. 
	 * On failure, it resets the edit token both in the session and in the form, 
	 * so they will match on the user's next load. 
	 *
	 * @var string $val
	 * @return bool
	 */
	protected function token_matchEditToken( $val ) {
		// fetch a salted version of the session token
		$sessionSaltedToken = $this->token_getSaltedSessionToken();
		if ( $val != $sessionSaltedToken ) {
			WMF_Framework::log( "DonationData::matchEditToken: broken session data\n" );
			//and reset the token for next time. 
			$this->token_refreshAllTokenEverything();
		}
		return $val == $sessionSaltedToken;
	}

	/**
	 * ensureSession
	 * Ensure that we have a session set for the current user.
	 * If we do not have a session set for the current user,
	 * start the session.
	 * BE CAREFUL with this one, as creating sessions willy-nilly will break 
	 * squid caching for reasons that are not immediately obvious. 
	 * (See DonationData::doCacheStuff, and basically everything about setting 
	 * headers in $wgOut)
	 */
	protected static function ensureSession() {
		// if the session is already started, do nothing
		if ( self::sessionExists() )
			return;

		// otherwise, fire it up using global mw function wfSetupSession
		WMF_Framework::setup_session();
	}
	
	/**
	 * sessionExists
	 * Checks to see if the session exists without actually creating one. 
	 * @return bool true if we have a session, otherwise false.  
	 */
	protected static function sessionExists() {
		if ( session_id() )
			return true;
		return false;
	}

	/**
	 * token_checkTokens
	 * The main function to check the salted and MD5'd token we should have 
	 * saved and gathered from $wgRequest, against the clear-text token we 
	 * should have saved to the user's session. 
	 * token_getSaltedSessionToken() will start off the process if this is a 
	 * first load, and there's no saved token in the session yet. 
	 * @global Webrequest $wgRequest
	 * @staticvar string $match
	 * @return type 
	 */
	public function token_checkTokens() {
		static $match = null; //because we only want to do this once per load.

		if ( $match === null ) {
			if ( $this->isCaching() ){
				//This makes sense.
				//If all three conditions for caching are currently true, the 
				//last thing we want to do is screw it up by setting a session 
				//token before the page loads, because sessions break caching. 
				//The API will set the session and form token values immediately 
				//after that first page load, which is all we care about saving 
				//in the cache anyway. 
				return true;
			}

			// establish the edit token to prevent csrf
			$token = $this->token_getSaltedSessionToken();

			$this->log( $this->getAnnoyingOrderIDLogLinePrefix() . ' editToken: ' . $token, LOG_DEBUG );

			// match token			
			if ( !$this->isSomething( 'token' ) ){
				$this->setVal( 'token', $token );				
			}
			$token_check = $this->getVal( 'token' );
			
			$match = $this->token_matchEditToken( $token_check );
			if ( $this->wasPosted() ) {
				$this->log( $this->getAnnoyingOrderIDLogLinePrefix() . ' Submitted edit token: ' . $this->getVal( 'token' ), LOG_DEBUG );
				$this->log( $this->getAnnoyingOrderIDLogLinePrefix() . ' Token match: ' . ($match ? 'true' : 'false' ), LOG_DEBUG );
			}
		}

		return $match;
	}

	/**
	 * normalize helper function.
	 * 
	 * Checks to see if the utm_source is set properly for the credit card
	 * form including any cc form variants (identified by utm_source_id).  If
	 * anything cc form related is out of place for the utm_source, this
	 * will fix it.
	 *
	 * the utm_source is structured as: banner.landing_page.payment_instrument
	 */
	protected function setUtmSource() {
		
		$utm_source = $this->getVal( 'utm_source' );
		$utm_source_id = $this->getVal( 'utm_source_id' );
		
		//TODO: Seriously, you need to move this. 
		if ( $this->isSomething('payment_method') ){
			$payment_method = $this->getVal( 'payment_method' );
		} else {
			$payment_method = 'cc';
		}
		
		// this is how the payment method portion of the utm_source should be defined
		$correct_payment_method_source = ( $utm_source_id ) ? $payment_method . $utm_source_id . '.' . $payment_method : $payment_method;

		// check to see if the utm_source is already correct - if so, return
		if ( !is_null( $utm_source ) && preg_match( '/' . str_replace( ".", "\.", $correct_payment_method_source ) . '$/', $utm_source ) ) {
			return; //nothing to do. 
		}

		// split the utm_source into its parts for easier manipulation
		$source_parts = explode( ".", $utm_source );

		// if there are no sourceparts element, then the banner portion of the string needs to be set.
		// since we don't know what it is, set it to an empty string
		if ( !count( $source_parts ) )
			$source_parts[0] = '';

		// if the utm_source_id is set, set the landing page portion of the string to cc#
		$source_parts[1] = ( $utm_source_id ) ? $payment_method . $utm_source_id : ( isset( $source_parts[1] ) ? $source_parts[1] : '' );

		// the payment instrument portion should always be 'cc' if this method is being accessed
		$source_parts[2] = $payment_method;

		// reconstruct, and set the value.
		$utm_source = implode( ".", $source_parts );
		$this->setVal( 'utm_source' , $utm_source );
	}

	/**
	 * Determine proper opt-out settings for contribution tracking
	 *
	 * because the form elements for comment anonymization and email opt-out
	 * are backwards (they are really opt-in) relative to contribution_tracking
	 * (which is opt-out), we need to reverse the values.
	 * Difficulty here is compounded by the fact that these values come from 
	 * checkboxes on forms, which simply don't make it to $wgRequest if they are 
	 * not checked... or not present in the form at all. In other words, this 
	 * situation is painful and you probably want to leave it alone.
	 * NOTE: If you prune here, and there is a paypal redirect, you will have
	 * problems with the email-opt/optout and comment-option/anonymous.
	 */
	protected function setNormalizedOptOuts( $prune = false ) {
		$optout['optout'] = ( $this->isSomething( 'email-opt' ) && $this->getVal( 'email-opt' ) == "1" ) ? '0' : '1';
		$optout['anonymous'] = ( $this->isSomething( 'comment-option' ) && $this->getVal( 'comment-option' ) == "1" ) ? '0' : '1';
		foreach ( $optout as $thing => $stuff ) {
			$this->setVal( $thing, $stuff );
		}
		if ( $prune ) {
			$this->expunge( 'email-opt' );
			$this->expunge( 'comment-option' );
		}
	}

	/**
	 * Clean array of tracking data to contain valid fields
	 *
	 * Compares tracking data array to list of valid tracking fields and
	 * removes any extra tracking fields/data.  Also sets empty values to
	 * 'null' values.
	 * @param bool $unset If set to true, empty values will be unset from the 
	 * return array, rather than set to null. (default: false)
	 * @return array Clean tracking data 
	 */
	public function getCleanTrackingData( $unset = false ) {

		// define valid tracking fields
		$tracking_fields = array(
			'note',
			'referrer',
			'anonymous',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_key',
			'optout',
			'language',
			'ts'
		);

		foreach ( $tracking_fields as $value ) {
			if ( $this->isSomething( $value ) ) {
				$tracking_data[$value] = $this->getVal( $value );
			} else {
				if ( !$unset ){
					$tracking_data[$value] = null;
				}
			}
		}

		return $tracking_data;
	}

	/**
	 * Saves a NEW ROW in the Contribution Tracking table and returns the new ID. 
	 * @return boolean true if we got a contribution tracking # back, false if 
	 * something went wrong.  
	 */
	public function saveContributionTracking() {

		$tracked_contribution = $this->getCleanTrackingData();

		// insert tracking data and get the tracking id
		$result = self::insertContributionTracking( $tracked_contribution );

		$this->setVal( 'contribution_tracking_id', $result );

		if ( !$result ) {
			return false;
		}
		return true;
	}

	/**
	 * Insert a record into the contribution_tracking table
	 *
	 * @param array $tracking_data The array of tracking data to insert to contribution_tracking
	 * @return mixed Contribution tracking ID or false on failure
	 */
	public static function insertContributionTracking( $tracking_data ) {
		//XXX
		/*
		$db = ContributionTrackingProcessor::contributionTrackingConnection();

		if ( !$db ) {
			return false;
		}

		// set the time stamp if it's not already set
		if ( !isset( $tracking_data['ts'] ) || !strlen( $tracking_data['ts'] ) ) {
			$tracking_data['ts'] = $db->timestamp();
		}

		// Store the contribution data
		if ( $db->insert( 'contribution_tracking', $tracking_data ) ) {
			return $db->insertId();
		} else {
			return false;
		}
		*/
	}

	/**
	 * Update contribution_tracking table
	 *
	 * @param array $data Form data
	 * @param bool $force If set to true, will ensure that contribution tracking is updated
	 */
	public function updateContributionTracking( $force = false ) {
		//XXX
		/*
		// ony update contrib tracking if we're coming from a single-step landing page
		// which we know with cc# in utm_source or if force=true or if contribution_tracking_id is not set
		if ( !$force &&
			!preg_match( "/cc[0-9]/", $this->getVal( 'utm_source' ) ) &&
			is_numeric( $this->getVal( 'contribution_tracking_id' ) ) ) {
			return;
		}

		$db = ContributionTrackingProcessor::contributionTrackingConnection();

		// if contrib tracking id is not already set, we need to insert the data, otherwise update
		if ( !$this->getVal( 'contribution_tracking_id' ) ) {
			$tracked_contribution = $this->getCleanTrackingData();
			$this->setVal( 'contribution_tracking_id', $this->insertContributionTracking( $tracked_contribution ) );
		} else {
			$tracked_contribution = $this->getCleanTrackingData( true );
			$db->update( 'contribution_tracking', $tracked_contribution, array( 'id' => $this->getVal( 'contribution_tracking_id' ) ) );
		}
		*/
	}

	/**
	 * addDonorDataToSession
	 * Adds all the fields that are required to make a well-formed stomp 
	 * message, to the user's session for later use. This mechanism is used by gateways that 
	 * have a user being directed somewhere out of our control, and then coming 
	 * back to complete a transaction. (Globalcollect Hosted Credit Card, for 
	 * example)
	 * 
	 */
	public function addDonorDataToSession() {
		self::ensureSession();
		$donordata = $this->getStompMessageFields();
		$donordata[] = 'order_id';
		
		foreach ( $donordata as $item ) {
			if ( $this->isSomething( $item ) ) {
				$_SESSION['Donor'][$item] = $this->getVal( $item );
			}
		}
	}
	
	/**
	 * Checks to see if we have donor data in our session. 
	 * This can be useful for determining if a user should be at a certain point 
	 * in the workflow for certain gateways. For example: This is used on the 
	 * outside of the adapter in GlobalCollect's resultswitcher page, to 
	 * determine if the user is actually in the process of making a credit card 
	 * transaction. 
	 * @param string $key Optional: A particular key to check against the 
	 * donor data in session. 
	 * @param string $value Optional (unless $key is set): A value that the $key 
	 * should contain, in the donor session.  
	 * @return boolean true if the session contains donor data (and if the data 
	 * key matches, when key and value are set), and false if there is no donor 
	 * data (or if the key and value do not match)
	 */
	public function hasDonorDataInSession(  $key = false, $value= ''  ) {
		if ( self::sessionExists() && array_key_exists( 'Donor', $_SESSION ) ) {
			if ( $key == false ){
				return true;
			}
			if ( array_key_exists($key, $_SESSION['Donor'] ) && $_SESSION['Donor'][$key] === $value ){
				return true;
			} else {
				return false;
			}
			
			
		} else {
			return false;
		}
	}

	/**
	 * Unsets the session data, in the case that we've saved it for gateways 
	 * like GlobalCollect that require it to persist over here through their 
	 * iframe experience. 
	 */
	public function unsetDonorSessionData() {
		unset( $_SESSION['Donor'] );
	}
	
	/**
	 * This should kill the session as hard as possible.
	 * It will leave the cookie behind, but everything it could possibly 
	 * reference will be gone. 
	 */
	public function killAllSessionEverything() {
		//yes: We do need all of these things, to be sure we're killing the 
		//correct session data everywhere it could possibly be. 
		self::ensureSession(); //make sure we are killing the right thing. 
		session_unset(); //frees all registered session variables. At this point, they can still be re-registered. 
		session_destroy(); //killed on the server. 
	}

	/**
	 * addData
	 * Adds an array of data to the normalized array, and then re-normalizes it. 
	 * NOTE: If any gateway is using this function, it should then immediately 
	 * repopulate its own data set with the DonationData source, and then 
	 * re-stage values as necessary. 
	 * @param array $newdata An array of data to integrate with the existing 
	 * data held by the DonationData object. 
	 */
	public function addData( $newdata ) {
		if ( is_array( $newdata ) && !empty( $newdata ) ) {
			foreach ( $newdata as $key => $val ) {
				if ( !is_array( $val ) ) {
					$this->setVal( $key, $val );
				}
			}
		}
		$this->normalize();
	}

	/**
	 * incrementNumAttempt
	 * Adds one to the 'numAttempt' field we use to keep track of how many times 
	 * a donor has tried to do something. 
	 */
	public function incrementNumAttempt() {
		if ( $this->isSomething( 'numAttempt' ) ) {
			$attempts = $this->getVal( 'numAttempt' );
			if ( is_numeric( $attempts ) ) {
				$this->setVal( 'numAttempt', $attempts + 1 );
			} else {
				//assume garbage = 0, so...
				$this->setVal( 'numAttempt', 1 );
			}
		}
	}

	/**
	 * Gets the name of the adapter class that instantiated DonationData. 
	 * @return mixed The name of the class if it exists, or false. 
	 */
	protected function getAdapterClass(){
		if ( class_exists( $this->boss ) ) {
			return $this->boss;
		} else {
			return false;
		}
	}
	
	/**
	 * Returns an array of field names we intend to send to activeMQ via a Stomp 
	 * message. Note: These are field names from the FORM... not the field names 
	 * that will appear in the stomp message. 
	 * TODO: Move the mapping for donation data from 
	 * /extensions/DonationData/activemq_stomp/activemq_stomp.php
	 * to somewhere in DonationData. 	 * 
	 */
	public function getStompMessageFields(){
		$stomp_fields = array(
			'contribution_tracking_id',
			'optout',
			'anonymous',
			'comment',
			'size',
			'premium_language',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'language',
			'referrer',
			'email',
			'fname',
			'mname',
			'lname',
			'street',
			'street_supplemental',
			'city',
			'state',
			'country',
			'zip',
			'fname2',
			'lname2',
			'street2',
			'city2',
			'state2',
			'country2',
			'zip2',
			'gateway',
			'gateway_txn_id',
			'recurring',
			'payment_method',
			'payment_submethod',
			'response',
			'currency_code',
			'amount',
			'user_ip',
			'date',
		);
		return $stomp_fields;
	}
	
	/**
	 * Basically, this is a wrapper for the $wgRequest wasPosted function that 
	 * won't give us notices if we weren't even a web request. 
	 * I realize this is pretty lame. 
	 * Notices, however, are more lame. 
	 * @global type $wgRequest
	 * @staticvar string $posted Keeps track so we don't have to figure it out twice. 
	 */
	public function wasPosted(){
		static $posted = null;
		if ($posted === null){
			$posted = (array_key_exists('REQUEST_METHOD', $_SERVER) && WMF_Framework::was_posted());
		}
		return $posted; 
	}
	
	/**
	 * getValidationErrors
	 * This function will go through all the data we have pulled from wherever 
	 * we've pulled it, and make sure it's safe and expected and everything. 
	 * If it is not, it will return an array of errors ready for any 
	 * DonationInterface form class derivitive to display. 
	 */
	public function getValidationErrors( $recalculate = false, $check_not_empty = array() ){
		if ( is_null( $this->validationErrors ) || $recalculate ) {
			$this->validationErrors = DataValidator::validate( $this->normalized, $check_not_empty );
		}
		return $this->validationErrors;
	}
	
	/**
	 * validatedOK
	 * Checks to see if the data validated ok (no errors). 
	 * @return boolean True if no errors, false if errors exist. 
	 */
	public function validatedOK() {
		if ( is_null( $this->validationErrors ) ){
			$this->getValidationErrors();
		}
		
		if ( count( $this->validationErrors ) === 0 ){
			return true;
		}
		return false;
	}
	
}

?>
