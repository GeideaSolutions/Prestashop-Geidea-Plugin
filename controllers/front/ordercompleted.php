<?php

class GeideaPayOrderCompletedModuleFrontController extends ModuleFrontController
{
    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
    }
    public function postProcess()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if(empty($data)) {
            echo json_encode("Invalid request!");
            http_response_code(400);
            die();
        }

        $logger = new FileLogger(0); //0 == debug level, logDebug() won’t work without this.
        $logger->setFilename(_PS_ROOT_DIR_ . '/geidea.log');
        $logger->logDebug('\n\ngeidea call back data is ' . json_encode($data));

        if (isset($data['order']['status']) 
        && isset($data['order']['merchantReferenceId'])
        && isset($data['order']['merchantPublicKey'])
        && isset($data['order']['amount'])
        && isset($data['order']['currency'])
        && isset($data['order']['orderId'])
        && isset($data['signature'])) {

            $orderId = $data['order']['orderId'];
            $merchantReferenceId = $data['order']['merchantReferenceId'];
            $status = $data['order']['status'];

            $merchantPublicKey = $data['order']['merchantPublicKey'];
            $amount = number_format($data['order']['amount']  , '2' , '.' , '');
            $currency = $data['order']['currency'];
            
            $sandbox = Configuration::get('GEIDEA_PAY_SANDBOX', false);
            $publicKey = $sandbox ? Configuration::get('SANDBOX_PUBLIC_KEY', '') : Configuration::get('LIVE_PUBLIC_KEY', '');
            $publicKey = trim($publicKey);
            $merchantApiPassword = $sandbox ? Configuration::get('SANDBOX_MERCHANT_API_PASSWORD', '') : Configuration::get('LIVE_MERCHANT_API_PASSWORD', '');
            $logger->logDebug('\n\nmerchantPublicKey:- ' . json_encode($merchantPublicKey));
            $logger->logDebug('\n\npublicKey:- ' . json_encode($publicKey));
            if($publicKey == $merchantPublicKey)
            {
                $recieved_signature = $data['signature'];
                $timeStamp = $data['timeStamp'];
                $sig_string = $merchantPublicKey.$amount.$currency.$orderId.$status.$merchantReferenceId.$timeStamp;
                $logger->logDebug('\n\nsig_string:- ' . json_encode($sig_string));
                $logger->logDebug('\n\nmerchantApiPassword:- ' . json_encode($merchantApiPassword));

                $computed_signature = hash_hmac('sha256', $sig_string, $merchantApiPassword, true);
                $computed_signature = base64_encode($computed_signature);
                $logger->logDebug('\n\nrecieved signature:- ' . json_encode($recieved_signature));
                $logger->logDebug('\n\ncomputed signature:- ' . json_encode($computed_signature));
    
                if($computed_signature == $recieved_signature)
                {
                    try{

                        if(isset($data['order']['transactions'])){
                            $processing_result='';
                            if(isset($data['order']['transactions'][0]))
                                $processing_result = $data['order']['transactions'][0]['codes']['detailedResponseMessage'];
                            if(isset($data['order']['transactions'][1]))
                                $processing_result .= '---' . $data['order']['transactions'][1]['codes']['detailedResponseMessage'];
                        } else {
                            $processing_result = 'Unknown';
                        }
                        $paymentStatus = strtoupper($status);
                        $logger->logDebug('\n\npaymentStatus:- ' . json_encode($paymentStatus));
                        //return
                        if ($paymentStatus == 'PAID' || $paymentStatus == 'SUCCESS') {
                            $this->updateOrderStatus($logger, $merchantReferenceId, $orderId, Configuration::get('GEIDEA_ACCEPTED_PAYMENT'), $processing_result);
                            echo json_encode("Order is completed!");
                            http_response_code(200);
                            die();
                            //FAIL CLOSE
                        }elseif ($paymentStatus == 'CANCELLED' || $paymentStatus == 'EXPIRED') {
                            $this->updateOrderStatus($logger, $merchantReferenceId, $orderId, Configuration::get('PS_OS_CANCELED'), $processing_result);
                            echo json_encode("Payment Expired or Cancelled!");
                            http_response_code(200);
                            die();
                        } elseif ($paymentStatus == 'FAILED') {
                            $this->updateOrderStatus($logger, $merchantReferenceId, $orderId, Configuration::get('PS_OS_ERROR'), $processing_result);
                            echo json_encode("Payment failed!");
                            http_response_code(200);
                            die();
                        }
                    }catch(Exception $e){
                        $params = array(
                            'action'=>'fail',
                            'err_code'=>$e->getCode(),
                            'err_msg'=>$e->getMessage()
                        );
                        ob_clean();
                        print json_encode($params);
                        http_response_code(404);
                        exit;
                    }
                }else{
                    echo json_encode("Invalid signature!");
                    http_response_code(400);
                    die();
                }
            }else{
                echo json_encode("Invalid merchantPublicKey!");
                http_response_code(400);
                die();
            }
        }else{
            echo json_encode("Order is not defined properly!");
            http_response_code(400);
            die();
        }
    }

    public function updateOrderStatus($logger, $id_order, $geideaOrderId, $status , $processing_result)
    {
        $logger->logDebug('\n\nid_order:- ' . json_encode($id_order));
        $logger->logDebug('\n\nstatus:- ' . json_encode($status));

        $order = new Order($id_order); 
        $order->setCurrentState($status);
        $order->note = "Geidea Order Id is " . $geideaOrderId . ' for Merchant reference ID: ' . $id_order . '. Payment Information: ' . $processing_result;
        $order->update();
        $logger->logDebug('\n\norder:- ' . json_encode($order));
        $history = new OrderHistory();
        $history->id_order = (int)$order->id;
        $history->changeIdOrderState($status, (int)($order->id));

        $orderMessage = new Message();
        $orderMessage->id_order = $id_order;
        $orderMessage->message = "Geidea Order Id is " . $geideaOrderId;
        $orderMessage->private = true;
        $orderMessage->save();

        $orderMessage = new Message();
        $orderMessage->id_order = $id_order;
        $orderMessage->message = "Merchant Reference Id is " . $id_order;
        $orderMessage->private = true;
        $orderMessage->save();

        $orderMessage = new Message();
        $orderMessage->id_order = $id_order;
        $orderMessage->message = "Payment Information: " . $processing_result;
        $orderMessage->private = true;
        $orderMessage->save();
    }
}
