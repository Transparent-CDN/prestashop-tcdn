<?php
if ( !defined( '_PS_VERSION_' ) )
  exit;
 
class Transparentcdn extends Module {
  
  public function __construct() {
    $this->name = 'transparentcdn';
    $this->tab = 'administration';
    $this->version = 1.0;
    $this->author = 'Transparent CDN';
    $this->need_instance = 0;
 
    parent::__construct();
 
    $this->displayName = $this->l( 'Transparent CDN' );
    $this->description = $this->l( 'This module contains the basic configuration to cache the prestashop website.' );
  }
 
  public function install(){
    return parent::install() && 
        $this->registerHook('actionCategoryUpdate') &&
        $this->registerHook('actionProductUpdate');
  }

  public function uninstall() {
  if ( !parent::uninstall() )
    Db::getInstance()->Execute( 'DELETE FROM `' . _DB_PREFIX_ . 'transparentcdn`' );
    parent::uninstall();
  }

  public function hookActionCategoryUpdate($params) {
    $id_category = (int)Tools::getValue('id_category');
    if (isset($id_category) && $id_category != '')
    {   
      $category = new Category($id_category);
      $urlCategory = $this->context->link->getCategoryLink($category);
      $response = $this->_send_to_invalidate(array($urlCategory));
      $result = $this->_check_result_response($response);
      $this->_set_log($result, array($urlCategory), "hookActionCategoryUpdate");
    }
  }

  public function hookActionProductUpdate($params) {
    $id_product = Tools::getValue('id_product');
    if (isset($id_product) && $id_product != '')
    {   
      $product = new Product($id_product);
      $urlProduct = $this->context->link->getProductLink($product);
      $response = $this->_send_to_invalidate(array($urlProduct));
      $result = $this->_check_result_response($response);
      $this->_set_log($result, array($urlProduct), "hookActionProductUpdate");
    }
  }

  public function getContent()
  {
    $output = null;
 
    if (Tools::isSubmit('submit'.$this->name))
    {
        $company_id = strval(Tools::getValue('TRANSPARENTCDN_COMPANY'));
        $client = strval(Tools::getValue('TRANSPARENTCDN_CLIENT'));
        $key = strval(Tools::getValue('TRANSPARENTCDN_KEY'));

        if ($this->isValidValue($company_id) &&
          $this->isValidValue($client) &&
          $this->isValidValue($key)) {

          Configuration::updateValue('TRANSPARENTCDN_COMPANY', $company_id);
          Configuration::updateValue('TRANSPARENTCDN_CLIENT', $client);
          Configuration::updateValue('TRANSPARENTCDN_KEY', $key);
          $output .= $this->displayConfirmation($this->l('Settings updated'));  
        
        } else {
        
          $output .= $this->displayError($this->l('All the fields are required, please fill them'));
        
        }
    }
    return $output.$this->displayForm();
  }

  private function isValidValue($value) {
    $valid = true;

    if(!$value
      || empty($value)
      || !Validate::isGenericName($value))
      $valid = false;

    return $valid;
  }


  public function displayForm() {
      // Get default language
      $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
       
      // Init Fields form array
      $fields_form[0]['form'] = array(
          'legend' => array(
              'title' => $this->l('Settings Transparent CDN module'),
          ),
          'input' => array(
              array(
                  'type' => 'text',
                  'label' => $this->l('Company id'),
                  'name' => 'TRANSPARENTCDN_COMPANY',
                  'size' => 20,
                  'required' => true
              ),
              array(
                  'type' => 'text',
                  'label' => $this->l('Client key'),
                  'name' => 'TRANSPARENTCDN_CLIENT',
                  'size' => 60,
                  'required' => true
              ),
              array(
                  'type' => 'text',
                  'label' => $this->l('Secret key'),
                  'name' => 'TRANSPARENTCDN_KEY',
                  'size' => 60,
                  'required' => true
              )
          ),
          'submit' => array(
              'title' => $this->l('Save'),
              'class' => 'button'
          )
      );
       
      $helper = new HelperForm();
       
      // Module, token and currentIndex
      $helper->module = $this;
      $helper->name_controller = $this->name;
      $helper->token = Tools::getAdminTokenLite('AdminModules');
      $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
       
      // Language
      $helper->default_form_language = $default_lang;
      $helper->allow_employee_form_lang = $default_lang;
       
      // Title and toolbar
      $helper->title = $this->displayName;
      $helper->show_toolbar = true;        // false -> remove toolbar
      $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
      $helper->submit_action = 'submit'.$this->name;
      $helper->toolbar_btn = array(
          'save' =>
          array(
              'desc' => $this->l('Save'),
              'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
              '&token='.Tools::getAdminTokenLite('AdminModules'),
          ),
          'back' => array(
              'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
              'desc' => $this->l('Back to list')
          )
      );
       
      // Load current values
      $helper->fields_value['TRANSPARENTCDN_COMPANY'] = Configuration::get('TRANSPARENTCDN_COMPANY');
      $helper->fields_value['TRANSPARENTCDN_CLIENT'] = Configuration::get('TRANSPARENTCDN_CLIENT');
      $helper->fields_value['TRANSPARENTCDN_KEY'] = Configuration::get('TRANSPARENTCDN_KEY');
       
      return $helper->generateForm($fields_form);
  }


  /**
   * Returns true if the desired url goes to invalidate false otherwise
   * @param json response
   * @return bool
   */
  private function _check_result_response($response) {
      $sendCorrectly = false;

      if(isset($response->locked_urls[0]))
      {
          $sendCorrectly = false;
      }
      
      if(isset($response->urls_to_send[0])) 
      {
          $sendCorrectly = true;
      }

      return $sendCorrectly;
  }

  /**
   * Performs a curl request and send to invalidate the product url and returns the response.
   * @param array urlsToInvalidate
   * @return json
   */
  private function _send_to_invalidate($urlsToInvalidate) {
      $companyId = Configuration::get('TRANSPARENTCDN_COMPANY');
      $token = $this->_get_token();
      $url = 'https://api.transparentcdn.com/v1/companies/'.$companyId.'/invalidate/';
      $aCurl = $this->_get_curl_headers($token);
      $post = '{"urls":[';
      for($i = 0; $i < count($urlsToInvalidate); $i++)
      {
          if($i == 0)
          {
              $post .= '"'.$urlsToInvalidate[$i].'"';
          }
          else
          {
              $post .= ',"'.$urlsToInvalidate[$i].'"';
          }
      }
      $post .= ']}';
      $response = json_decode($this->_get_response($url,$post,$aCurl));
      return $response;
  }

  /**
   * Returns the token to comunicate with transparent cdn, empty string if something goes wrong
   * @return string
   */
  private function _get_token() {
      $token = "";
      $client_id = Configuration::get('TRANSPARENTCDN_CLIENT');
      $client_secret = Configuration::get('TRANSPARENTCDN_KEY');

      $tokenUrl = 'https://api.transparentcdn.com/v1/oauth2/access_token/';
      $post = 'client_id='.$client_id.'&client_secret='.$client_secret.'&grant_type=client_credentials';

      $response = json_decode($this->_get_response($tokenUrl,$post));

      if(isset($response->access_token))
      {
          $token = $response->access_token;
      } 

      return $token;
  }

  /**
   * Returns an array with the curloptions to perform the request.
   * @param string token
   * @return array
   */
  private function _get_curl_headers($token) {
      return  array(
                  CURLOPT_HTTPHEADER  => array(
                                          'Authorization: Bearer '.$token,
                                          'Content-Type: application/json',
                                          ),
              );
  }

  /**
   * Returns the desired response from the provided url
   * @param string url
   * @param string post
   * @param array curlParameters
   * @param bool false
   * @return string
   */
  private function _get_response($url, $post = '', $curlParameters = '', $raw = FALSE) {        
      
      $ch = curl_init();

      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
      curl_setopt($ch, CURLOPT_TIMEOUT, 6000);
      curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
      curl_setopt($ch, CURLOPT_HEADER, FALSE);

      //curl_setopt($ch, CURLOPT_USERAGENT, 'trovitbot');
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
      if ($post) {
          curl_setopt($ch, CURLOPT_POST, TRUE);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
      }
      if ($curlParameters) {
          foreach ($curlParameters as $key => $value) {
              curl_setopt($ch, $key, $value);
          }
      }

      $html = curl_exec($ch);

      curl_close($ch);
      
      return $html;
  }

  /**
   * Set the log with the result of the request
   * @param bool $result
   * @param product product
   */
  private function _set_log($result, $productUrlsToInvalidate, $method) {
      $message = "";
      if($result)
      {
          $message = "urls for function ".$method." send to invalidate, urls:";
          foreach($productUrlsToInvalidate as $url)
          {
              $message .= " ".$url.",";
          }
          
      }
      else
      {
          $message = "urls for function ".$method." locked. Transparent can not clean the following urls:";
          foreach($productUrlsToInvalidate as $url)
          {
              $message .= " ".$url.",";
          }
      }

      Logger::addLog($message);
  }
}
?>