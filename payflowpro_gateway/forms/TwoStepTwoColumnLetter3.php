<?php

class PayflowProGateway_Form_TwoStepTwoColumnLetter3 extends PayflowProGateway_Form_TwoStepTwoColumn {
	public function __construct( &$form_data, &$form_errors ) {
		global $wgScriptPath;

		// set the path to css, before the parent constructor is called, checking to make sure some child class hasn't already set this
		if ( !strlen( $this->getStylePath() ) ) {
			$this->setStylePath( $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/forms/css/TwoStepTwoColumnLetter3.css' );
		}

		parent::__construct( $form_data, $form_errors );
	}

	public function generateFormStart() {
		global $wgOut, $wgRequest;

		$form = parent::generateBannerHeader();

		$form .= Xml::openElement( 'table', array( 'width' => '100%', 'cellspacing' => 0, 'cellpadding' => 0, 'border' => 0 ) );
		$form .= Xml::openElement( 'tr' );
		$form .= Xml::openElement( 'td', array( 'id' => 'appeal', 'valign' => 'top' ) );

		$text_template = $wgRequest->getText( 'text_template', '2010/JimmyAppealLong' );
		// if the user has uselang set, honor that, otherwise default to the language set for the form defined by 'language' in the query string
		if ( $wgRequest->getText( 'language' ) ) $text_template .= '/' . $this->form_data[ 'language' ];

		$template = ( strlen( $text_template ) ) ? $wgOut->parse( '{{' . $text_template . '}}' ) : '';
		// if the template doesn't exist, prevent the display of the red link
		if ( preg_match( '/redlink\=1/', $template ) ) $template = NULL;
		$form .= $template;

		$form .= Xml::closeElement( 'td' );

		$form .= Xml::openElement( 'td', array( 'id' => 'donate', 'valign' => 'top' ) );

		// add noscript tags for javascript disabled browsers
		$form .= $this->getNoScript();

		$form .= Xml::tags( 'h2', array( 'id' => 'donate-head' ), wfMsg( 'payflowpro_gateway-please-complete' ) );

		// provide a place at the top of the form for displaying general messages
		if ( $this->form_errors['general'] ) {
			$form .= Xml::openElement( 'div', array( 'id' => 'mw-payflow-general-error' ) );
			if ( is_array( $this->form_errors['general'] ) ) {
				foreach ( $this->form_errors['general'] as $this->form_errors_msg ) {
					$form .= Xml::tags( 'p', array( 'class' => 'creditcard-error-msg' ), $this->form_errors_msg );
				}
			} else {
				$form .= Xml::tags( 'p', array( 'class' => 'creditcard-error-msg' ), $this->form_errors_msg );
			}
			$form .= Xml::closeElement( 'div' );  // close div#mw-payflow-general-error
		}

		// Xml::element seems to convert html to htmlentities
		$form .= "<p class='creditcard-error-msg'>" . $this->form_errors['retryMsg'] . "</p>";
		$form .= Xml::openElement( 'form', array( 'name' => 'payment', 'method' => 'post', 'action' => $this->getNoCacheAction(), 'onsubmit' => 'return validate_form(this)', 'autocomplete' => 'off' ) );

		$form .= $this->generateBillingContainer();
		return $form;
	}

	public function generateFormEnd() {
		$form = '';
		$form .= $this->generateFormClose();
		return $form;
	}

	protected function generateBillingContainer() {
		$form = '';
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-personal-info' ) );
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-donor' ) );
		$form .= $this->generateBillingFields();
		$form .= Xml::closeElement( 'table' ); // close table#payflow-table-donor
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-personal-info

		return $form;
	}

	protected function generateBillingFields() {
		global $wgScriptPath;

		$form = '';
		
		// amount
		$form .= '<tr>';
		$form .= '<td colspan="2">';
		$form .= '<table cellspacing="2" cellpadding="2" border="1" id="donation_amount">';
		$form .= '<tr>';
		$form .= '<td class="amount_header">'.wfMsg( 'payflowpro_gateway-description' ).'</td>';
		$form .= '<td class="amount_header">'.wfMsg( 'payflowpro_gateway-donor-amount' ).'</td>';
		$form .= '<td class="amount_header">'.wfMsg( 'payflowpro_gateway-donor-currency-label' ).'</td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="amount_data">'.wfMsg( 'payflowpro_gateway-donation' ).'</td>';
		$form .= '<td class="amount_data">'.$this->form_data['amount'].'</td>';
		$form .= '<td class="amount_data">'.$this->form_data[ 'currency' ].'</td>';
		$form .= '</tr>';
		$form .= '</table>';
		$form .= '</td>';
		$form .= '</tr>';
		
		$form .= '<h3>'.wfMsg( 'payflowpro_gateway-cc-form-header-personal' ).'</h3>';

		// name
		$form .= $this->getNameField();

		// email
		$form .= $this->getEmailField();

		// card logos
		$form .= '<tr>';
		$form .= '<td />';
		$form .= '<td>' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/credit_card_logos.gif" ) ) . '</td>';
		$form .= '</tr>';

		// card number
		$form .= $this->getCardNumberField();

		// cvv
		$form .= $this->getCvvField();

		// expiry
		$form .= $this->getExpiryField();

		// street
		$form .= $this->getStreetField();

		// city
		$form .= $this->getCityField();

		// state
		$form .= $this->getStateField();
		// zip
		$form .= $this->getZipField();

		// country
		$form .= $this->getCountryField();

		return $form;
	}

	/**
	 * Generate form closing elements
	 */
	public function generateFormClose() {
		$form = '';
		// add hidden fields
		$hidden_fields = $this->getHiddenFields();
		foreach ( $hidden_fields as $field => $value ) {
			$form .= Html::hidden( $field, $value );
		}

		$form .= Xml::closeElement( 'form' ); // close form 'payment'
		$form .= $this->generateDonationFooter();
		$form .= Xml::closeElement( 'td' );
		$form .= Xml::closeElement( 'tr' );
		$form .= Xml::closeElement( 'table' );
		return $form;
	}
}