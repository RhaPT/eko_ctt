<?php
/*
* 2015 ekosshop
*
* NOTICE OF LICENSE 
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
*
*  @author ekosshop <info@ekosshop.com>
*  @shop http://ekosshop.com
*  @copyright  2015 ekosshop
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*
*/

if (!defined('_PS_VERSION_'))
	exit;

class eko_ctt extends Module
{
	public $ctt_URL, $ctt_cron, $ctt_os_0, $ctt_tr_0, $ctt_tr_1, $ctt_change;

	public function __construct()
	{
		$this->name 	= 'eko_ctt';
		$this->tab     	= 'shipping_logistics';
		$this->version 	= '0.0.2';
		$this->author 	= 'ekosshop';

		$this->ctt_URL  = "http://www.ctt.pt/feapl_2/app/open/objectSearch/objectSearch.jspx";

		$config = Configuration::getMultiple(array('EKO_CTT_CRON', 'EKO_CTT_CHANGE_STATS', 'EKO_CTT_OS_0', 'EKO_CTT_TR_0', 'EKO_CTT_TR_1'));
		if (isset($config['EKO_CTT_CRON']))
			$this->ctt_cron = $config['EKO_CTT_CRON'];

		if (isset($config['EKO_CTT_CHANGE_STATS']))
			$this->ctt_change = $config['EKO_CTT_CHANGE_STATS'];

		if (isset($config['EKO_CTT_OS_0']))
			$this->ctt_os_0 = $config['EKO_CTT_OS_0'];

		if (isset($config['EKO_CTT_TR_0']))
			$this->ctt_tr_0 = $config['EKO_CTT_TR_0'];

		if (isset($config['EKO_CTT_TR_1']))
			$this->ctt_tr_1 = $config['EKO_CTT_TR_1'];

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('CTT Tracking');
		$this->description = $this->l('Tracking CTT Shipment');
		$this->confirmUninstall = $this->l('Are you sure about removing this module?');
	}

	public function install()
    {
        if(!(Configuration::get('EKO_CTT_OS_0') > 0))
            $this->create_states();

        if (!parent::install() || !$this->registerHook('displayAdminOrder') || !$this->registerHook('displayOrderDetail')
				|| !$this->registerHook('DisplayBackOfficeHeader') || !$this->registerHook('DisplayHeader'))
			return false;

        return true;
	}
 
    public function uninstall()
	{
		if (!parent::uninstall())
			return false;

		Configuration::deleteByName("EKO_CTT_CRON");
		Configuration::deleteByName("EKO_CTT_CHANGE_STATS");
		Configuration::deleteByName("EKO_CTT_OS_0");
		Configuration::deleteByName("EKO_CTT_TR_0");
		Configuration::deleteByName("EKO_CTT_TR_1");

        return true;
	}

	public function create_states()
	{
		$this->order_state = 	array(
									array( '009d95', '10110', 'Delivered',  '' , 0, 1, 1, 1)
								);

		/** OBTENDO UMA LISTA DOS IDIOMAS  **/
		$languages = Db::getInstance()->ExecuteS('
		SELECT `id_lang`, `iso_code`
		FROM `'._DB_PREFIX_.'lang`
		');
		/** /OBTENDO UMA LISTA DOS IDIOMAS  **/

		/** INSTALANDO STATUS MULTIBANCO **/
		foreach ($this->order_state as $key => $value)
		{
			/** CRIANDO OS STATUS NA TABELA order_state **/
			Db::getInstance()->Execute
			('
				INSERT INTO `' . _DB_PREFIX_ . 'order_state`
			( `invoice`, `send_email`, `color`, `unremovable`, `logable`, `delivery`, `module_name`, `shipped`, `paid`)
				VALUES
			('.$value[5].', '.$value[4].', \'#'.$value[0].'\', 1, 1, 1,\'eko_ctt\','.$value[6].','.$value[7].');
			');
			/** /CRIANDO OS STATUS NA TABELA order_state **/

			$this->figura 	= Db::getInstance()->Insert_ID();

			foreach ( $languages as $language_atual )
			{
				/** CRIANDO AS DESCRIÇÕES DOS STATUS NA TABELA order_state_lang  **/
				Db::getInstance()->Execute
				('
					INSERT INTO `' . _DB_PREFIX_ . 'order_state_lang`
				(`id_order_state`, `id_lang`, `name`, `template`)
					VALUES
				('.$this->figura .', '.$language_atual['id_lang'].', \''.$value[2].'\', \''.$value[3].'\');
				');
				/** /CRIANDO AS DESCRIÇÕES DOS STATUS NA TABELA order_state_lang  **/
			}

			/** COPIANDO O ICONE ATUAL **/
			$this->smartCopy((dirname(__file__) . "/logo.gif"),(dirname( dirname (dirname(__file__) ) ) .  "/img/os/$this->figura.gif"));
			/** /COPIANDO O ICONE ATUAL **/

    		/** GRAVA AS CONFIGURAÇÕES  **/
    		Configuration::updateValue("EKO_CTT_OS_$key", $this->figura);
		}

		/** CRIANDO A Tabela de Registo **/
		Db::getInstance()->Execute
		('
			CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'eko_ctt`(
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `order_id` int(11) NOT NULL,
			  `tracking` varchar(30) NOT NULL,
			  `entregue` smallint(2) NOT NULL,
			  `html` text,
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8;'
		);

		return true;
	}

	public function getContent()
	{
		$this->_html = '<h2>'.$this->displayName.'</h2>';

		if (Tools::isSubmit('btnSubmit'))
		{
			$this->_postProcess();
		}
		else
			$this->_html .= '<br />';

		$this->_displayEkoCTT();
		$this->_html .= $this->renderForm();

		return $this->_html;
	}

	private function _postProcess()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			Configuration::updateValue('EKO_CTT_CRON', 		   	Tools::getValue('cron'));
			Configuration::updateValue('EKO_CTT_CHANGE_STATS', 	Tools::getValue('change'));
			Configuration::updateValue('EKO_CTT_TR_0', 			Tools::getValue('tr_0'));
			Configuration::updateValue('EKO_CTT_TR_1', 			Tools::getValue('tr_1'));
		}
		$this->_html .= $this->displayConfirmation($this->l('Settings updated'));
	}

	private function _displayEkoCTT()
	{
		$this->_html .= '
		<div class="alert alert-info">
			<img src="../modules/eko_ctt/logo_ctt.png" style="float:left; margin-right:15px;" width="86" height="86">
			<p><strong>'.$this->l('This module allows Tracking CTT Shipment.').'</p>
			<p><br/>'.$this->l('This module adds a tracking result in order detail.').'</p>
			<p>'.$this->l('If you choose cron tab update, please use this path in your cron tab.').'</p>
			<p>'.PHP_BINDIR.DIRECTORY_SEPARATOR.'php '.dirname(__FILE__) .DIRECTORY_SEPARATOR.'cron'.DIRECTORY_SEPARATOR.'cron.php</p>
		</div>';
	}

	public function renderForm()
	{
		$CronOptions = array(
              array(
                'id_option' => 0, 
                'name' => $this->l('Order View') 
              ),
              array(
                'id_option' => 1, 
                'name' => $this->l('Cron Tab') 
              ),
              array(
                'id_option' => 2, 
                'name' => $this->l('Admin Hook Header') 
              ),
              array(
                'id_option' => 3, 
                'name' => $this->l('Front Hook Header')
              )
		);

		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('CTT Tracking Configuration'),
					'icon' => 'icon-truck'
				),
				'input' => array(
					array(
						'type'    => 'switch',
						'is_bool' => true,
						'label'   => $this->l('Update Order Status'),
						'name'    => 'change',
						'desc'    => $this->l('Change Order Status when delivery is detected.'),
						'values'  => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('Disabled')
							)
						)
					),
					array (
						'type'     => 'select',
						'label'    => $this->l('Update Mode'),
						'name'     => 'cron',
						'desc'     => $this->l('How to update shipping data'),
						'options'  => array(
							'query' 	=> $CronOptions,
							'id' 		=> 'id_option',
							'name' 		=> 'name'
						)
					),
					array (
						'type'     => 'select',
						'label'    => $this->l('CTT Carrier (01)'),
						'name'     => 'tr_0',
						'options'  => array(
							'query' 	=> $this->getTransportadoras(),
							'id' 		=> 'id_carrier',
							'name' 		=> 'name'
						)
					),
					array (
						'type'     => 'select',
						'label'    => $this->l('CTT Carrier (02)'),
						'name'     => 'tr_1',
						'options'  => array(
							'query' 	=> $this->getTransportadoras(),
							'id' 		=> 'id_carrier',
							'name' 		=> 'name'
						)
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			)
		);

		$helper = new HelperForm();
		$helper->module = $this;
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'btnSubmit';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}

	private function getConfigFieldsValues()
	{
		return array(
			'cron'   => Tools::getValue('cron',   Configuration::get('EKO_CTT_CRON')),
			'change' => Tools::getValue('change', Configuration::get('EKO_CTT_CHANGE_STATS')),
			'tr_0' 	 => Tools::getValue('tr_0', Configuration::get('EKO_CTT_TR_0')),
			'tr_1' 	 => Tools::getValue('tr_1', Configuration::get('EKO_CTT_TR_1')),
		);
	}

	private function getTransportadoras() {
		$sqlE = '
			SELECT `id_carrier`, `name`
			FROM `' . _DB_PREFIX_ . 'carrier`
			WHERE `deleted` = 0 and `active` = 1
		';
		$results = Db::getInstance()->ExecuteS($sqlE);
		array_unshift($results, array(
					'id_carrier' => 0,
					'name' 		 => $this->l('No Carrier')
				));
		return $results;
	}

	public function hookdisplayAdminOrder($params)
	{
		if (!$this->active)
			return;

		$order_id  	   = $params['id_order'];
		$order 	   	   = new Order($order_id);
/*
		$carrier   = new Carrier($order->id_carrier);
		$carrier->name = ($carrier->name == '0' ? "" : $carrier->name);

		if (empty($order->shipping_number) or ($carrier->name != 'CTT' and $carrier->name != 'CTT Express'))
			return;

		print_r($order->shipping_number." => ".$order->id_carrier. " CTT = ".Configuration::get('EKO_CTT_TR_0'));
		if (empty($order->shipping_number) or ($order->id_carrier != Configuration::get('EKO_CTT_TR_0') and $order->id_carrier != Configuration::get('EKO_CTT_TR_1')))
			return;
*/
		if (empty($order->shipping_number))
			return;

		$track = $this->_getEncomendaTrack($order->shipping_number, $order_id, true);	
		if(!is_string($track)) return;

		$this->smarty->assign('track', $track);

		return $this->display(__FILE__, '/ctt.tpl');
	}

	public function hookDisplayHeader($params)
    {
		if(Configuration::get('EKO_CTT_CRON') == 3 ) {
			$this->updateAllTracking();
		}
    }

	public function hookDisplayBackOfficeHeader($params)
    {
		if(Configuration::get('EKO_CTT_CRON') == 2 ) {
			$this->updateAllTracking();
		}
    }

	public function hookdisplayOrderDetail($params)
	{
		if (!$this->active)
			return;

		$order_id  = $params['order']->id;
		$order 	   = new Order($order_id);

/*
		$carrier   = new Carrier($order->id_carrier);
		$carrier->name = ($carrier->name == '0' ? "" : $carrier->name);

		if (empty($order->shipping_number) or ($carrier->name != 'CTT' and $carrier->name != 'CTT Express'))
			return;

		print_r($order->shipping_number." => ".$order->id_carrier. " CTT = ".Configuration::get('EKO_CTT_TR_0'));
		if (empty($order->shipping_number) or ($order->id_carrier != Configuration::get('EKO_CTT_TR_0') and $order->id_carrier != Configuration::get('EKO_CTT_TR_1')))
			return;
*/
		if (empty($order->shipping_number))
			return;	

		$track = $this->_getEncomendaTrack($order->shipping_number, $order_id);
		if(!is_string($track)) return;

		$this->smarty->assign('track', $track);

		return $this->display(__FILE__, '/ctt.tpl');
	}
	
	private function _getEncomendaTrack($trackingNumber, $order, $admin = false, $updateMode = false)
	{
		if($this->verifyTrackingDB($trackingNumber) == 0) {
			$orderOBJ = new Order($order);
			if ($orderOBJ->id_carrier != Configuration::get('EKO_CTT_TR_0') and $orderOBJ->id_carrier != Configuration::get('EKO_CTT_TR_1'))
				return;	
			$this->setTrackingDB($order, $trackingNumber);
		}

		$tracking = $this->getTrackingDB($trackingNumber);
		if($tracking['entregue'] > 0) {
			if($updateMode) return 1;
			$sResult = $this->translateTracking($tracking['html']);
		} else {
			if(!$this->checkOnline("www.ctt.pt")) {
				return 0;
			}
			$sSearch = "details_0";
			$aParams = array ('objects' => '', 'showResults' => 'true', 'pesqObjecto.objectoId' => $trackingNumber );

			// Build Http query using params
			$sQuery = http_build_query ($aParams);

			// Create Http context details
			$aContextData = array (
                            'method' => 'POST',
                            'header' => "Connection: close\r\n".
                            "Content-Type: application/x-www-form-urlencoded\r\n".
                            "Content-Length: ".strlen($sQuery)."\r\n",
                            'content'=> $sQuery );

			// Create context resource for our request
			$sContext = stream_context_create(array ( 'http' => $aContextData ));

			// Read page rendered as result of your POST request
			$sResult = file_get_contents( $this->ctt_URL, false, $sContext);
			$iPos = strpos($sResult, $sSearch);
			if( $iPos > 0 and strpos($sResult, "Objecto n&atilde;o encontrado") === false 
						  and strpos($sResult, "Não foi possível obter mais informação sobre o objeto.") === false
						  and strpos($sResult, "NÃ£o foi possÃ­vel obter mais informaÃ§Ã£o sobre o objeto.") === false
				) {
				$sResult  = substr($sResult, $iPos + strlen($sSearch));          	// Retirar Top do Resultado
				$iPos 	  = strpos($sResult, "<table");
				$sResult  = substr($sResult, $iPos);
				$iPos 	  = strpos($sResult, "</table>");							// Retirar Bottom do Resultado 
				$sResult  = substr($sResult, 0, $iPos + 8);
				$sResult  = trim(preg_replace('/\s\s+/', ' ', $sResult));           // Remove Tabs
			} else {
				$sResult  = '<table class="full-width"><tr><td>Objecto n&atilde;o encontrado</td></tr></table>';
			}

			$entregue = 0;
			$iPos = strpos($sResult, "Entrega conseguida");
			if($iPos > 0) {
				$entregue = 1;
				$this->changeTrackOrderState($order);
			}

			$sResult = str_replace("<tr>",'<tr class="item">',$sResult);
			$sResult = str_replace('class="group"','',$sResult);
			$sResult = str_replace('<td colspan="5">','<td colspan=5" style="background-color: #f2f2f2;">',$sResult);

			if(!empty($sResult)) {
				$this->updateTrackingDb($trackingNumber, $entregue, $sResult);
				if($updateMode) return 1;
				$sResult = $this->translateTracking($sResult);
			} else {
				return 0;
			}
		}

		if($admin) {
			$sResult = str_replace("full-width","table",$sResult);
			$sResult = str_replace("<th>",'<th class="title_box">',$sResult);
			$sResult = '<div id="formTrackingPanel" class="panel"><div class="panel-heading">
					<i class="icon-truck "> </i> '.$this->l('Shipment Tracking').'</div><div class="table-responsive">'.$sResult.'</div></div>';
		} else {
			$sResult = str_replace("full-width","table table-bordered footab footable-loaded footable default",$sResult);
			$sResult = '<h3 class="page-heading bottom-indent">'.$this->l('Shipment Tracking').'</h3>'.$sResult;
		}

		return ($sResult);
	}

	private function checkOnline($domain) {
		$curlInit = curl_init($domain);
		curl_setopt($curlInit,CURLOPT_CONNECTTIMEOUT,10);
		curl_setopt($curlInit,CURLOPT_HEADER,true);
		curl_setopt($curlInit,CURLOPT_NOBODY,true);
		curl_setopt($curlInit,CURLOPT_RETURNTRANSFER,true);

		//get answer
		$response = curl_exec($curlInit);

		curl_close($curlInit);
		if ($response) return true;
		return false;
	}

	private function getTrackingDB($trackingNumber) {
        $traking = Db::getInstance()->getRow('
			SELECT *
			FROM `' . _DB_PREFIX_ . 'eko_ctt`
			WHERE `tracking` = \'' . $trackingNumber . '\' 
		');

        return $traking;
	}

	private function setTrackingDB($order, $trackingNumber) {
		if(!empty($trackingNumber)) {
			Db::getInstance()->Execute
			('
				INSERT INTO `' . _DB_PREFIX_ . 'eko_ctt`
				( `order_id`, `tracking`)
					VALUES
				('.$order.', \''.$trackingNumber.'\');
			');
		}
	}

	public function updateTrackingDb($trackingNumber, $entregue, $html)
	{
		Db::getInstance()->Execute
			('	UPDATE `' . _DB_PREFIX_ . 'eko_ctt`
				SET `entregue` = '.$entregue.', `html` = \''.$html.'\' 
				WHERE `tracking`= \''.$trackingNumber.'\'
			');
	}

	private function verifyTrackingDb($trackingNumber)
	{
		$tracking = Db::getInstance()->getRow('
		SELECT count(order_id) as Total
		FROM `'._DB_PREFIX_.'eko_ctt`
		WHERE `tracking` = \''.$trackingNumber.'\' 
		');

		return $tracking['Total'];
	}

	public function updateAllTracking()
	{
		$x = 0;
		$sqlE = 'SELECT `order_id`, `tracking`
			FROM `' . _DB_PREFIX_ . 'eko_ctt`
			WHERE `entregue` = 0';
		$sqlResult = Db::getInstance()->ExecuteS($sqlE);

		foreach ( $sqlResult as $row ) {
			$this->_getEncomendaTrack($row['tracking'], $row['order_id'], false, true);
			$x++;
		}

		return($x);
	}

	private function changeTrackOrderState($orderId) {
		if(!empty($orderId) and Configuration::get('EKO_CTT_CHANGE_STATS')){
			$order = new Order((int)$orderId);
			$use_existings_payment = !$order->hasInvoice();
			$new_history = new OrderHistory();
			$new_history->id_order = (int)$orderId;
			if($order->current_state != (int)Configuration::get('EKO_CTT_OS_0')) {
				$new_history->changeIdOrderState((int)Configuration::get('EKO_CTT_OS_0'), $order, $use_existings_payment);
				$new_history->add(true);
			}
		}

		return true;
	}

	private function translateTracking($html)
	{
		$html = str_replace("Hora",$this->l('Time'),$html);
		$html = str_replace("Estado",$this->l('Status'),$html);
		$html = str_replace("Motivo",$this->l('Info'),$html);
		$html = str_replace("Local",$this->l('Location'),$html);
		$html = str_replace("Recetor",$this->l('Receiver'),$html);

		$html = str_replace("Envio",$this->l('Shipment'),$html);
		$html = str_replace("Entrega conseguida",$this->l('Delivered'),$html);
		$html = str_replace("Em distribui&ccedil;&atilde;o",$this->l('In Transit'),$html);
		$html = str_replace("Expedi&ccedil;&atilde;o nacional",$this->l('National Shipment'),$html);
		$html = str_replace("Rece&ccedil;&atilde;o no local de entrega",$this->l('Delivered at Site'),$html);
		$html = str_replace("Rece&ccedil;&atilde;o nacional",$this->l('National Reception'),$html);
		$html = str_replace("Aceita&ccedil;&atilde;o",$this->l('Pickup'),$html);
		$html = str_replace("Dispon&iacute;vel para levantamento",$this->l('Available for Pickup'),$html);
		$html = str_replace("Entrega n&atilde;o conseguida",$this->l('Not Delivered'),$html);
		$html = str_replace("Destinat&aacute;rio ausente, empresa encerrada, Avisado na Loja CTT",$this->l('Recipient missing or company closed, notice at CTT Shop '),$html);
		$html = str_replace("Entrega n&atilde;o efectuada, Aguarda nova tentativa de entrega",$this->l('Not Delivered, waiting for new delivery attempt'),$html);

		$html = str_replace("Pedido de Encaminhamento/SIGA, Reexpedido",$this->l('Request Forwarding / SIGA, transhipped'),$html);
		$html = str_replace("Objecto n&atilde;o encontrado",$this->l('Not Found'),$html);
		$html = str_replace("NÃ£o foi possÃ­vel obter mais informaÃ§Ã£o sobre o objeto.",$this->l('Shipment not Found'),$html);

		$html = str_replace("Janeiro",$this->l('January'),$html);
		$html = str_replace("Fevereiro",$this->l('February'),$html);
		$html = str_replace("Mar&ccedil;o",$this->l('March'),$html);
		$html = str_replace("Abril",$this->l('April'),$html);
		$html = str_replace("Maio",$this->l('May'),$html);
		$html = str_replace("Junho",$this->l('June'),$html);
		$html = str_replace("Julho",$this->l('July'),$html);
		$html = str_replace("Agosto",$this->l('August'),$html);
		$html = str_replace("Setembro",$this->l('September'),$html);
		$html = str_replace("Outubro",$this->l('October'),$html);
		$html = str_replace("Novembro",$this->l('November'),$html);
		$html = str_replace("Dezembro",$this->l('December'),$html);

		$html = str_replace("segunda-feira",$this->l('monday'),$html);
		$html = str_replace("ter&ccedil;a-feira",$this->l('tuesday'),$html);
		$html = str_replace("quarta-feira",$this->l('wednesday'),$html);
		$html = str_replace("quinta-feira",$this->l('thursday'),$html);
		$html = str_replace("sexta-feira",$this->l('friday'),$html);
		$html = str_replace("sabado",$this->l('saturday'),$html);
		$html = str_replace("domingo",$this->l('sunday'),$html);

		return($html);
	}

	public function smartCopy($source, $dest, $options=array('folderPermission'=>0755,'filePermission'=>0755))
	{
        $result=false;

        if (is_file($source)) {
            if ($dest[strlen($dest)-1]=='/') {
                if (!file_exists($dest)) {
                    cmfcDirectory::makeAll($dest,$options['folderPermission'],true);
                }
                $__dest=$dest."/".basename($source);
            } else {
                $__dest=$dest;
            }
            $result=copy($source, $__dest);
            chmod($__dest,$options['filePermission']);

        } elseif(is_dir($source)) {
            if ($dest[strlen($dest)-1]=='/') {
                if ($source[strlen($source)-1]=='/') {
                    //Copy only contents
                } else {
                    //Change parent itself and its contents
                    $dest=$dest.basename($source);
                    @mkdir($dest);
                    chmod($dest,$options['filePermission']);
                }
            } else {
                if ($source[strlen($source)-1]=='/') {
                    //Copy parent directory with new name and all its content
                     @mkdir($dest,$options['folderPermission']);
                    chmod($dest,$options['filePermission']);
                } else {
                    //Copy parent directory with new name and all its content
                    @mkdir($dest,$options['folderPermission']);
                    chmod($dest,$options['filePermission']);
                }
            }

            $dirHandle=opendir($source);
            while($file=readdir($dirHandle))
            {
                if($file!="." && $file!="..")
                {
                     if(!is_dir($source."/".$file)) {
                        $__dest=$dest."/".$file;
                    } else {
                        $__dest=$dest."/".$file;
                    }
                    //echo "$source/$file ||| $__dest<br />";
                    $result=smartCopy($source."/".$file, $__dest, $options);
                }
            }
            closedir($dirHandle);

        } else {
            $result=false;
        }
        return $result;
    }
}