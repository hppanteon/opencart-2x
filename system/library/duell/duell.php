<?php

namespace duell;

class Duell {

    private $url = 'https://duell.kasseservice.no/api/v1/';
    private $loginAction = 'getaccesstokens';
    private $registry;
    private $logger;
    private $max_log_size = 50; //max log size in Mb
    private $keyName = 'duell_integration';
    private $settings = array();
    private $limit = 20;
    private $totalLoginAttempt = 3;
    private $cnt = 0;
    private $_duellLang = array();

    public function __construct($registry) {
        $this->registry = $registry;
        $this->lasterror = '';
        $this->lastmsg = '';


        $this->settings = $this->getSetting($this->keyName);

        $this->logging = 1;
        if (isset($this->settings['duell_integration_log_status']) && $this->settings['duell_integration_log_status'] == 0) {
            $this->logging = 0;
        }

        if ($this->logging == 1) {
            $this->setLogger();
        }


        require_once 'duell_mail_lang.php';

        $this->_duellLang = $duellLang;
    }

    public function __get($name) {
        return $this->registry->get($name);
    }

    public function getSetting($key) {
        $qry = $this->db->query("SELECT `key`,`value` FROM `" . DB_PREFIX . "setting` WHERE `code` = '" . $this->db->escape($key) . "' ");
        if ($qry->num_rows > 0) {
            foreach ($qry->rows as $val) {
                $data[$val['key']] = $val['value'];
            }
            return $data;
        } else {
            return false;
        }
    }

    public function getDuellItemByModel($product_model) {
        $this->log('getDuellItemByModel() - Product Model: ' . $product_model);

        $qry = $this->db->query("SELECT `product_id`,`quantity`,`model`, `status` FROM `" . DB_PREFIX . "product` WHERE `model` = '" . $product_model . "' LIMIT 1");

        if ($qry->num_rows > 0) {
            //$this->log('Returning ' . $product_model . ' - getDuellItemByModel()');
            return $qry->row;
        } else {
            $this->log('No product model found - getDuellItemByModel() ' . $product_model);
            return false;
        }
    }

    public function updateDuellItemByItemId($product_id, $qty = 0) {
        $this->log('updateDuellItemByItemId() - New Stock Product Id: ' . $product_id . ' Qty: ' . $qty);

        $this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = '" . $qty . "' WHERE `product_id` = '" . $this->db->escape($product_id) . "'");
    }

    public function processStockUpdation($allData = array()) {
        try {
            if (!empty($allData)) {
                foreach ($allData as $val) {

                    //$this->log('processStockUpdation() - API data: ' . json_encode($val));

                    $productNumber = isset($val['product_number']) ? $val['product_number'] : '';
                    $stock = isset($val['stock']) ? $val['stock'] : 0;

                    if ($productNumber != '') {
                        $getProductData = $this->getDuellItemByModel($productNumber);

                        if (!empty($getProductData)) {
                            $this->log('processStockUpdation() Before updating stock - Product Id: ' . $getProductData['product_id'] . ' Qty: ' . $getProductData['quantity']);
                            $this->updateDuellItemByItemId($getProductData['product_id'], $stock);
                        }
                    }
                }
            }
        } catch (Exception $e) {

            $this->log('processStockUpdation() - Catch exception throw:: ' . $e->getMessage());
        }
    }

    public function callDuellStockSync($type = "manual") {

        $type = strtolower($type);

        $response = array();

        $response['status'] = FALSE;
        $response['message'] = 'Webservice is temporary unavailable. Please try again.';

        try {

            if (isset($this->settings['duell_integration_status']) && $this->settings['duell_integration_status'] == 1) {

                if (!isset($this->settings['duell_integration_client_number']) || (int) $this->settings['duell_integration_client_number'] <= 0) {
                    $text_error = $this->_duellLang['duell_client_number_not_set'];

                    $this->log('callDuellStockSync() - ' . $text_error);
                    $response['message'] = $text_error;

                    $error_message = 'callDuellStockSync() - ' . $text_error;

                    if ($type != 'manual') {
                        //$this->duellMailAlert($text_error, 422);
                    }
                    return $response;
                }

                if (!isset($this->settings['duell_integration_client_token']) || strlen($this->settings['duell_integration_client_token']) <= 0) {

                    $text_error = $this->_duellLang['duell_client_token_not_set'];

                    $this->log('callDuellStockSync() - ' . $text_error);
                    $response['message'] = $text_error;

                    $error_message = 'callDuellStockSync() - ' . $text_error;

                    if ($type != 'manual') {
                        //$this->duellMailAlert($text_error, 422);
                    }


                    return $response;
                }

                if (!isset($this->settings['duell_integration_department_token']) || strlen($this->settings['duell_integration_department_token']) <= 0) {

                    $text_error = $this->_duellLang['duell_department_token_not_set'];

                    $this->log('callDuellStockSync() - ' . $text_error);
                    $response['message'] = $text_error;

                    $error_message = 'callDuellStockSync() - ' . $text_error;

                    if ($type != 'manual') {
                        //$this->duellMailAlert($text_error, 422);
                    }


                    return $response;
                }




                $start = 0;
                $limit = $this->limit;

                $apiData = array('client_number' => (int) $this->settings['duell_integration_client_number'], 'client_token' => $this->settings['duell_integration_client_token'], 'department_token' => $this->settings['duell_integration_department_token'], 'length' => $limit, 'start' => $start);

                $wsdata = $this->call('all/product/stock', 'get', $apiData, 'json', $type);

                if ($wsdata['status'] === true) {

                    $totalRecord = $wsdata['total_count'];
                    if ($totalRecord > 0) {

                        if (isset($wsdata['data']) && !empty($wsdata['data'])) {
                            $allData = $wsdata['data'];

                            $this->processStockUpdation($allData);
                            sleep(20);

                            $nextCounter = $start + $limit;

                            while ($totalRecord > $limit && $totalRecord > $nextCounter) {

                                $apiData = array('client_number' => (int) $this->settings['duell_integration_client_number'], 'client_token' => $this->settings['duell_integration_client_token'], 'department_token' => $this->settings['duell_integration_department_token'], 'length' => $limit, 'start' => $nextCounter);

                                $wsdata = $this->call('all/product/stock', 'get', $apiData, 'json', $type);

                                if ($wsdata['status'] === true) {
                                    $totalNRecord = $wsdata['total_count'];
                                    if ($totalNRecord > 0) {

                                        if (isset($wsdata['data']) && !empty($wsdata['data'])) {
                                            $allData = $wsdata['data'];
                                            $this->processStockUpdation($allData);
                                        }
                                    }
                                    $nextCounter = $nextCounter + $limit;
                                }
                                sleep(20);
                            }
                        }
                    }

                    $response['status'] = TRUE;
                    $response['message'] = 'success';

                    return $response;
                } else {
                    $text_error = $wsdata['message'];
                    $this->log('callDuellStockSync() - Error:: ' . $text_error);
                    $response['message'] = $text_error;
                    if ($type != 'manual') {
                        //$this->duellMailAlert($text_error, 422);
                    }
                }
            } else {
                $text_error = $this->_duellLang['duell_status_is_not_active'];
                $this->log('callDuellStockSync() - ' . $text_error);
                $response['message'] = $text_error;
                if ($type != 'manual') {
                    $this->duellMailAlert($text_error, 422);
                }
                return $response;
            }
        } catch (Exception $e) {

            $text_error = 'Catch exception throw:: ' . $e->getMessage();

            $this->log('callDuellStockSync() - ');
            if ($type != 'manual') {
                $this->duellMailAlert($text_error, 422);
            }
        }
        return $response;
    }

    public function callDuellStockUpdate($orderProductData = array()) {
        $this->log('callDuellStockUpdate() - Data:: ' . json_encode($orderProductData));

        if (!empty($orderProductData)) {
            try {

                if (isset($this->settings['duell_integration_status']) && $this->settings['duell_integration_status'] == 1) {

                    if (!isset($this->settings['duell_integration_client_number']) || (int) $this->settings['duell_integration_client_number'] <= 0) {
                        $text_error = $this->_duellLang['duell_client_number_not_set'];
                        $this->log('callDuellStockUpdate() - ' . $text_error);
                        $this->duellMailAlert($text_error, 422);
                        return true;
                    }
                    if (!isset($this->settings['duell_integration_client_token']) || strlen($this->settings['duell_integration_client_token']) <= 0) {
                        $text_error = $this->_duellLang['duell_client_token_not_set'];
                        $this->log('callDuellStockUpdate() - ' . $text_error);
                        $this->duellMailAlert($text_error, 422);
                        return true;
                    }

                    if (!isset($this->settings['duell_integration_department_token']) || strlen($this->settings['duell_integration_department_token']) <= 0) {
                        $text_error = $this->_duellLang['duell_department_token_not_set'];
                        $this->log('callDuellStockUpdate() - ' . $text_error);
                        $this->duellMailAlert($text_error, 422);
                        return true;
                    }

                    $product_data = array();

                    foreach ($orderProductData as $val) {

                        $productId = $val['product_id'];

                        $qry = $this->db->query("SELECT `subtract` FROM `" . DB_PREFIX . "product` WHERE `product_id` = '" . $productId . "' LIMIT 1");

                        if ($qry->num_rows > 0) {

                            $productRow = $qry->row;
                            if ($productRow['subtract'] == 1) {
                                $product_data[] = array('product_number' => $val['model'], 'quantity' => $val['quantity']);
                            }
                        }
                    }
                    if (!empty($product_data)) {
                        $apiData = array('client_number' => (int) $this->settings['duell_integration_client_number'], 'client_token' => $this->settings['duell_integration_client_token'], 'department_token' => $this->settings['duell_integration_department_token'], 'product_data' => $product_data);

                        $wsdata = $this->call('updates/products/stocks', 'post', $apiData);


                        $text_error = $wsdata['message'];

                        if ($wsdata['status'] === true) {
                            //$this->log('callDuellStockUpdate() - Success:: ' . $text_error);
                        } else {
                            $this->log('callDuellStockUpdate() - Error:: ' . $text_error);
                            $this->duellMailAlert('callDuellStockUpdate() - Error:: ' . $text_error, 422);
                        }
                    }
                    return true;
                } else {
                    $text_error = $this->_duellLang['duell_status_is_not_active'];
                    $this->log('callDuellStockUpdate() - ' . $text_error);
                }
            } catch (Exception $e) {
                $text_error = 'Catch exception throw:: ' . $e->getMessage();
                $this->log('callDuellStockUpdate() - ' . $text_error);
                $this->duellMailAlert($text_error, 422);
            }
        } else {
            //$this->log('callDuellStockUpdate() - Order product data is empty');
        }

        return true;
    }

    public function loginApi($action, $method = 'POST', $data = array(), $content_type = 'json', $type = 'manual') {
        try {

            $method = strtoupper($method);

            $this->log('loginApi(' . $action . ') - Data: ' . json_encode($data));

            $url = $this->url . $action;



            $headers = array();
            //$headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';


            $curl = curl_init();

            switch ($method) {
                case "POST":
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    if (!empty($data)) {
                        curl_setopt($curl, CURLOPT_POST, count($data));
                        $data = http_build_query($data);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                    }
                    break;
                case "PUT":
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                    curl_setopt($curl, CURLOPT_PUT, 1);

                    if (!empty($data)) {
                        $url = sprintf("%s?%s", $url, http_build_query($data));
                    }
                    break;
                default:
                    if (!empty($data)) {
                        $url = sprintf("%s?%s", $url, http_build_query($data));
                    }
            }



            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_USERAGENT, "Duell Integration OP");

            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($curl, CURLOPT_FORBID_REUSE, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);


            $result = curl_exec($curl);

            //$this->log('loginApi() - Result of : "' . $result . '"');

            if (!$result) {
                $text_error = 'loginApi() - Curl Failed ' . curl_error($curl);
                $this->log($text_error . ' ' . curl_errno($curl));
                if ($type != 'manual') {
                    $this->duellMailAlert($text_error, curl_errno($curl));
                }
            }
            curl_close($curl);





            if ($content_type == 'json') {
                $encoding = mb_detect_encoding($result);

                if ($encoding == 'UTF-8') {
                    $result = preg_replace('/[^(\x20-\x7F)]*/', '', $result);
                }

                $res = json_decode($result, true);



                if (empty($res)) {

                    $res['code'] = 100010;
                    $res['status'] = FALSE;
                    $res['token'] = '';
                    $res['message'] = 'Webservice is temporary unavailable. Please try again.';
                    $this->log('loginApi() - Result json_decode is not proper');
                } else {
                    if ($res['status'] === true) {

                    } else {
                        $result_code = '';
                        if (isset($res['code']) && $res['code'] != '') {
                            $result_code = $res['code'];
                        }

                        $result_message = '';
                        if (isset($res['message']) && $res['message'] != '') {
                            $result_message = $res['message'];
                        }

                        $text_error = 'loginApi() - Result Failed - ' . $result_message;

                        $this->log('loginApi() - Result Failed ' . $result_code . ' ' . $result_message);

                        if ($type != 'manual') {
                            //$this->duellMailAlert($text_error, $result_code);
                        }
                    }
                }
            }
        } catch (Error $e) {
            $res['code'] = 100010;
            $res['status'] = FALSE;
            $res['token'] = '';
            $res['message'] = $e->getMessage();

            $text_error = 'loginApi() - Error exception throw:: ' . $e->getMessage();

            $this->log($text_error);
            if ($type != 'manual') {
                $this->duellMailAlert($text_error, 422);
            }
        } catch (Exception $e) {
            $res['code'] = 100010;
            $res['status'] = FALSE;
            $res['token'] = '';
            $res['message'] = $e->getMessage();
            $text_error = 'loginApi() - Catch exception throw:: ' . $e->getMessage();
            $this->log($text_error);
            if ($type != 'manual') {
                $this->duellMailAlert($text_error, 422);
            }
        }

        return $res;
    }

    public function call($action, $method = 'POST', $data = array(), $content_type = 'json', $type = 'manual') {

        try {

            $requestedData = $data;

            $method = strtoupper($method);

            $this->log('call(' . $action . ') - Data: ' . json_encode($data));

            $url = $this->url . $action;


            $token = '';

            if (isset($_COOKIE[$this->keyName]) && !empty($_COOKIE[$this->keyName])) {
                $token = $_COOKIE[$this->keyName];
            } else {

                $loginAttempt = 1;
                while ($loginAttempt <= $this->totalLoginAttempt) {

                    //$this->log('call(' . $action . ') - login Attempt: ' . $loginAttempt);
                    $tokenData = $this->loginApi($this->loginAction, 'POST', $requestedData, $content_type, $type);

                    if ($tokenData['status'] == true) {
                        //==save in session or cookie
                        $token = $tokenData['token'];
                        if ($token != '') {
                            setcookie($this->keyName, $token, time() + (86400 * 30), "/"); // 86400 = 1 day
                            break;
                        }
                    }
                    $loginAttempt++;
                }
            }

            if ($token == '') {
                $res['code'] = 100010;
                $res['status'] = FALSE;
                $text_error = $this->_duellLang['login_credential_wrong'];
                $res['message'] = $text_error;
                $this->log('call() - ' . $text_error);
                if ($type != 'manual') {
                    $this->duellMailAlert('call() - ' . $text_error, 100010);
                }
                return $res;
            }

            /* For testing purpose
              if ($this->cnt == 0) {
              $token = "";
              $this->cnt++;
              } */

            $headers = array();
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Authorization: Bearer ' . $token;

            $curl = curl_init();

            switch ($method) {
                case "POST":
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    if (!empty($data)) {
                        curl_setopt($curl, CURLOPT_POST, count($data));
                        $data = json_encode($data);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                    }
                    break;
                case "PUT":
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                    curl_setopt($curl, CURLOPT_PUT, 1);
//$data = json_encode($data);
//curl_setopt($curl, CURLOPT_POSTFIELDS,http_build_query($data));

                    if (!empty($data)) {
                        $url = sprintf("%s?%s", $url, http_build_query($data));
                    }
                    break;
                default:
                    if (!empty($data)) {
                        $url = sprintf("%s?%s", $url, http_build_query($data));
                    }
            }

            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_USERAGENT, "Duell Integration OP");

            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($curl, CURLOPT_FORBID_REUSE, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);


            $result = curl_exec($curl);

            $this->log('call() - Result of : "' . $result . '"');

            if (!$result) {

                $text_error = 'call() - Curl Failed ' . curl_error($curl);
                $this->log($text_error . ' ' . curl_errno($curl));
                if ($type != 'manual') {
                    $this->duellMailAlert($text_error, curl_errno($curl));
                }
            }
            curl_close($curl);





            if ($content_type == 'json') {
                $encoding = mb_detect_encoding($result);

                if ($encoding == 'UTF-8') {
                    $result = preg_replace('/[^(\x20-\x7F)]*/', '', $result);
                }

                $res = json_decode($result, true);

                if (empty($res)) {

                    $res['code'] = 100010;
                    $res['status'] = FALSE;
                    $res['message'] = 'Webservice is temporary unavailable. Please try again.';
                    $this->log('call() - Result json_decode is not proper');
                } else {
                    if ($res['status'] === true) {

                    } else {
                        $result_code = '';
                        if (isset($res['code']) && $res['code'] != '') {
                            $result_code = $res['code'];
                        }

                        $result_message = '';
                        if (isset($res['message']) && $res['message'] != '') {
                            $result_message = $res['message'];
                        }

                        $this->log('call() - Result Failed ' . $result_code . ' ' . $result_message);

                        if ((int) $result_code == 401 || (int) $result_code == 403) {
                            //==relogin
                            unset($_COOKIE[$this->keyName]);
                            return $this->call($action, $method, $requestedData, $content_type, $type);
                        } else {
                            if ($type != 'manual') {
                                $this->duellMailAlert('call(' . $action . ') - ' . $result_message, $result_code);
                            }
                        }
                    }
                }
            }
        } catch (Error $e) {
            $res['code'] = 100010;
            $res['status'] = FALSE;
            $res['message'] = $e->getMessage();
            $this->log('call() - Error exception throw:: ' . $e->getMessage());
            if ($type != 'manual') {
                $this->duellMailAlert('call(' . $action . ') - Error exception throw:: ' . $e->getMessage(), 100010);
            }
        } catch (Exception $e) {
            $res['code'] = 100010;
            $res['status'] = FALSE;
            $res['message'] = $e->getMessage();
            $this->log('call() - Catch exception throw:: ' . $e->getMessage());
            if ($type != 'manual') {
                $this->duellMailAlert('call(' . $action . ') - Catch exception throw::  ' . $e->getMessage(), 100010);
            }
        }

        return $res;
    }

    public function validateJsonDecode($data) {
        $data = (string) $data;

        $encoding = mb_detect_encoding($data);

        if ($encoding == 'UTF-8') {
            $data = preg_replace('/[^(\x20-\x7F)]*/', '', $data);
            $data = preg_replace('#\\\\x[0-9a-fA-F]{2,2}#', '', $data);
        }

        $data = json_decode($data);

        if (function_exists('json_last_error')) {
            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    $this->log('validateJsonDecode() - No json decode errors');
                    break;
                case JSON_ERROR_DEPTH:
                    $this->log('validateJsonDecode() - Maximum stack depth exceeded');
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $this->log('validateJsonDecode() - Underflow or the modes mismatch');
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $this->log('validateJsonDecode() - Unexpected control character found');
                    break;
                case JSON_ERROR_SYNTAX:
                    $this->log('validateJsonDecode() - Syntax error, malformed JSON');
                    break;
                case JSON_ERROR_UTF8:
                    $this->log('validateJsonDecode() - Malformed UTF-8 characters, possibly incorrectly encoded');
                    break;
                default:
                    $this->log('validateJsonDecode() - Unknown error');
                    break;
            }
        } else {
            $this->log('validateJsonDecode() - json_last_error PHP function does not exist');
        }

        return $data;
    }

    private function setLogger() {
        if (file_exists(DIR_LOGS . 'error.log')) {
            if (filesize(DIR_LOGS . 'error.log') > ($this->max_log_size * 1000000)) {
                rename(DIR_LOGS . 'error.log', DIR_LOGS . '_error_' . date('Y-m-d_H-i-s') . '.log');
            }
        }

        $this->logger = new \Log('error.log');
    }

    public function log($data, $write = true) {
        if ($this->logging == 1) {
            if (function_exists('getmypid')) {
                $process_id = getmypid();
                $data = $process_id . ' - ' . print_r($data, true);
            }
            if ($write == true) {
                $this->logger->write($data);
            }
        }
    }

    public function duellMailAlert($error_message = '', $error_code = '') {



        $subject = sprintf($this->_duellLang['mail_subject'], html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'), $error_code);
        $text = sprintf($this->_duellLang['mail_body'], $error_code, $error_message);


        $mail = new \Mail();
        $mail->protocol = $this->config->get('config_mail_protocol');
        $mail->parameter = $this->config->get('config_mail_parameter');
        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
        $mail->setTo($this->config->get('config_email'));
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender($this->config->get('config_email'));
        $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
        $mail->setText(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
        $mail->send();


        // Send to additional alert emails
        $emails = explode(',', $this->config->get('config_alert_emails'));

        foreach ($emails as $email) {
            if ($email && preg_match('/^[^\@]+@.*\.[a-z]{2,6}$/i', $email)) {
                $mail->setTo($email);
                $mail->send();
            }
        }
    }

}
