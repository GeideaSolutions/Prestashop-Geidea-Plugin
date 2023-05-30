<?php

function sendGiRequest($gatewayUrl, $merchantKey, $password, $values, $method = 'POST')
{
    $origString = $merchantKey . ":" . $password;
    $authKey = base64_encode($origString);
    $postParams = $values;

    $headers = array(
        'Authorization: Basic ' . $authKey,
        'Content-Type: application/json',
        'Accept: application/json',
    );
    $curl = curl_init($gatewayUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postParams));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}


header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Retrieve the merchantApiPassword from session
$merchantApiPassword = isset($_SESSION['merchantApiPassword']) ? $_SESSION['merchantApiPassword'] : '';

$iframeConfiguration = array(
    'merchantPublicKey' => $data['merchantKey'],
    'apiPassword' =>  $merchantApiPassword,
    'callbackUrl' => $data['callbackUrl'],
    'amount' =>  $data['amount'],
    'currency' => $data['currency'],
    'merchantReferenceId' => ($data['merchantReferenceId'] === '') ? null : $data['merchantReferenceId'],
    'initiatedBy' => 'Internet',
    'customer' => array(
        'create' => false,
        'setDefaultMethod' => false,
        'email' => ($data['email'] === '') ? null : $data['email'],
        'phoneNumber' => ($data['phoneNumber'] === '') ? null : $data['phoneNumber'],
        'address' => array(
            'billing' => json_decode(str_replace('&quot;', '"', $data['billingAddress']), true),
            'shipping' => json_decode(str_replace('&quot;', '"', $data['shippingAddress']), true),
        ),
    ),
    'appearance' => array(
        'merchant' => array(
            'logoUrl' => ($data['merchantLogo'] === $data['uploadDir']) ? null : $data['merchantLogo'],
        ),
        'showAddress' => $data['addressEnabled'],
        'showEmail' => $data['showEmail'],
        'showPhone' => $data['showPhone'],
        'receiptPage' => $data['receiptEnabled'],
        'styles' => array(
            'hideGeideaLogo' => $data['hideLogoEnabled'],
            'headerColor' => ($data['headerColor'] === '') ? null : $data['headerColor'],
           
            'hppProfile' => $data['hppProfile'],
          ),
          ),
          'order' => array(
          'integrationType' => $data['IntegrationType'],
          ),
          'platform' => array(
          'name' => $data['name'],
          'pluginVersion' => $data['pluginVersion'],
          'partnerId' => $data['partnerId'],
          ),
          );
          
          $iframeConfigurationJson = $iframeConfiguration;          
          $response = sendGiRequest(
          'https://api.merchant.geidea.net/payment-intent/api/v1/direct/session',
          $data['merchantKey'],
          $merchantApiPassword,
          $iframeConfigurationJson
          );
          
          $responseBody = array();
          
          if ($response === false) {
          // Error occurred while making the request
          $responseBody['error'] = 'Failed to connect to the gateway.';
          } else {
          $responseArray = json_decode($response, true);
          if ($responseArray && isset($responseArray['session'])) {
            $responseBody = $responseArray;
            $responseBody['returnUrl'] = $data['returnUrl'];
            $responseBody['cancelUrl'] = $data['cancelUrl'];
        } else {
            // Error occurred in the response
            $responseBody['error'] = 'Invalid response from the gateway.';
        }
      }

      echo json_encode($response);
      ?>        