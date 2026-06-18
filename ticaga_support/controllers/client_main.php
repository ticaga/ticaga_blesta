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

		// Ensure a logged-in customer has a linked Ticaga account. linkClient() is
		// idempotent (skips if already linked) and otherwise finds-or-creates the
		// Ticaga customer via customers/link, so tickets are attributed to the
		// correct Ticaga customer id and customer-only departments are available.
		if ($this->client_id) {
			try {
				$this->TicagaTickets->linkClient($this->client_id);
			} catch (Throwable $e) {
				// Non-fatal: the guest flow remains as a fallback if linking can't complete
			}
		}

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
            // Guests and customers not yet linked to Ticaga are sent to the departments
            // page, where they can open a ticket as a guest (no forced account sync).
            $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/departments');
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
		$is_linked = ($client_id != false && $userExists != '0');

		if ($is_linked)
		{
		    // Linked customer: show the departments available to their account
		    $depts_clients = $this->TicagaTickets->getDepartmentsForClientsUseOnly();

			$this->set('depts', $depts_clients["departments"]);
			$this->set('client_id', $client_id);
		} else {
		    // Guests and not-yet-linked customers: show public departments and let
		    // them open a ticket as a guest.
		    $depts_public = $this->TicagaTickets->getDepartmentsForPublicUseOnly();

			$this->set('depts', $depts_public["departments"]);
			$this->set('client_id', false);

			// Logged-in but unlinked: warn that tickets won't be tied to their account yet
			if ($client_id != false) {
				$this->set('unlinked_notice', true);
			}
		}

		return $this->view->setView('client_main_departments', 'default');
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
        // Is the customer linked to a Ticaga account?
		$userExists = $this->TicagaTickets->doesUserExist();
		$is_linked = ($client_id != false && $userExists != '0');

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

        // The department must exist and be enabled
        if (!$department_array || $department_array['is_disabled'] != 0) {
            $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/departments');
        }

		if ($is_linked)
		{
            // ===== Linked customer: the ticket is tied to their Ticaga account =====
            $this->set('department_slug', $this->get[0]);
            $this->set('client_id', $client_id);
            $this->set('department_name', $department_array["department_name"]);
            $this->set('allow_high_priority', $department_array["allows_high_priority"]);
            $this->set('is_highpriority_allowed', $prioritystatuses);
            $this->set('business_hours', $department_array['business_hours'] ?? null);
            $this->set('cc_enabled', !isset($department_array['cc_enabled']) || $department_array['cc_enabled']);
            $this->set('custom_fields', $department_array['custom_fields'] ?? []);

            $client_var = $this->Record->select()->from("ticaga_billing")->where("ticaga_billing.billing_userid", "=", $client_id)->fetch();
            $blesta_client = $this->Clients->get($client_id);
            $client_name = $blesta_client->first_name . " " . $blesta_client->last_name;
            $client_email = $client_var->email_address;

            if (!empty($this->post)) {
                $message = $this->post['message'];
                $email = $client_email;
                $cid = $client_var->ticaga_userid ?: 0;
                $ccid = $this->parseCarbonCopy($this->post['cc'] ?? '');

                $submitarray = ["organize" => 'blesta', "department_slug" => $this->get[0], "client_id" => $cid, "priority" => $this->post['priority'], "subject" => $this->post["subject"], "message" => $message, "cc" => $ccid, 'client_email' => $email, 'public_name' => $client_name, 'custom_fields' => $this->post['custom_fields'] ?? []];
                $ticketsubmit = $this->TicagaTickets->add($submitarray);

                if ($ticketsubmit != false) {
                    $this->flashMessage('message', "Success! Your ticket has been sent to our team.", null, false);
                    $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/index');
                } else {
                    $error = $this->TicagaTickets->getLastError();
                    $this->flashMessage('error', $error ? ("Failure submitting ticket: " . $error) : "Failure Submitting Ticket", null, false);
                    $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/departments');
                }
            }

            return $this->view->setView('client_main_submitticket', 'default');
		}
		else
		{
            // ===== Guest, or logged-in customer not yet linked: a public ticket =====
            // Only public departments may be used for guest submissions
            if ($department_array['is_public'] != 1) {
                $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/departments');
            }

            // Pre-fill name/email from Blesta for a logged-in but unlinked customer
            $prefill_name = '';
            $prefill_email = '';
            if ($client_id != false) {
                $blesta_client = $this->Clients->get($client_id);
                if ($blesta_client) {
                    $prefill_name = trim($blesta_client->first_name . ' ' . $blesta_client->last_name);
                    $prefill_email = $blesta_client->email;
                }
                $this->set('unlinked_notice', true);
            }

            $this->set('department_slug', $this->get[0]);
            $this->set('client_id', '0');
            $this->set('department_name', $department_array["department_name"]);
            $this->set('allow_high_priority', $department_array["allows_high_priority"]);
            $this->set('is_highpriority_allowed', $prioritystatuses);
            $this->set('prefill_name', $prefill_name);
            $this->set('prefill_email', $prefill_email);
            $this->set('business_hours', $department_array['business_hours'] ?? null);
            $this->set('cc_enabled', !isset($department_array['cc_enabled']) || $department_array['cc_enabled']);
            $this->set('custom_fields', $department_array['custom_fields'] ?? []);

            if (!empty($this->post)) {
                $priority = $this->post['priority'] ?? 'none';
                $message = $this->post['message'];
                $email = !empty($this->post['email']) ? $this->post['email'] : $prefill_email;
                $client_name = !empty($this->post['public_name']) ? $this->post['public_name'] : $prefill_name;
                $ccid = $this->parseCarbonCopy($this->post['cc'] ?? '');

                $submitarray = ["organize" => 'blesta', "department_slug" => $this->get[0], "client_id" => '0', "priority" => $priority, "subject" => $this->post["subject"], "message" => $message, "cc" => $ccid, 'client_email' => $email, 'public_name' => $client_name, 'custom_fields' => $this->post['custom_fields'] ?? []];
                $ticketsubmit = $this->TicagaTickets->add($submitarray);

                if ($ticketsubmit) {
                    $reference = $this->extractTicketReference($ticketsubmit);
                    $success = "Success! Your ticket has been sent to our team.";
                    if ($reference) {
                        $success .= " Your reference is " . $reference . " — please keep it to track your ticket.";
                    }
                    $this->flashMessage('message', $success, null, false);
                    $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/index');
                } else {
                    $error = $this->TicagaTickets->getLastError();
                    $this->flashMessage('error', $error ? ("Sorry your ticket couldn't be submitted: " . $error) : "Sorry your ticket couldn't be submitted. Please try again.", null, false);
                    $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/open/' . $this->get[0]);
                }
            }

            return $this->view->setView('client_main_open', 'default');
		}
  	}

	/**
	 * Normalises the carbon-copy field (string or array) into an array of values.
	 *
	 * @param string|array $cc The raw carbon-copy input
	 * @return array A list of cc values
	 */
	private function parseCarbonCopy($cc)
	{
		if (is_array($cc)) {
			return count($cc) > 1 ? array_values($cc) : [0 => reset($cc)];
		}
		if (is_string($cc) && trim($cc) !== '') {
			return explode(',', $cc);
		}
		return [];
	}

	/**
	 * Extracts a public reference (hash, falling back to id) from a ticket
	 * creation response so a guest can track their ticket.
	 *
	 * @param mixed $ticketsubmit The decoded API response from TicagaTickets::add()
	 * @return string|null The reference, or null if none could be determined
	 */
	private function extractTicketReference($ticketsubmit)
	{
		if (is_object($ticketsubmit) && isset($ticketsubmit->id)) {
			$ticket = $ticketsubmit->id;
			if (is_object($ticket)) {
				return $ticket->public_hash ?? ($ticket->id ?? null);
			}
			return $ticket;
		}
		return null;
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

            // The ticket owner's Ticaga customer id (0 for guest/public tickets)
            $ticket_user_id = $ticket_information['ticket']->user_id ?: '0';

            if (!empty($this->post)) {
                // A star rating submission carries a 'rating' field; a reply
                // carries 'response_content'. Handle them separately.
                if (isset($this->post['rating'])) {
                    if ($ticket_information['ratings_enabled'] && $ticket_user_id != '0') {
                        $rated = $this->TicagaTickets->rateTicket($this->get[0], $this->post['rating'], $ticket_user_id);
                        if ($rated) {
                            $this->flashMessage('message', "Thanks! Your rating has been saved.", null, false);
                        } else {
                            $this->flashMessage('error', "Sorry, your rating couldn't be saved.", null, false);
                        }
                    }
                    $this->redirect($this->base_uri . 'plugin/ticaga_support/client_main/view/' . $this->get[0]);
                }

                $submitarray = [
                    "user_id" => $ticket_user_id,
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
            $this->set('custom_fields', $ticket_information['custom_fields'] ?? null);
            // Only offer rating on the customer's own (non-guest) tickets
            $this->set('ratings_enabled', !empty($ticket_information['ratings_enabled']) && $ticket_user_id != '0');
            $this->set('statuses', $this->TicagaTickets->getStatuses());

            $replies_information = $this->TicagaTickets->getReplies($this->get[0]);
            $this->set('replies', json_decode($replies_information, true));

            return $this->view->setView('client_main_view', 'default');
            return $this->renderAjaxWidgetIfAsync(false);
    }
}