<?php
/**
 * Ticaga_Support client_main controller
 *
 * @link https://ticaga.com/ Ticaga
 */
class ClientMain extends TicagaSupportController
{
    /**
     * Setup
     */
    public function preAction()
    {
        parent::preAction();
		
		// Redirect if the plugin is not installed
        if (!$this->PluginManager->isInstalled('ticaga_support', $this->company_id)) {
            $this->redirect($this->client_uri);
        }

        $this->structure->set('page_title', Language::_('ClientMain.index.page_title', true));
		
		$this->uses(['Staff', 'Companies', 'TicagaSupport.TicagaTickets', 'TicagaSupport.TicagaSettings', 'Input', 'Record', 'Session', 'Clients']);
		
		$this->client_id = $this->Session->read('blesta_client_id');
		
		 // Fetch contact that is logged in, if any
        if (!isset($this->Contacts)) {
            $this->uses(['Contacts']);
        }
        $this->contact = $this->Contacts->getByUserId($this->Session->read('blesta_id'), $this->client_id);
    }
	
	/**
     * Returns the view for a list of tickets
     */
    public function index()
    {
		$client_id = $this->client_id;
		$userExists = $this->TicagaTickets->doesUserExist();

		if ($userExists == false && $client_id == false)
		{
			$this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/departments/');
		} else if($userExists == false && $client_id != false) {
			$tickets = $this->TicagaTickets->getTicketsByUserID($client_id);
			$departments_all = $this->TicagaTickets->getDepartmentsAll();
			if ($tickets == false)
			{
				$this->set('tickets', []);
				$this->set('depts', []);
			} else {
				$this->set('tickets', $tickets);
				$this->set('depts', $departments_all);
			}
		} else {
			$tickets = $this->TicagaTickets->getTicketsByUserID($client_id);

			if ($tickets == false)
			{
				$this->set('tickets', []);
				$this->set('depts', []);
			} else {
				$this->set('tickets', $tickets);
				$this->set('depts', $departments_all);
			}
		}
		return $this->renderAjaxWidgetIfAsync(false);
   }
   
   /**
     * Returns the view for showing guest departments for submitting tickets to.
     */
    public function departments()
  	{
		$client_id = $this->client_id;
		$userExists = $this->TicagaTickets->doesUserExist();
		$depts_public = $this->TicagaTickets->getDepartmentsForPublicUseOnly();
		$depts_clients = $this->TicagaTickets->getDepartmentsForClientsUseOnly();
		
		if ($client_id != false)
		{
			$deptmerged = array_merge($depts_public, $depts_clients);
			$this->set('depts', $deptmerged);
			$this->set('client_id', $client_id);
			return $this->view->setView('client_main_clientticketchoosedept', 'default');
		    return $this->renderAjaxWidgetIfAsync(false);
		} else {
			$this->set('depts', $depts_public);
			$this->set('client_id', false);
			return $this->view->setView('client_main_guestticketchoosedept', 'default');
			return $this->renderAjaxWidgetIfAsync(false);
		}
  	}
   
   /**
     * Returns the view for showing guest departments for submitting tickets to.
     */
    public function guestTicketChooseDept()
  	{
		$client_id = $this->client_id;
		$userExists = $this->TicagaTickets->doesUserExist();
		$depts = $this->TicagaTickets->getDepartmentsForPublicUseOnly();
		
		if ($depts != false)
		{
			$this->set('depts', $depts);
			$this->set('client_id', $client_id);
		} else {
			$this->set('depts', []);
			$this->set('client_id', false);
		}
		return $this->view->setView('client_main_guestticketchoosedept', 'default');
		return $this->renderAjaxWidgetIfAsync(false);
  	}
  
  /**
     * Returns the view for showing guest departments for submitting tickets to.
     */
    public function guestTicketOpen()
  	{
		$client_id = $this->client_id ?? false;
		$userExists = $this->TicagaTickets->doesUserExist();
		$deptinfo = $this->TicagaTickets->getDepartmentsByIDNonArray($this->get[0]);
		$prioritystatuses = $this->TicagaTickets->getPrioritiesHighAllowed($this->get[0]);
		
		if ($deptinfo)
		{
			$deptjsondec = $deptinfo;
			if ($deptjsondec[0]->is_public == 1)
			{
				$this->set('department_id', $this->get[0]);
				$this->set('client_id', $client_id);
				$this->set('is_highpriority_allowed', $prioritystatuses);

				if (!empty($this->post)) {
					$dept_id = $this->get[0];
					$priority = $this->post['priority'];
					$subject = $this->post['summary'];
					$content = $this->post['details'];
					$cid = 0;
					$cc = $this->post['cc'];
					$ccid = [];

					if (count($cc) > 1)
					{
						$ccid = explode(",",$cc);
					} else {
						$ccid = [0 => $cc];
					}

					$submitarray = ["department_id" => $dept_id, "client_id" => $cid, "priority" => $priority, "summary" => $subject, "details" => $details, "cc" => $ccid];
					$ticketsubmit = $this->TicagaTickets->add($submitarray);	
				}
			} else {
				$this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/departments');
			}
		}

		return $this->view->setView('client_main_guestticketopen', 'default');
		return $this->renderAjaxWidgetIfAsync(false);
  	}
	
	/**
     * Returns the view for showing all users whether guest or client for submitting tickets to.
     */
    public function submitTicket()
  	{
		$client_id = $this->client_id ?? false;
		$userExists = $this->TicagaTickets->doesUserExist();
		$deptinfo = $this->TicagaTickets->getDepartmentsByIDNonArray($this->get[0]);
		$prioritystatuses = $this->TicagaTickets->getPrioritiesHighAllowed($this->get[0]);
		
		if ($deptinfo && $client_id == false)
		{
			$deptjsondec = $deptinfo;
			if ($deptjsondec[0]->is_public == 1)
			{
				$this->set('department_id', $this->get[0]);
				$this->set('client_id', $client_id);
				$this->set('is_highpriority_allowed', $prioritystatuses);

				if (!empty($this->post)) {
					$dept_id = $this->get[0];
					$priority = $this->post['priority'];
					$subject = $this->post['summary'];
					$content = $this->post['details'];
					$email = $this->post['email'];
					$cid = 0;
					$cc = $this->post['cc'];
					$ccid = [];

					if (count($cc) > 1)
					{
						$ccid = explode(",",$cc);
					} else {
						$ccid = [0 => $cc];
					}

					$submitarray = ["department_id" => $dept_id, "client_id" => $cid, "priority" => $priority, "summary" => $subject, "details" => $content, "cc" => $ccid, 'client_email' => $email];
					$ticketsubmit = $this->TicagaTickets->add($submitarray);	
				}
			} else {
				$this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/departments');
			}
		} else {
		if ($deptinfo && $client_id != false)
		{
			$deptjsondec = $deptinfo;

			if ($client_id != false)
			{
				$this->set('department_id', $this->get[0]);
				$this->set('client_id', $client_id);
				$this->set('is_highpriority_allowed', $prioritystatuses);
				$client_var = $this->Clients->get($client_id);
				$client_email = $client_var->email;

				if (!empty($this->post)) {
					$dept_id = $this->get[0];
					$priority = $this->post['priority'];
					$subject = $this->post['summary'];
					$content = $this->post['details'];
					$email = $client_email;
					$cid = 0;
					$cc = $this->post['cc'];
					$ccid = [];

					if ($cc != "" || !empty($cc))
					{
						$ccid = explode(",",$cc);
					} else {
						$ccid = [0 => $cc];
					}

					$submitarray = ["department_id" => $dept_id, "client_id" => $cid, "priority" => $priority, "summary" => $subject, "details" => $content, "cc" => $ccid, 'client_email' => $email];
					$ticketsubmit = $this->TicagaTickets->add($submitarray);
					if ($ticketsubmit != false)
					{
					die(print_r($ticketsubmit,true));
					$this->flashMessage('message', "Ticket Submitted", null, false);
					$this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/index');	
					} else {
						die(print_r($ticketsubmit,true));
					}
				}
			} else {
				$this->set('depts', []);
				$this->set('client_id', false);
			}
			return $this->view->setView('client_main_submitticket', 'default');
			return $this->renderAjaxWidgetIfAsync(false);
		} else {
			$this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/departments');
		}
		return $this->view->setView('client_main_submitticket', 'default');
		return $this->renderAjaxWidgetIfAsync(false);
		}
  	}
  
  	/**
     * Returns the view for showing client departments for submitting tickets to.
     */
    public function clientTicketChooseDept()
  	{
		$client_id = $this->client_id;
		$userExists = $this->TicagaTickets->doesUserExist();
		$depts_public = $this->TicagaTickets->getDepartmentsForPublicUseOnly();
		$depts_clients = $this->TicagaTickets->getDepartmentsForClientsUseOnly();
	
		if ($client_id != false)
		{
			$deptmerged = array_merge($depts_public, $depts_clients);
			$this->set('depts', $deptmerged);
			$this->set('client_id', $client_id);
		} else {
			$this->set('depts', $depts_public);
			$this->set('client_id', false);
		}
		return $this->view->setView('client_main_clientticketchoosedept', 'default');
		return $this->renderAjaxWidgetIfAsync(false);
	}
  
  	/**
     * Returns the view for showing syncing client account.
     */
    public function syncClientAccount()
  	{
		$client_id = $this->client_id;
		$userExists = $this->TicagaTickets->doesUserExist();
		return $this->view->setView('client_main_syncclientaccount', 'default');
		return $this->renderAjaxWidgetIfAsync(false);
  	}
  
 	/**
     * Returns the view for showing client departments for submitting tickets to.
     */
    public function clientTicketOpen()
  	{
		$client_id = $this->Session->read('blesta_client_id') ?? false;
		$userExists = $this->TicagaTickets->doesUserExist();
		$deptinfo = $this->TicagaTickets->getDepartmentsByID($this->get[0]);
		$prioritystatuses = $this->TicagaTickets->getPrioritiesHighAllowed($this->get[0]);
		
		if ($deptinfo)
		{
			$deptjsondec = json_decode($deptinfo['response']);

			if ($client_id != false)
			{
				$this->set('department_id', $this->get[0]);
				$this->set('client_id', $client_id);
				$this->set('is_highpriority_allowed', $prioritystatuses);

				if (!empty($this->post)) {
					$dept_id = $this->get[0];
					$priority = $this->post['priority'];
					$subject = $this->post['summary'];
					$content = $this->post['details'];
					$cid = 0;
					$cc = $this->post['cc'];
					$ccid = [];

					if ($cc != "" || !empty($cc))
					{
						$ccid = explode(",",$cc);
					} else {
						$ccid = [0 => $cc];
					}

					$submitarray = ["department_id" => $dept_id, "client_id" => $cid, "priority" => $priority, "summary" => $subject, "details" => $details, "cc" => $ccid];
					$ticketsubmit = $this->TicagaTickets->add($submitarray);
					$this->flashMessage('message', "Ticket Submitted", null, false);
					$this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/index');
				}
			} else {
				$this->set('depts', []);
				$this->set('client_id', false);
			}
		}
		return $this->view->setView('client_main_clientticketopen', 'default');
		return $this->renderAjaxWidgetIfAsync(false);
  	}
  
  
  
  	/**
     * Returns the view for a list of extensions
     */
    public function clientViewTicket()
    {
		$ticket_id = $this->get[0];
		$ticket = $this->TicagaTickets->get($ticket_id);
		$ticketBelongToClient = $this->TicagaTickets->doesTicketBelongToClient($ticket_id);
	
		if ($ticket == false || $ticketBelongToClient == false)
		{
			$this->flashMessage('message', "Sorry, No Ticket by that ID Exists or you have no rights to view it.", null, false);
			$this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/index');	
			$this->set('ticket', []);
		} else {
			$this->set('ticket', $ticket);
		}
		
		return $this->view->setView('client_main_clientticketview', 'default');
		return $this->renderAjaxWidgetIfAsync(false);
    }
}