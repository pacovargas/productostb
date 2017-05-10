<?php
/**
* 2007-2017 PrestaShop
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
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2017 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Productostb extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'productostb';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Francisco Javier Vargas Estrada';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('productostb');
        $this->description = $this->l('Importación de productos Tomas Bodero');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        // Configuration::updateValue('PRODUCTOSTB_LIVE_MODE', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader');
    }

    public function uninstall()
    {
        // Configuration::deleteByName('PRODUCTOSTB_LIVE_MODE');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitProductostbModule')) == true) {
            $this->postProcess();
        }

        $link = new Link();
        $enlace_sincronizar = $link->getModuleLink('productostb', 'sincronizar');
        $enlace_getproducts = $link->getModuleLink('productostb', 'getproducts');

        $this->context->smarty->assign('module_dir', $this->_path);

        $this->context->smarty->assign(array(
            'enlace_sincronizar' => $enlace_sincronizar,
            'enlace_getproducts' => $enlace_getproducts,
            'enlace_log' => _PS_BASE_URL_ . "/logs/productostb.log",
        ));

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $this->renderForm().$output;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProductostbModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'PRODUCTOSTB_SERVER',
                        'label' => $this->l('Servidor FTP'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'PRODUCTOSTB_USER',
                        'label' => $this->l('Usuario FTP'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'password',
                        'name' => 'PRODUCTOSTB_PASSWORD',
                        'label' => $this->l('Contraseña FTP'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'PRODUCTOSTB_RUTA',
                        'label' => $this->l('Ruta del archivo'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'PRODUCTOSTB_SERVER' => Configuration::get('PRODUCTOSTB_SERVER', null),
            'PRODUCTOSTB_USER' => Configuration::get('PRODUCTOSTB_USER', null),
            'PRODUCTOSTB_PASSWORD' => Configuration::get('PRODUCTOSTB_PASSWORD', null),
            'PRODUCTOSTB_RUTA' => Configuration::get('PRODUCTOSTB_RUTA', null),

        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function getIdiomas(){
        $idiomas = Language::getIsoIds();
        $ret = array();
        foreach ($idiomas as $i){
            $ret[] = $i["id_lang"];
        }
        return $ret;
    }

    public function log($msg){
        $filename = "productostb";
        $logger = new FileLogger(0); //0 == nivel de debug. Sin esto logDebug() no funciona.
        $logger->setFilename(_PS_ROOT_DIR_."/logs/$filename.log");
        $logger->logInfo("$msg");
    }
}
