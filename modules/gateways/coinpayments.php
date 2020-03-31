<?php
/**
 * CoinPayments WHMCS Payment Gateway Module
 *
 * Version: 1.0.0
 * Author: Shaun Reitan
 * https://github.com/ShaunR/CoinPayments-WHMCS-Gateway-Module.git
 * 
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function coinpayments_MetaData()
{
    return [
        'DisplayName' => 'CoinPayments.Net',
        'APIVersion' => '1.1'
    ];
}



function coinpayments_config()
{
    return [

        // Adds backward compatability with older WHMCS versions
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'CoinPayments.Net'
        ],

        // Merchant ID
        'merchantId' => [
            'FriendlyName' => 'Merchant Id',
            'Type' => 'text',
            'Size' => '32',
            'Default' => '',
            'Description' => 'Your merchant id can be found under the account settings section'
        ],

        // IPN Secret
        'ipnSecret' => [
            'FriendlyName' => 'IPN Secret',
            'Type' => 'password',
            'Size' => '32',
            'Description' => 'Your IPN secret can be found under merchant settings, you generate it yourself',
            'Default' => ''
        ],

        // Include Shipping Information
        'includeShippingAddress' => [
            'FriendlyName' => 'Include Shipping Information',
            'Type' => 'yesno',
            'Description' => 'Include shipping information',
        ],

        // Payment Button URL
        'paymentButtonImageUrl' => [
            'FriendlyName' => 'Payment Button Image URL',
            'Type' => 'text',
            'Size' => '50',
            'Default' => 'https://www.coinpayments.net/images/pub/buynow-med-grey.png',
            'Description' => 'Payment button image used on client invoice'
        ],

    ];

}

function coinpayments_link($params)
{


    // Require Merchant Id
    if (!isset($params['merchantId']) || $params['merchantId'] == '') {
        return '<p>Merchant Id is missing from gateway module configuration</p>';
    }

    // Require IPN secret
    if (!isset($params['ipnSecret']) || $params['ipnSecret'] == '') {
        return '<p>IPN secret is missing from gateway module configuration</p>';
    }


    // Payment Button Fields
    $fields = [
        'cmd' => '_pay_simple',
        
        'reset' => '1',

        'merchant' => $params['merchantId'],

        'currency' => $params['currency'],

        'amountf' => $params['amount'],

        'item_name' => $params['description'],

        'invoice' => $params['invoiceid'],

        'first_name' => $params['clientdetails']['firstname'],

        'last_name' => $params['clientdetails']['lastname'],

        'email' => $params['clientdetails']['email'],
        
        'ipn_url' => $params['systemurl'] . 'modules/gateways/callback/coinpayments.php',
        
        'success_url' => $params['returnurl'],

        'cancel_url' => $params['returnurl']
    ];

    // Include shipping information if configuration says to
    if ($params['includeShippingAddress'] == 'yes') {
        $fields['want_shipping'] = '1';

        $fields['address1'] = $params['clientdetails']['address1'];

        $fields['address2'] = $params['clientdetails']['address2'];

        $fields['city'] = $params['clientdetails']['city'];
        
        $fields['state'] = $params['clientdetails']['state'];

        $fields['zip'] = $params['clientdetails']['zip'];

        $fields['country'] = $params['clientdetails']['country'];

        $fields['phone'] = $params['clientdetails']['phonenumber'];
    }

    // Build HTML payment form
    $html = '<form id="coinpayments_form" action="https://www.coinpayments.net/index.php" method="post">';

    // Form Fields
    foreach ($fields as $fieldName => $fieldValue) {
        $html .= '<input type="hidden" name="' . $fieldName . '" value="' . htmlspecialchars($fieldValue) . '">';
    }

    $html .= '<input type="image" src="' . $params['paymentButtonImageUrl'] . '" alt="' . $params['name'] . ' - ' . $params['langpaynow'] . '">';
    $html .= '</form>';

    // Return HTML payment form
    return $html;
}
