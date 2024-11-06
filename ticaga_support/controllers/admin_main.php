
<?php
/**
 * TicagaSupport ticaga_admin_main controller
 *
 * @link https://ticaga.com/ Ticaga
 */
class AdminMain extends TicagaSupportController
{
    /**
     * Setup
     */
    public function preAction()
    {
        parent::preAction();

        $this->structure->set('page_title', Language::_('TicagaAdminMain.index.page_title', true));
		
		Language::loadLang('ticaga_support_plugin', null, PLUGINDIR . 'ticaga_support' . DS . 'language' . DS);
		
		// Load components
        Loader::loadComponents($this, ['Input', 'Record', 'Session']);

        // Load models
        Loader::loadModels($this, ['Staff', 'Companies', 'TicagaSupport.TicagaTickets', 'TicagaSupport.TicagaSettings']);
    }

    /**
     * Returns the view for a list of extensions
     */
    public function index()
    {
		$count = $this->TicagaTickets->getAPIInfoByCompanyIdCount();
		if ($count == false)
		{
			$this->flashMessage('message', "API Information Must be Added!", null, false);
			$this->redirect($this->base_uri . 'plugin/ticaga_support/admin_main/addAPIInfo/');	
		} else {
			$tickets = $this->TicagaTickets->getTickets();
			if ($tickets == false)
			{
				$this->set('tickets', []);
			} else {
				$this->set('tickets', $tickets);
			}
		}
    }
	
	/**
     * Returns the view for showing client departments for submitting tickets to.
     */
    public function adminClientTicketChooseDept()
	{
			$client_id = $this->get[0] ?? false;
			$depts = $this->TicagaTickets->getDepartmentsForPublicUseOnly();
			if ($depts != false && $client_id != false)
			{
				$this->set('depts', $depts);
			} else if($depts != false && $client_id == false){
				$this->flashMessage('message', "Client ID could not be detected. Please ensure the Client Parameters are correct", null, false);
				$this->redirect($this->base_uri . 'plugin/ticaga_support/admin_main/index/');
			} else {
				$this->set('depts', []);
			}
	}
  
  	/**
     * Returns the view for submitting ticket as Client
     */
    public function adminClientTicketSubmit()
	{
		$client_id = $this->get[0] ?? false;
		$userExists = $this->TicagaTickets->doesUserExist();
		
		if (!empty($this->post)) {

			$dept_id = $this->post["department_id"];
			$cid = $client_id ?? 0;
			$priority = $this->post['priority'];
			$subject = $this->post['summary'];
			$content = $this->post['details'];
			$cc = $this->post['cc'];
			$ccid = [];

			if (count($cc) > 1)
			{
				$ccid = explode(",",$cc);
			} else {
				$ccid = [0 => $cc];
			}

			$submitarray = ["department_id" => $dept_id, "client_id" => $cid, "priority" => $priority, "summary" => $subject, "details" => $details, "cc" => $ccid];
			$ticketsubmit = $this->TicagaTickets->open($submitarray);
		} else {
			$this->flashMessage('message', "Sorry, but no information was submitted to submit ticket. Please ensure all fields are filled in and try again.", null, false);
			$this->redirect($this->base_uri . 'plugin/ticaga_support/admin_main/index/');
		}
	}

	/**
     * Returns the view for a list of extensions
     */
    public function viewTicket()
    {
		$ticket_id = $this->get[0];
		$ticket = $this->TicagaTickets->get($ticket_id);

		if ($ticket == false)
		{
			$this->flashMessage('message', "Sorry, No Ticket by that ID Exists", null, false);
    		$this->redirect($this->base_uri . 'plugin/ticaga_support/admin_main/index');	
			$this->set('ticket', []);
		} else {
			$this->set('ticket', $ticket);
		}
    }
	
	public function addAPIInfo()
    {
		$this->set('company_id', Configure::get('Blesta.company_id'));
		
		// Add Category
        if (!empty($this->post)) {
			$company_id = Configure::get('Blesta.company_id');
			$api_key = $this->post['api_key'];
			$api_url = $this->post['api_url'];
			$arraypost = array("company_id" => $company_id, "api_key" => $api_key, "api_url" => $api_url);
            $result = $this->TicagaSettings->add($this->post);

            // Parse result
            if ($result) {
                $this->flashMessage('message', "Added API Info!", null, false);
                $this->redirect($this->base_uri . 'plugin/ticaga_support/admin_main/index/');
            } else {
                $this->setMessage('error', "Sorry, API Information could not be validated, try again.", false, null, false);
            }
        }
        
		// Set variables to the view
        $this->set('vars', (object) $this->post);
		$this->set('company_id', Configure::get('Blesta.company_id'));
        // return $this->renderAjaxWidgetIfAsync(
            // isset($this->get['sort']) ? true : (isset($this->get[1]) || isset($this->get[0]) ? false : null)
        // );
    }
}
