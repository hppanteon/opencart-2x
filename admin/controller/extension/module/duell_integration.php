<?php

class ControllerExtensionModuleDuellIntegration extends Controller {

  private $error = array();

  public function index() {
    /* Load language file. */
    $this->load->language('extension/module/duell_integration');

    $this->document->setTitle($this->language->get('heading_title'));

    $this->load->model('setting/setting');

    /* Check if data has been posted back. */
    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
      $this->model_setting_setting->editSetting('duell_integration', $this->request->post);

      $this->session->data['success'] = $this->language->get('text_success');

      $this->cache->delete('duell_integration');

      $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true));
    }

    /* Load language strings. */
    $data['text_edit'] = $this->language->get('text_edit');

    $data['text_about'] = $this->language->get('text_about');
    $data['text_module'] = $this->language->get('text_module');
    $data['text_enabled'] = $this->language->get('text_enabled');
    $data['text_disabled'] = $this->language->get('text_disabled');

    $data['heading_title'] = $this->language->get('heading_title');

    $data['entry_title'] = $this->language->get('entry_title');
    $data['entry_text'] = $this->language->get('entry_text');
    $data['entry_status'] = $this->language->get('entry_status');

    $data['button_save'] = $this->language->get('button_save');
    $data['button_cancel'] = $this->language->get('button_cancel');


    $data['text_duell_integration_client_number'] = $this->language->get('text_duell_integration_client_number');
    $data['text_duell_integration_client_token'] = $this->language->get('text_duell_integration_client_token');
    $data['text_duell_integration_department_token'] = $this->language->get('text_duell_integration_department_token');


    $data['help_text_duell_integration_client_number'] = $this->language->get('help_text_duell_integration_client_number');
    $data['help_text_duell_integration_client_token'] = $this->language->get('help_text_duell_integration_client_token');
    $data['help_text_duell_integration_department_token'] = $this->language->get('help_text_duell_integration_department_token');

    $data['text_duell_integration_manual_sync'] = $this->language->get('text_duell_integration_manual_sync');

    $data['text_user_guide'] = $this->language->get('text_user_guide');
    $data['text_cron_steup_title_curl'] = $this->language->get('text_cron_steup_title_curl');
    $data['text_every_hours'] = $this->language->get('text_every_hours');
    $data['text_every_night'] = $this->language->get('text_every_night');

    /* Present error messages to users. */
    if (isset($this->error['warning'])) {
      $data['error_warning'] = $this->error['warning'];
    } else {
      $data['error_warning'] = '';
    }

    if (isset($this->error['duell_integration_client_number'])) {
      $data['error_duell_integration_client_number'] = $this->error['duell_integration_client_number'];
    } else {
      $data['error_duell_integration_client_number'] = '';
    }


    if (isset($this->error['duell_integration_client_token'])) {
      $data['error_duell_integration_client_token'] = $this->error['duell_integration_client_token'];
    } else {
      $data['error_duell_integration_client_token'] = '';
    }

    if (isset($this->error['duell_integration_department_token'])) {
      $data['error_duell_integration_department_token'] = $this->error['duell_integration_department_token'];
    } else {
      $data['error_duell_integration_department_token'] = '';
    }

    /* Breadcrumb. */
    $data['breadcrumbs'][] = array(
        'text' => $this->language->get('text_home'),
        'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true),
    );

    $data['breadcrumbs'][] = array(
        'text' => $this->language->get('text_extension'),
        'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true),
    );

    $data['breadcrumbs'][] = array(
        'text' => $this->language->get('heading_title'),
        'href' => $this->url->link('extension/module/duell_integration', 'token=' . $this->session->data['token'], true)
    );

    $data['action'] = $this->url->link('extension/module/duell_integration', 'token=' . $this->session->data['token'], true);

    $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true);

    $data['cron_link'] = HTTP_CATALOG . 'system/duellcron.php';

    $data['token'] = $this->session->data['token'];


    /* Initial values for form. */
    if (isset($this->request->post['duell_integration_client_number'])) {
      $data['duell_integration_client_number'] = $this->request->post['duell_integration_client_number'];
    } else {
      $data['duell_integration_client_number'] = $this->config->get('duell_integration_client_number');
    }

    if (isset($this->request->post['duell_integration_client_token'])) {
      $data['duell_integration_client_token'] = $this->request->post['duell_integration_client_token'];
    } else {
      $data['duell_integration_client_token'] = $this->config->get('duell_integration_client_token');
    }

    if (isset($this->request->post['duell_integration_department_token'])) {
      $data['duell_integration_department_token'] = $this->request->post['duell_integration_department_token'];
    } else {
      $data['duell_integration_department_token'] = $this->config->get('duell_integration_department_token');
    }





    if (isset($this->request->post['duell_integration_status'])) {
      $data['duell_integration_status'] = $this->request->post['duell_integration_status'];
    } else {
      $data['duell_integration_status'] = $this->config->get('duell_integration_status');
    }

    /* Render some output. */
    $data['header'] = $this->load->controller('common/header');
    $data['column_left'] = $this->load->controller('common/column_left');
    $data['footer'] = $this->load->controller('common/footer');

    $this->response->setOutput($this->load->view('extension/module/duell_integration', $data));
  }

  /* Check user input. */

  protected function validate() {
    if (!$this->user->hasPermission('modify', 'extension/module/duell_integration')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }

    if (!$this->request->post['duell_integration_client_number'] || strlen($this->request->post['duell_integration_client_number']) <= 5) {
      $this->error['duell_integration_client_number'] = $this->language->get('error_duell_integration_client_number');
    }

    if (!$this->request->post['duell_integration_client_token'] || strlen($this->request->post['duell_integration_client_token']) <= 0) {
      $this->error['duell_integration_client_token'] = $this->language->get('error_duell_integration_client_token');
    }

    if (!$this->request->post['duell_integration_department_token'] || strlen($this->request->post['duell_integration_department_token']) <= 0) {
      $this->error['duell_integration_department_token'] = $this->language->get('error_duell_integration_department_token');
    }

    return !$this->error;
  }

  public function manualsync() {
    $json = array();

    $json['status'] = false;
    $json['message'] = '';

    $this->load->language('extension/module/duell_integration');

    $this->load->library('duell/duell');


    $result = $this->duell->callDuellStockSync();


    if ($result['status'] == true) {
      $status = true;
      $json['message'] = $this->language->get('sync_success');
    } else {
      $json['message'] = $result['message'];
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
  }

}
