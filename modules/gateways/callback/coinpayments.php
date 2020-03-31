<?php
/**
 * CoinPayments WHMCS Payment Gateway Module
 *
 * Version: 1.0.0
 * Author: Shaun Reitan
 * https://github.com/ShaunR/CoinPayments-WHMCS-Gateway-Module.git
 *
 */

use WHMCS\Billing\Invoice;
use WHMCS\Billing\Payment\Transaction;

// Init WHMCS
require_once __DIR__ . '/../../../init.php';

// Load required functions
App::load_function('gateway');

// Required POST params
$requiredPostParams = [
    'merchant',
    'ipn_type',
    'ipn_mode',
    'status',
    'status_text',
    'invoice',
    'txn_id',
    'amount1',
    'amount2',
    'currency1',
    'currency2'
];

// Check that all required POST params were received
foreach ($requiredPostParams as $param) {
    if (!isset($_POST[$param])) {
        die($param . ' POST param is required');
    }
}

// Get signature
$input = @file_get_contents('php://input');
if ($input === false) {
    die('Error reading POST data');
}

// Ensure STDIN data is not blank
if ($input == '') {
    die('No POST data received');
}

// Ensure HTTP_HMAC
if (!array_key_exists('HTTP_HMAC', $_SERVER)) {
    die('HMAC signature is missing');
}

// Ensure HTTP_HMAC is not blank
if ($_SERVER['HTTP_HMAC'] == '') {
    die('HMAC signature is missing');
}

// Get gateway module name using this filename
$gatewayModuleName = basename(__FILE__, '.php');

// Get gateway module config variables
$gatewayModuleVars = getGatewayVariables($gatewayModuleName);

// Ensure module is active
if (!$gatewayModuleVars['type']) {
    die($gatewayModuleName . ' is not active');
}

// Ensure merchant matches
if ($_POST['merchant'] != $gatewayModuleVars['merchantId']) {
    die('Merchant does not match');
}

// Ensure ipn_type POST value is valid
if ($_POST['ipn_type'] != 'button' && $_POST['ipn_type'] != 'simple') {
    die('Unsupported ipn_type');
}

// Ensure ipn_mode POST value is valid
if ($_POST['ipn_mode'] != 'hmac') {
    die('Unsupported ipn_mode');
}

// Check signature
$hmac = hash_hmac('sha512', $input, trim($gatewayModuleVars['ipnSecret']));
if ($hmac != $_SERVER['HTTP_HMAC']) {
    die('HMAC signature does not match');
}

// Ensure invoice id was passed
if ($_POST['invoice'] == '') {
    die('POST param invoice is blank');
}

// Lookup invoice.
$invoice = Invoice::find($_POST['invoice']);
if (is_null($invoice)) {
    die('no invoice found');
}

// Ensure payment currency matches invoice currency!
if ($invoice->getCurrencyCodeAttribute() != $_POST['currency1']) {
    die('Payment currency does not match invoice currency');
}

// Ensure received transaction id is not blank
if ($_POST['txn_id'] == '') {
    die('POST param txn_id is blank');
}

// Look for duplicate transaction id
$transaction = Transaction::find($_POST['txn_id']);
if (!is_null($transaction)) {
    die('Transaction already exists');
}

// Payment Cancelled or Timed Out
if ($_POST['status'] == '-1' && $invoice->getBalanceAttribute()) {

    // Gateway Log
    logTransaction($gatewayModuleName, $_POST, $_POST['status_text']);

    // Set invoice status to pending if invoice has a balance
    if ($invoice->getBalanceAttribute()) {
        $invoice->status = 'Unpaid';
        $invoice->save();
    }

    // Stop execution
    die();
}

// Payment Refund or Reversal
if ($_POST['status'] == -2) {
    
    // Gateway log
    logTransaction($gatewayModuleName, $_POST, $_POST['status_text']);

    // We need to handle the refund/reversal here

    // Stop execution
    die();
}

// Payment process started or a payment is waiting to be confirmed
if ($_POST['status'] == 0) {

    // If invoice has a balance, set status to 'Payment Pending'
    if ($invoice->getBalanceAttribute()) {
        $invoice->status = 'Payment Pending';
        $invoice->save();
    }
    
    // If received amount is greater than 0 then payment was received, otherwise it's waiting to be confirmed
    if ($_POST['received_amount'] > 0 ) {
        logTransaction($gatewayModuleName, $_POST, 'Payment Received');
    } else {
        logTransaction($gatewayModuleName, $_POST, 'Payment Pending');
    }

    // Stop execution
    die();
}

// Payment Confirmed
if ($_POST['status'] == 1) {

    // Gateway log
    logTransaction($gatewayModuleName, $_POST, 'Payment Confirmed');

    // Stop execution
    die();
}

// Payment completed
if ($_POST['status'] >= 100 || $_POST['status'] == 2) {

    // Gateway Log
    logTransaction($gatewayModuleName, $_POST, 'Payment Completed');

    // Add Transaction
    $invoice->addPaymentIfNotExists($_POST['amount1'], $_POST['txn_id'], 0, $gatewayModuleName);

    // Stop execution
    die();
}
