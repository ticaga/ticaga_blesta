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
        
        $api_info = $this->TicagaTickets->getAPIInfoByCompanyId();

        if (!empty($this->post)) {
            switch ($this->post['type']) {
                case 'api_info':
                    $company_id = Configure::get('Blesta.company_id');
                    $api_key	= $this->post['api_key'];
                    $api_email 	= $this->post['api_email'];
                    $api_url 	= $this->post['api_url'];
                    if(!empty($api_key) && !empty($api_email) && !empty($api_url))
                    {
                        // Verify the credentials actually work against the API before saving
                        $test = $this->TicagaSettings->testConnection($api_url, $api_email, $api_key);
                        if (!$test['success']) {
                            $this->flashMessage('error', "Connection failed: " . $test['message'], null, false);
                            $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
                        }

                        $arraypost = array("company_id" => $company_id, "api_key" => $api_key, "api_email" => $api_email, "api_url" => $api_url);
                        if(!empty($api_info))
                        {
                            $result = $this->TicagaSettings->edit($arraypost);
                            if ($result) {
                                $this->flashMessage('message', "Success: API Details have been verified and updated.", null, false);
                                $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
                            } else {
                                $this->flashMessage('error', "Error: API Details couldn't be updated.", null, false);
                                $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
                            }
                        } else {
                            $result = $this->TicagaSettings->add($arraypost);
                            if ($result) {
                                $this->flashMessage('message', "Congratulations, you've connected Blesta to Ticaga.", null, false);
                                $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
                            } else {
                                $this->flashMessage('error', "Sorry, your account couldn't connect to Ticaga, please check your details.", null, false);
                                $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
                            }
                        }
                    } else {
                        $this->flashMessage('error', "Please provide the API URL, email and key.", null, false);
                        $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
                    }

                    break;
                case 'sync_accounts':
                    if (empty($api_info)) {
                        $this->flashMessage('error', "Please connect to the Ticaga API before syncing accounts.", null, false);
                        $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
                    }

                    // Process a bounded batch so the request cannot time out; the cron
                    // task picks up any remainder on its schedule.
                    $sync = $this->TicagaTickets->syncClients(100);

                    if ($sync['remaining'] > 0) {
                        $this->flashMessage('message', "Synced " . $sync['linked'] . " account(s) this run. " . $sync['remaining'] . " remaining will be processed automatically by the cron, or click again.", null, false);
                    } else {
                        $this->flashMessage('message', "Account sync complete. Linked " . $sync['linked'] . " account(s)" . ($sync['failed'] > 0 ? ", " . $sync['failed'] . " could not be synced." : "."), null, false);
                    }
                    $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
                    break;
                default:
                    $this->flashMessage('error', "Sorry, we couldn't do that requested action.", null, false);
                    break;
            }
            $this->redirect($this->base_uri . 'settings/company/plugins/manage/' . $this->plugin_id . '/');
        } else {
            // Determine live connection status for display.
            // null = not configured, true = connected, false = configured but unreachable/unauthorised
            $connection_status = null;
            if (!empty($api_info)) {
                $test = $this->TicagaSettings->testConnection($api_info->api_url, $api_info->api_email, $api_info->api_key);
                $connection_status = $test['success'];
                $connection_message = $test['message'];
            }

            return $this->partial('admin_manage_plugin',[
                'api_info' => $api_info,
                'connection_status' => $connection_status,
                'connection_message' => isset($connection_message) ? $connection_message : '',
            ]);
        }
    }
}
