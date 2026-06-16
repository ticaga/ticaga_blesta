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
            // Create table for Ticaga API information
            $this->Record
                ->setField(
                    'api_email',
                    [
                        'type' => 'TEXT',
                        'is_null' => true
                    ]
                )
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
                ->create('ticaga_settings', true);
				
            // Create table ticaga_billing for User connections via API.
            $this->Record
                ->setField(
                    'ticaga_userid',
                    [
                        'type' => 'INT',
                        'size' => "10",
                        'is_null' => false,
                        'default' => '0',
                    ]
                )
				->setField(
                    'billing_userid',
                    [
                        'type' => 'INT',
                        'size' => "10",
                        'is_null' => false,
                        'default' => '0',
                    ]
                )
                ->setField(
                    'email_address',
                    [
                        'type' => 'VARCHAR',
                        'size' => "255",
                        'is_null' => false,
                        'default' => '',
                    ]
                )
                ->setField(
                    'billing_system',
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
                ->setKey(['ticaga_userid'], 'primary')
                ->create('ticaga_billing', true);
				
        } catch (Exception $e) {
            // Error adding... no permission?
            $this->Input->setErrors(['db' => ['create' => $e->getMessage()]]);
            return;
        }

        // Register the account-sync cron task (separate from table creation so a
        // cron API hiccup can't roll back the install)
        try {
            $this->addCronTasks();
        } catch (Throwable $e) {
            // Non-fatal: the plugin still installs without the scheduled sync
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
        // Remove this company's cron task run (and the task definition on the last instance)
        try {
            $this->removeCronTasks($last_instance);
        } catch (Throwable $e) {
            // Non-fatal
        }

        if ($last_instance) {
            try {
                // Remove database tables
                //$this->Record->from("actions")->where("actions.plugin_id", "=", $plugin_id)->delete(array("actions.*"));
                $this->Record->drop('ticaga_settings');
				$this->Record->drop('ticaga_billing');
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
        // Ensure the account-sync cron task is registered for installs upgrading
        // from a version before it existed (idempotent)
        try {
            $this->addCronTasks();
        } catch (Throwable $e) {
            // Non-fatal
        }
    }

    /**
     * Registers the account-sync cron task (idempotent: safe on install and upgrade).
     */
    private function addCronTasks()
    {
        Loader::loadModels($this, ['CronTasks']);

        $key = 'ticaga_account_sync';
        $dir = 'ticaga_support';

        // Register the (global) task definition if not already present
        $task = $this->CronTasks->getByKey($key, $dir, 'plugin');
        $task_id = $task ? $task->id : $this->CronTasks->add([
            'key' => $key,
            'task_type' => 'plugin',
            'dir' => $dir,
            'name' => 'Ticaga Account Sync',
            'description' => 'Creates and links Ticaga accounts for Blesta clients that are not yet synced.',
            'is_lang' => 0,
            'type' => 'interval'
        ]);

        // Register a per-company task run if one doesn't already exist
        if ($task_id) {
            $task_run = $this->CronTasks->getTaskRunByKey($key, $dir, false, 'plugin');
            if (!$task_run) {
                $this->CronTasks->addTaskRun($task_id, [
                    'interval' => 15,
                    'enabled' => 1
                ]);
            }
        }
    }

    /**
     * Removes the account-sync cron task run, and the task definition on last uninstall.
     *
     * @param bool $last_instance True if this is the last instance of the plugin
     */
    private function removeCronTasks($last_instance)
    {
        Loader::loadModels($this, ['CronTasks']);

        $key = 'ticaga_account_sync';
        $dir = 'ticaga_support';

        $task_run = $this->CronTasks->getTaskRunByKey($key, $dir, false, 'plugin');
        if ($task_run) {
            $this->CronTasks->deleteTaskRun($task_run->task_run_id);
        }

        if ($last_instance) {
            $task = $this->CronTasks->getByKey($key, $dir, 'plugin');
            if ($task) {
                $this->CronTasks->deleteTask($task->id, 'plugin', $dir);
            }
        }
    }

    /**
     * Runs a registered cron task.
     *
     * @param string $key The key of the cron task being run
     */
    public function cron($key)
    {
        if ($key === 'ticaga_account_sync') {
            try {
                Loader::loadModels($this, ['TicagaSupport.TicagaTickets']);
                // Process a bounded batch per run so cron execution stays fast
                $this->TicagaTickets->syncClients(100);
            } catch (Throwable $e) {
                // Swallow transient API errors so the run isn't marked failed
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
                            'uri' => 'plugin/ticaga_support/client_main/departments/',
                            'name' => 'Departments'
                        ],
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
                'label' => 'Total Tickets',
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

    /**
     * Returns all events to register observers for (invoked after install() or upgrade(),
     * overwrites all existing event handlers)
     *
     * @return array A numerically indexed array containing:
     *  - event The event to register for
     *  - callback A callable to be invoked when the event fires
     */
    public function getEvents()
    {
        return [
            // Fired whenever a Blesta client record is created (including self-registration)
            [
                'event' => 'Clients.add',
                'callback' => ['this', 'createTicagaAccount']
            ],
            // Fired whenever a user logs in — link clients on login so Ticaga staff can
            // see the customer (and their services, via the billing link) straight away
            [
                'event' => 'Users.login',
                'callback' => ['this', 'linkClientOnLogin']
            ]
        ];
    }

    /**
     * Event handler: creates a Ticaga account for a newly registered Blesta client
     * and links the two together. Idempotent and failure-isolated so it can never
     * interrupt the Blesta registration it observes.
     *
     * @param EventInterface $event The event instance, whose params include the new client_id
     */
    public function createTicagaAccount($event)
    {
        try {
            $params = is_object($event) && method_exists($event, 'getParams') ? $event->getParams() : [];
            $client_id = $params['client_id'] ?? ($params['id'] ?? null);

            if (empty($client_id)) {
                return;
            }

            // Delegate to the shared, idempotent link routine (find-or-create by email).
            Loader::loadModels($this, ['TicagaSupport.TicagaTickets']);
            $this->TicagaTickets->linkClient($client_id);
        } catch (Throwable $e) {
            // Never allow a Ticaga-side failure to interrupt Blesta client registration
            return;
        }
    }

    /**
     * Event handler: links a Blesta client to a Ticaga account when they log in,
     * so Ticaga staff can see the customer (and their services, via the billing
     * link) immediately. Idempotent and failure-isolated; no-ops for staff logins.
     *
     * @param EventInterface $event The Users.login event, whose params identify the user
     */
    public function linkClientOnLogin($event)
    {
        try {
            $params = is_object($event) && method_exists($event, 'getParams') ? $event->getParams() : [];

            // Resolve the id of the user that logged in (key varies by Blesta version)
            $user_id = null;
            if (!empty($params['user_id'])) {
                $user_id = $params['user_id'];
            } elseif (!empty($params['id'])) {
                $user_id = $params['id'];
            } elseif (isset($params['user']) && is_object($params['user']) && isset($params['user']->id)) {
                $user_id = $params['user']->id;
            }

            if (empty($user_id)) {
                return;
            }

            // Only client logins map to a Blesta client; staff logins won't match
            $client = $this->Record->select(['clients.id'])
                ->from('clients')
                ->where('clients.user_id', '=', $user_id)
                ->fetch();
            if (!$client) {
                return;
            }

            Loader::loadModels($this, ['TicagaSupport.TicagaTickets']);
            $this->TicagaTickets->linkClient($client->id);
        } catch (Throwable $e) {
            // Never allow a Ticaga-side failure to interrupt the login
            return;
        }
    }

	public function getAPIInfoByCompanyIdProvided(){
        $this->uses(['Record']);
        return $this->Record->select()->from("ticaga_settings")->where("ticaga_settings.company_id", "=", Configure::get('Blesta.company_id'))->fetch();
	}
	
	/**
     * Retrieves a partially-constructed Record object for fetching client tickets by ID
     *
     * @param int $clientid The ID of the client whose tickets counts to fetch
     * @return Record A partially-constructed Record object
     */
    private function getTicketsCountByClientID($clientid)
    {
		// The card callback receives the Blesta client ID; resolve the linked
		// Ticaga user ID before querying the Ticaga API.
		$billing = $this->Record->select('ticaga_userid')
			->from('ticaga_billing')
			->where('billing_userid', '=', $clientid)
			->fetch();

		if (!$billing || empty($billing->ticaga_userid)) {
			return 0;
		}

		$api = $this->getAPIInfoByCompanyIdProvided();
		if (!$api) {
			return 0;
		}

		$resp = $this->TicagaSettings->callAPI(
			'tickets/get/all/' . $billing->ticaga_userid,
			$api->api_url,
			$api->api_email,
			$api->api_key
		);

		if (($resp['status'] ?? '') !== 'success') {
			return 0;
		}

		$tickets = json_decode($resp['response'], true);

        return is_array($tickets) ? count($tickets) : 0;
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
