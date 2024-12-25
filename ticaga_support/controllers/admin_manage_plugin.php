
<?php
/**
 * TicagaSupport ticaga_admin_main controller
 *
 * @link https://ticaga.com/ Ticaga
 */
class AdminManagePlugin extends AppController
{
	private function init()
    {
        // Require Login
        $this->parent->requireLogin();

        // Load Language
        Language::loadLang('ticaga_support_plugin', null, PLUGINDIR . 'ticaga_support' . DS . 'language' . DS);

        $this->company_id = Configure::get('Blesta.company_id');
        $this->plugin_id  = (isset($this->get[0]) ? $this->get[0] : null);
        $this->Javascript = $this->parent->Javascript;
        $this->parent->structure->set('page_title', Language::_('TicagaAdminMain.index.page_title', true));
        $this->uses(['PluginManager']);
        $this->view->setView(null, 'ticaga_support.default');
		// Load components
        Loader::loadComponents($this, ['Input', 'Record', 'Session']);
		
		// Load models
        Loader::loadModels($this, ['Staff', 'Companies', 'TicagaSupport.TicagaTickets', 'TicagaSupport.TicagaSettings']);

        // Get Plugin ID
        if (isset($this->plugin_id)) {
            $plugins = $this->PluginManager->get($this->plugin_id, true);
        }
    }
	
	/**
     * Returns the view for a list of extensions
     */
    public function index()
    {
        $this->init();
        $this->uses(['Staff', 'Companies', 'TicagaSupport.TicagaTickets', 'TicagaSupport.TicagaSettings', 'Settings', 'Plugins', 'PluginManager']);
        $this->parent->set('company_id', Configure::get('Blesta.company_id'));
        // Manage actions

        $api_info_exists = $this->TicagaTickets->getAPIInfoByCompanyIdCount();

        if (!empty($this->post)) {
            switch ($this->post['type']) {
                case 'api_info':
                    $company_id = Configure::get('Blesta.company_id');
                    $api_key = $this->post['api_key'];
                    $api_url = $this->post['api_url'];
                    if(!empty($api_key) && !empty($api_url))
                    {
                        $arraypost = array("company_id" => $company_id, "api_key" => $api_key, "api_url" => $api_url);
                        $result = $this->TicagaSettings->add($arraypost);
                    } else {
                        $result = 'false';
                    }
                    if ($result == 'true') {
                        $this->flashMessage('message', "Congratulations, you've connected Blesta to Ticaga.", null, false);
                        $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
                    } else {
                        $this->flashMessage('error', "Sorry, your account couldn't connect to Ticaga, please check your details.", null, false);
                        $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
                    }
                    break;
                default:
                    $this->flashMessage('error', "Sorry, we couldn't do that requested action.", null, false);
                    break;
            }
            $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
        } else {
            return $this->partial('admin_manage_plugin',[
                'api_info_exists' => $api_info_exists,
            ]);
        }
    }
}
