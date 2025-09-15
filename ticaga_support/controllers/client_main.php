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
		
		$this->staff_id = $this->Session->read('blesta_staff_id');
		
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
        $database_check = $this->Record->select()->from("ticaga_settings")->where("ticaga_settings.company_id", "=", Configure::get('Blesta.company_id'))->fetch();
        if ($database_check == false) {
            $this->flashMessage('error', "Error: The Ticaga API has not been provided.", null, false);
            $this->redirect($this->client_uri);
        }

        // Blesta ID of user currently logged in.
        $client_id  = $this->Session->read('blesta_client_id');
        // Does the user exist in the database?
		$userExists = $this->TicagaTickets->doesUserExist();

        // Get all departments
        $departments_all = $this->TicagaTickets->getDepartmentsAll();

		if ($userExists == '0')
		{
            if($client_id)
            {
                $this->flashMessage('error', "Please Sync your account with Ticaga.", null, false);
                $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/sync/');
            } else {
                // Could implement a guest submitting ticket at the moment re-direct to departments page.
                $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/departments');
            }
		} else {
            // Get tickets for this user
			$tickets = $this->TicagaTickets->getTicketsByUserID($userExists->ticaga_userid);
            $ticket_array = json_decode($tickets["response"], true);

            if (!$tickets)
			{
				$this->set('tickets', []);
				$this->set('depts', []);
			} else {
				$this->set('tickets', $ticket_array);
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
        $database_check = $this->Record->select()->from("ticaga_settings")->where("ticaga_settings.company_id", "=", Configure::get('Blesta.company_id'))->fetch();
        if ($database_check == false) {
            $this->flashMessage('error', "Error: The Ticaga API has not been provided.", null, false);
            $this->redirect($this->base_uri);
        }

		$client_id = $this->client_id;
		$userExists = $this->TicagaTickets->doesUserExist();
		
		if ($client_id != false)
		{
            if($userExists == '0')
            {
                $this->flashMessage('error', "Please Sync your account with Ticaga.", null, false);
                $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/sync/');
            }

		    $depts_clients = $this->TicagaTickets->getDepartmentsForClientsUseOnly();
            
			$this->set('depts', $depts_clients["departments"]);
			$this->set('client_id', $client_id);
		} else {
		    $depts_public = $this->TicagaTickets->getDepartmentsForPublicUseOnly();

			$this->set('depts', $depts_public["departments"]);
			$this->set('client_id', false);

			return $this->view->setView('client_main_departments', 'default');
			return $this->renderAjaxWidgetIfAsync(false);
		}
  	}
	
	/**
     * Returns the view for showing all users whether guest or client for submitting tickets to.
     */
    public function open()
  	{
        $database_check = $this->Record->select()->from("ticaga_settings")->where("ticaga_settings.company_id", "=", Configure::get('Blesta.company_id'))->fetch();
        if ($database_check == false) {
            $this->flashMessage('error', "Error: The Ticaga API has not been provided.", null, false);
            $this->redirect($this->client_uri);
        }

        // Client ID of user currently logged in.
		$client_id = $this->client_id ?: false;
        // Does the user exist in the database?
		$userExists = $this->TicagaTickets->doesUserExist();

        // Check if department exists
		$department_information = $this->TicagaTickets->getDepartmentsBySlug($this->get[0]) ?: '1';
        if($department_information == '1')
        {
            $this->flashMessage('error', "Sorry this department doesn't exist.", null, false);
            $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/departments');
        }

        //Split the array to remove unnecessary data
        $department_array = $department_information['department'][0];

        // Check if department allows high priority tickets
		$prioritystatuses = $this->TicagaTickets->getPrioritiesHighAllowed($this->get[0]);
        
		if ($department_array && $client_id == false && $department_array['is_disabled'] == 0)
		{
			if ($department_array['is_public'] == 1  && $department_array['is_disabled'] == 0)
			{
            
                $this->set('department_slug', $this->get[0]);
                $this->set('client_id', '0');
                $this->set('department_name', $department_array["department_name"]);
                $this->set('allow_high_priority', $department_array["allows_high_priority"]);
                $this->set('is_highpriority_allowed', $prioritystatuses);

                if (!empty($this->post)) {
                    $dept_id = $this->get[0];
                    $client_name = $this->post['public_name'];
                    $priority = $this->post['priority'] ?? 'none';
                    $message = $this->post['message'];
                    $email = $this->post['email'];
                    $cid = '0';
                    $cc = $this->post['cc'];
                    $ccid = [];

                    if (gettype($cc) == "array")
                    {
                        if (count($cc) > 1)
                        {
                            $ccid = explode(",",$cc);
                        } else {
                            $ccid = [0 => $cc];
                        }
                    } elseif (gettype($cc) == "string") {
                        $cctest = explode(",",$cc);
                        if (count($cctest) > 1)
                        {
                            $ccid = explode(",",$cc);
                        } else {
                            $ccid = [0 => $cc];
                        }
                    } else {
                            $ccid = [];
                    }
                 
                    //  var_dump($this->post["subject"]); die;
                    $submitarray = ["organize" => 'blesta', "department_slug" => $this->get[0], "client_id" => '0', "priority" => $priority, "subject" => $this->post["subject"], "message" => $message, "cc" => $ccid, 'client_email' => $email, 'public_name' => $client_name];
                    $ticketsubmit = $this->TicagaTickets->add($submitarray);
                    if ($ticketsubmit)
                    {
                        $this->flashMessage('message', "Success! Your ticket has been sent to our team.", null, false);
                        // Might want to redirect to the ticket view page instead.
                        $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/index');
                    } else {
                        $this->flashMessage('message', "Error: Sorry your ticket couldn't be submitted. Please try again", null, false);
                        $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/open/' . $this->get[0]);
                    }
                }
			} else {
				$this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/departments');
			}
		} else {
            if ($department_array && $client_id != false && $department_array['is_disabled'] == 0)
            {
                $this->set('department_slug', $this->get[0]);
                $this->set('client_id', $client_id);
                $this->set('department_name', $department_array["department_name"]);
                $this->set('allow_high_priority', $department_array["allows_high_priority"]);
                $this->set('is_highpriority_allowed', $prioritystatuses);
                    
                $client_var = $this->Record->select()->from("ticaga_billing")->where("ticaga_billing.billing_userid", "=", $client_id)->fetch();
                $client_name = $this->Clients->get($client_id)->first_name . " " . $this->Clients->get($client_id)->last_name;
                $client_email = $client_var->email_address;

                if (!empty($this->post)) {
                    $dept_id = $this->get[0];
                    $message = $this->post['message'];
                    $email = $client_email;
                    $cid = $client_var->ticaga_userid ?: 0;
                    $cc = $this->post['cc'];
                    $ccid = [];

                    if (gettype($cc) == "array")
                    {
                        if (count($cc) > 1)
                        {
                            $ccid = explode(",",$cc);
                        } else {
                            $ccid = [0 => $cc];
                        }
                    } elseif (gettype($cc) == "string") {
                        $cctest = explode(",",$cc);
                        if (count($cctest) > 1)
                        {
                            $ccid = explode(",",$cc);
                        } else {
                            $ccid = [0 => $cc];
                        }
                    } else {
                        $ccid = [];
                    }
                    $submitarray = ["organize" => 'blesta', "department_slug" => $this->get[0], "client_id" => $cid, "priority" => $this->post['priority'], "subject" => $this->post["subject"], "message" => $message, "cc" => $ccid, 'client_email' => $email, 'public_name' => $client_name];
                    $ticketsubmit = $this->TicagaTickets->add($submitarray);

                    if ($ticketsubmit != false)
                    {
                        $this->flashMessage('message', "Success! Your ticket has been sent to our team.", null, false);
                        // Might want to redirect to the ticket view page instead.
                        $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/index');
                    } else {
                        $this->flashMessage('error', "Failure Submitting Ticket", null, false);
                        $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/departments');
                    }
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
     * Returns the view for showing syncing client account.
     */
    public function sync()
  	{
        // Client ID of user currently logged in.
		$client_id = $this->client_id ?: false;
        // Does the user exist in the database?
		$userExists = $this->TicagaTickets->doesUserExist();
		if ($client_id != '0' && $userExists == '0')
        {
            if (!empty($this->post))
            {
                $result = $this->TicagaTickets->connectAccounts($this->post['email_address'], $this->post['ticaga_id']);
                
                if ($result)
                {
                    $this->flashMessage('message', "Your Blesta account has now been synced with Ticaga.", null, false);
                    $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/index');
                } else {
                    $this->flashMessage('error', "Sorry your account hasn't been synced, please check the information again.", null, false);
                    $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/sync/');
                }
            } else {
                return $this->view->setView('client_main_sync', 'default');
            }
		 } else {
            return $this->view->setView('client_main_sync', 'default');
            return $this->renderAjaxWidgetIfAsync(false);
        }
  	}
  
  
  
  	/**
     * Returns the view for a list of extensions
     */
    public function view()
    {
        // Check the ticket ID is valid and return the ticket information.
        $ticket_information = $this->TicagaTickets->getTicketByCode($this->get[0]);

        // Does the user exist in the database?
		$ticketBelongToClient = $this->TicagaTickets->doesTicketBelongToClient($ticket_information["ticket"]->user_id);
        
        if ($ticket_information["ticket"] == null || $ticketBelongToClient == false && $ticket_information["ticket"]->user_id != '0')
		{
			$this->flashMessage('error', "Sorry this ticket hasn't been found on our system. Please contact our support.", null, false);
			$this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/index');	
		}
		
            if (!empty($this->post)) {
                $submitarray = [
                    "user_id" => $ticket_information['ticket']->user_id ?: '0', 
                    "ticket_number" => $this->get[0], 
                    "content" => $this->post['response_content'], 
                    "is_note" => '0', 
                    "employee_response" => '0', 
                    "organize" => 'blesta'
                ];
                $response_submit = $this->TicagaTickets->addReply($this->get[0], $submitarray);
                if ($response_submit != false)
                {
                    $this->flashMessage('message', "Ticket Updated successufully.", null, false);
                    $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/view/' .  $this->get[0]);	
                } else {
                    $this->flashMessage('error', "Sorry, this ticket can't be updated.", null, false);
                    $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/view/' . $this->get[0]);
                }
            }
		
            $this->set('ticket', $ticket_information["ticket"]);

            $replies_information = $this->TicagaTickets->getReplies($this->get[0]);
            $this->set('replies', json_decode($replies_information, true));
            
            return $this->view->setView('client_main_view', 'default');
            return $this->renderAjaxWidgetIfAsync(false);
    }
}