<?php

namespace duell;

class Duell {

  private $url = 'http://panteon-kasse.devhost/api/v1/';
  private $loginAction = 'getaccesstokens';
  private $registry;
  private $logger;
  private $max_log_size = 50; //max log size in Mb
  private $keyName = 'duell_integration';
  private $settings = array();
  private $limit = 20;
  private $totalLoginAttempt = 3;
  private $cnt = 0;

  public function __construct($registry) {
    $this->registry = $registry;
    $this->lasterror = '';
    $this->lastmsg = '';
    $this->logging = 1;

    if ($this->logging == 1) {
      $this->setLogger();
    }
    $this->settings = $this->getSetting($this->keyName);
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
      $this->log('Returning ' . $product_model . ' - getDuellItemByModel()');
      return $qry->row;
    } else {
      $this->log('No product model found - getDuellItemByModel() ' . $product_model);
      return false;
    }
  }

  public function updateDuellItemByItemId($product_id, $qty = 0) {
    $this->log('updateDuellItemByItemId() - Product Id: ' . $product_id . ' Qty: ' . $qty);

    $this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = '" . $qty . "' WHERE `product_id` = '" . $this->db->escape($product_id) . "'");
  }

  public function processStockUpdation($allData = array()) {
    try {
      if (!empty($allData)) {
        foreach ($allData as $val) {

          $productNumber = isset($val['productNumber']) ? $val['productNumber'] : '';
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

  public function callDuellStockSync() {

    $response = array();

    $response['status'] = FALSE;
    $response['message'] = 'Webservice is temporary unavailable. Please try again.';

    try {

      if (isset($this->settings['duell_integration_status']) && $this->settings['duell_integration_status'] == 1) {

        if (!isset($this->settings['duell_integration_client_number']) || (int) $this->settings['duell_integration_client_number'] <= 0) {
          $this->log('callDuellStockSync() - Duell client number is not set');
          $response['message'] = 'Duell client number is not set';
          return $response;
        }

        if (!isset($this->settings['duell_integration_client_token']) || strlen($this->settings['duell_integration_client_token']) <= 0) {
          $this->log('callDuellStockSync() - Duell client token is not set');
          $response['message'] = 'Duell client token is not set';
          return $response;
        }

        if (!isset($this->settings['duell_integration_department_token']) || strlen($this->settings['duell_integration_department_token']) <= 0) {
          $this->log('callDuellStockSync() - Duell department token is not set');
          $response['message'] = 'Duell department token is not set';
          return $response;
        }




        $start = 0;
        $limit = $this->limit;

        $apiData = array('client_number' => (int) $this->settings['duell_integration_client_number'], 'client_token' => $this->settings['duell_integration_client_token'], 'department_token' => $this->settings['duell_integration_department_token'], 'length' => $limit, 'start' => $start);

        $wsdata = $this->call('all/product/stock', 'get', $apiData);

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

                $wsdata = $this->call('all/product/stock', 'get', $apiData);

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
          $this->log('callDuellStockSync() - Error:: ' . $wsdata['message']);
          $response['message'] = $wsdata['message'];
        }
      } else {
        $this->log('callDuellStockSync() - Duell status is not active');
        $response['message'] = 'Duell status is not active';
        return $response;
      }
    } catch (Exception $e) {

      $this->log('callDuellStockSync() - Catch exception throw:: ' . $e->getMessage());
    }
    return $response;
  }

  public function callDuellStockUpdate($orderProductData = array()) {
    $this->log('callDuellStockUpdate() - Data:: ' . json_encode($orderProductData));

    if (!empty($orderProductData)) {
      try {

        if (isset($this->settings['duell_integration_status']) && $this->settings['duell_integration_status'] == 1) {

          if (!isset($this->settings['duell_integration_client_number']) || (int) $this->settings['duell_integration_client_number'] <= 0) {
            $this->log('callDuellStockUpdate() - Duell client number is not set');
            return true;
          }
          if (!isset($this->settings['duell_integration_client_token']) || strlen($this->settings['duell_integration_client_token']) <= 0) {
            $this->log('callDuellStockUpdate() - Duell client token is not set');
            return true;
          }

          if (!isset($this->settings['duell_integration_department_token']) || strlen($this->settings['duell_integration_department_token']) <= 0) {
            $this->log('callDuellStockUpdate() - Duell department token is not set');
            return true;
          }

          $product_data = array();

          foreach ($orderProductData as $val) {
            $product_data[] = array('product_number' => $val['model'], 'quantity' => $val['quantity']);
          }

          $apiData = array('client_number' => (int) $this->settings['duell_integration_client_number'], 'client_token' => $this->settings['duell_integration_client_token'], 'department_token' => $this->settings['duell_integration_department_token'], 'product_data' => $product_data);

          $wsdata = $this->call('updates/products/stocks', 'post', $apiData);

          if ($wsdata['status'] === true) {
            $this->log('callDuellStockUpdate() - Success:: ' . $wsdata['message']);
          } else {
            $this->log('callDuellStockUpdate() - Error:: ' . $wsdata['message']);
          }
          return true;
        } else {
          $this->log('callDuellStockUpdate() - Duell status is not active');
        }
      } catch (Exception $e) {
        $this->log('callDuellStockUpdate() - Catch exception throw:: ' . $e->getMessage());
      }
    } else {
      $this->log('callDuellStockUpdate() - Order product data is empty');
    }

    return true;
  }

  public function loginApi($action, $method = 'POST', $data = array(), $content_type = 'json') {
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

      $this->log('loginApi() - Result of : "' . $result . '"');

      if (!$result) {
        $this->log('loginApi() - Curl Failed ' . curl_error($curl) . ' ' . curl_errno($curl));
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

            $this->log('loginApi() - Result Failed ' . $result_code . ' ' . $result_message);
          }
        }
      }
    } catch (Error $e) {
      $res['code'] = 100010;
      $res['status'] = FALSE;
      $res['token'] = '';
      $res['message'] = $e->getMessage();
      $this->log('loginApi() - Error exception throw:: ' . $e->getMessage());
    } catch (Exception $e) {
      $res['code'] = 100010;
      $res['status'] = FALSE;
      $res['token'] = '';
      $res['message'] = $e->getMessage();
      $this->log('loginApi() - Catch exception throw:: ' . $e->getMessage());
    }

    return $res;
  }

  public function call($action, $method = 'POST', $data = array(), $content_type = 'json') {

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

          $this->log('call(' . $action . ') - login Attempt: ' . $loginAttempt);
          $tokenData = $this->loginApi($this->loginAction, 'POST', $requestedData, $content_type);

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
        $res['message'] = 'Not able to login with given crediential. Please check your settings.';
        $this->log('call() - Not able to login with given crediential. Please check your settings.');
        return $res;
      }

      /* For testing purpose
        if ($this->cnt == 0) {
        $token = "";
        $this->cnt++;
        } */

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
        $this->log('call() - Curl Failed ' . curl_error($curl) . ' ' . curl_errno($curl));
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
              return $this->call($action, $method, $requestedData, $content_type);
            }
          }
        }
      }
    } catch (Error $e) {
      $res['code'] = 100010;
      $res['status'] = FALSE;
      $res['message'] = $e->getMessage();
      $this->log('call() - Error exception throw:: ' . $e->getMessage());
    } catch (Exception $e) {
      $res['code'] = 100010;
      $res['status'] = FALSE;
      $res['message'] = $e->getMessage();
      $this->log('call() - Catch exception throw:: ' . $e->getMessage());
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
    if (file_exists(DIR_LOGS . 'duell.log')) {
      if (filesize(DIR_LOGS . 'duell.log') > ($this->max_log_size * 1000000)) {
        rename(DIR_LOGS . 'duell.log', DIR_LOGS . '_duell_' . date('Y-m-d_H-i-s') . '.log');
      }
    }

    $this->logger = new \Log('duell.log');
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

}
