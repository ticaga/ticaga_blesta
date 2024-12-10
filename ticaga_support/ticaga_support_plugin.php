<?php
use Blesta\Core\Util\Events\Common\EventInterface;

/**
 * TicagaSupport plugin handler
 *
 * @link https://ticaga.com/ Ticaga
 */
class TicagaSupportPlugin extends Plugin
{
    public function __construct()
    {
        // Load components required by this plugin
        Loader::loadComponents($this, ['Input', 'Record']);
		
		// Load models
		Loader::loadModels($this, ['Staff', 'Companies', 'TicagaSupport.TicagaSettings']);

        Language::loadLang('ticaga_support_plugin', null, dirname(__FILE__) . DS . 'language' . DS);
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');
    }

    /**
     * Performs any necessary bootstraping actions
     *
     * @param int $plugin_id The ID of the plugin being installed
     */
    public function install($plugin_id)
    {
        try {
            // Create database tables
            // ticaga_blesta_settings
            $this->Record
                ->setField(
                    'api_key',
                    [
                        'type' => 'TEXT',
                        'is_null' => true
                    ]
                )
                ->setField(
                    'api_url',
                    [
                        'type' => 'VARCHAR',
                        'size' => "255",
                        'is_null' => false,
                        'default' => '',
                    ]
                )
				->setField(
                    'company_id',
                    [
                        'type' => 'VARCHAR',
                        'size' => "255",
                        'is_null' => false,
                        'default' => Configure::get('Blesta.company_id'),
                    ]
                )
                ->setKey(['api_url'], 'primary')
                ->create('ticaga_blesta_settings', true);
				
				// Create database tables
            // ticaga_blesta_user_association
            $this->Record
                ->setField(
                    'user_ticaga',
                    [
                        'type' => 'VARCHAR',
                        'size' => "255",
                        'is_null' => false,
                        'default' => '',
                    ]
                )
				->setField(
                    'user_blesta',
                    [
                        'type' => 'VARCHAR',
                        'size' => "255",
                        'is_null' => false,
                        'default' => '',
                    ]
                )
                ->setField(
                    'email_ticaga',
                    [
                        'type' => 'VARCHAR',
                        'size' => "255",
                        'is_null' => false,
                        'default' => '',
                    ]
                )
				->setField(
                    'company_id',
                    [
                        'type' => 'INT',
                        'size' => "10",
                        'is_null' => false,
                        'default' => Configure::get('Blesta.company_id'),
                    ]
                )
                ->setKey(['user_ticaga'], 'primary')
                ->create('ticaga_blesta_users', true);
				
        } catch (Exception $e) {
            // Error adding... no permission?
            $this->Input->setErrors(['db' => ['create' => $e->getMessage()]]);
            return;
        }
    }

    /**
     * Performs any necessary cleanup actions
     *
     * @param int $plugin_id The ID of the plugin being uninstalled
     * @param bool $last_instance True if $plugin_id is the last instance across
     *  all companies for this plugin, false otherwise
     */
    public function uninstall($plugin_id, $last_instance)
    {
        if ($last_instance) {
            try {
                // Remove database tables
                $this->Record->drop('ticaga_blesta_settings');
				$this->Record->drop('ticaga_blesta_users');
            } catch (Exception $e) {
                // Error dropping... no permission?
                $this->Input->setErrors(['db' => ['create' => $e->getMessage()]]);
                return;
            }
        }
    }
	
	/**
     * Perform the upgrade logic of the plugin.
     *
     * @param string $current_version The installed version of the product
     * @param int    $plugin_id       The plugin ID
     */
    public function upgrade($current_version, $plugin_id)
    {
        // Upgrade if possible
        if (version_compare($this->version, $current_version, '>')) {
          // Upgrade to 1.0.1 (Second Revision/Release)
          if (version_compare($current_version, '1.0.1', '<')) {
            //Create IP Address table
            $this->Record->setField('user_ticaga', ['type' => 'varchar', 'size' => 255])
                    ->setField('user_blesta', ['type' => 'varchar', 'size' => 255])
                    ->setField('email_ticaga', ['type' => 'varchar', 'size' => 255])
                    ->setField('company_id', ['type' => 'int', 'size' => 10])
                    ->setKey(['user_ticaga'], 'primary')
                    ->create('ticaga_blesta_users', true);
          }
		}
    }

    /**
     * Returns all actions to be configured for this widget
     * (invoked after install() or upgrade(), overwrites all existing actions)
     *
     * @return array A numerically indexed array containing:
     *  - action The action to register for
     *  - uri The URI to be invoked for the given action
     *  - name The name to represent the action (can be language definition)
     *  - options An array of key/value pair options for the given action
     */
    public function getActions()
    {
		return [
		 // Client Nav
            [
                'action' => 'nav_primary_client',
                'uri' => 'plugin/ticaga_support/client_main/index',
                'name' => 'TicagaSupportPlugin.nav_primary_client.main',
                'options' => [
                    'sub' => [
                        [
                            'uri' => 'plugin/ticaga_support/client_main/index/',
                            'name' => 'TicagaSupportPlugin.nav_primary_client.index'
                        ]
                    ]
                ]
            ],
            // Client Widget
            [
                'action' => 'widget_client_home',
                'uri' => 'plugin/ticaga_support/client_main/index/',
                'name' => 'TicagaSupportPlugin.widget_client_home.main'
            ]
        ];
    }

    /**
     * Returns all cards to be configured for this plugin (invoked after install() or upgrade(),
     * overwrites all existing cards)
     *
     * @return array A numerically indexed array containing:
     *
     *  - level The level this card should be displayed on (client or staff) (optional, default client)
     *  - callback A method defined by the plugin class for calculating the value of the card or fetching a custom html
     *  - callback_type The callback type, 'value' to fetch the card value or
     *      'html' to fetch the custom html code (optional, default value)
     *  - background The background color in hexadecimal or path to the background image for this card (optional)
     *  - background_type The background type, 'color' to set a hexadecimal background or
     *      'image' to set an image background (optional, default color)
     *  - label A string or language key appearing under the value as a label
     *  - link The link to which the card will be pointed (optional)
     *  - enabled Whether this card appears on client profiles by default
     *      (1 to enable, 0 to disable) (optional, default 1)
     */
    public function getCards()
    {
        return [
            [
                'level' => 'client',
                'callback' => ['this', 'getClientTicketsTotal'],
                'callback_type' => 'value',
                'background' => '#fff',
                'background_type' => 'color',
                'label' => 'TicagaSupportPlugin.card_client.getClientTicketsTotal',
                'link' => 'plugin/ticaga_support/client_main',
                'enabled' => 1
            ]
        ];
    }

    /**
     * Returns all permissions to be configured for this plugin (invoked after install(), upgrade(),
     *  and uninstall(), overwrites all existing permissions)
     *
     * @return array A numerically indexed array containing:
     *
     *  - group_alias The alias of the permission group this permission belongs to
     *  - name The name of this permission
     *  - alias The ACO alias for this permission (i.e. the Class name to apply to)
     *  - action The action this ACO may control (i.e. the Method name of the alias to control access for)
     */
    public function getPermissions()
    {
    }

    /**
     * Returns all permission groups to be configured for this plugin (invoked after install(), upgrade(),
     *  and uninstall(), overwrites all existing permission groups)
     *
     * @return array A numerically indexed array containing:
     *
     *  - name The name of this permission group
     *  - level The level this permission group resides on (staff or client)
     *  - alias The ACO alias for this permission group (i.e. the Class name to apply to)
     */
    public function getPermissionGroups()
    {
    }
	
	public function getAPIInfoByCompanyId(){
        $this->uses(['Record']);
        return $this->Record->select()->from("ticaga_blesta_settings")->where("ticaga_blesta_settings.company_id", "=", Configure::get('Blesta.company_id'))->fetch();
    }

	public function getAPIInfoByCompanyIdProvided(){
        $this->uses(['Record']);
        return $this->Record->select()->from("ticaga_blesta_settings")->where("ticaga_blesta_settings.company_id", "=", Configure::get('Blesta.company_id'))->fetch();
	}
	
	/**
     * Retrieves a partially-constructed Record object for fetching client tickets by ID
     *
     * @param int $clientid The ID of the client whose tickets counts to fetch
     * @return Record A partially-constructed Record object
     */
    private function getTicketsCountByClientID($clientid)
    {
		$company_id = Configure::get('Blesta.company_id');
        $apiKey = $this->getAPIInfoByCompanyIdProvided($company_id)->api_key;
		$apiURL = $this->getAPIInfoByCompanyIdProvided($company_id)->api_url;
		
		$resp = $this->TicagaSettings->callAPI("tickets/user/" . $clientid,$apiURL,$apiKey);
		
		$totalcountjson = json_decode($resp,true);
		
		$totalcount = count($totalcountjson);
		
        return $totalcount ?? 0;
    }

    /**
     * Retrieves the value for a client card
     *
     * @param int $client_id The ID of the client for which to fetch the card value
     * @return mixed The value for the client card
     */
    public function getStaffTicketsForClients($client_id)
    {
        return $this->getTicketsCountByClientID($client_id);
    }

    /**
     * Retrieves the value for a client card
     *
     * @param int $client_id The ID of the client for which to fetch the card value
     * @return mixed The value for the client card
     */
    public function getClientTicketsTotal($client_id)
    {
        return $this->getTicketsCountByClientID($client_id);
    }
}
