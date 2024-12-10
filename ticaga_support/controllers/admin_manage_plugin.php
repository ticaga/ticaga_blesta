
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
		
		$count = $this->TicagaTickets->getAPIInfoByCompanyIdCount();
		$this->parent->view->set('api_info_exists', $count);

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
        $this->uses(['Staff', 'Companies', 'TicagaSupport.TicagaTickets', 'TicagaSupport.TicagaSettings', 'Settings', 'Plugins']);
		$this->parent->set('company_id', Configure::get('Blesta.company_id'));
        // Manage actions
		
        if (!empty($this->post)) {
          switch ($this->post['type']) {
              case 'api_info':
				  $company_id = Configure::get('Blesta.company_id');
			 	  $api_key = $this->post['api_key'];
				  $api_url = $this->post['api_url'];
				  $arraypost = array("company_id" => $company_id, "api_key" => $api_key, "api_url" => $api_url);
				  $result = $this->TicagaSettings->add($this->post);
                  if ($result) {
                    $this->parent->flashMessage('message', "Added API Info!", true);
                  } else {
                    $this->parent->flashMessage('error', "Error Adding API Info", true);
                  }
                  break;
              default:
                  $this->parent->flashMessage('error', "Sorry, we couldn't do that requested action.");
                  break;
              }
          $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
        }

        // Set varriables
        
        return $this->partial('admin_manage_plugin');
    }
}
