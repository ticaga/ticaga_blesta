<?php
/**
 * TicagaTickets model
 *
 * @package ticaga
 * @subpackage ticaga.plugins.ticagasupport
 * @copyright Copyright (c) 2024, Ticaga
 * @license http://ticaga.com/license/ The Ticaga License Agreement
 * @link http://ticaga.com/ Ticaga
 */
class TicagaTickets extends TicagaSupportModel
{
    /**
     * The system-level staff ID
     */
    private $system_staff_id = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        Configure::load('mime', dirname(__FILE__) . DS . '..' . DS . 'config' . DS);
        Language::loadLang('ticaga_tickets', null, PLUGINDIR . 'ticaga_support' . DS . 'language' . DS);
		// Load components
        Loader::loadComponents($this, ['Input', 'Record', 'Session']);
		// Load models
        Loader::loadModels($this, ['Staff', 'Companies', 'TicagaSupport.TicagaSettings', 'Clients']);
    }
	
	public function getAPIInfoByCompanyId(){
        return $this->Record->select()->from("ticaga_blesta_settings")->where("ticaga_blesta_settings.company_id", "=", Configure::get('Blesta.company_id'))->fetch();
    }
	
	public function getAPIInfoByCompanyIdCount(){
        $res = $this->Record->select()->from("ticaga_blesta_settings")->where("ticaga_blesta_settings.company_id", "=", Configure::get('Blesta.company_id'))->numResults();
		if ($res > 0)
		{
			return true;
		} else {
			return false;
		}
    }
	
    // Function to get the client ip address
    public function get_client_ip_server() {
    $ipaddress = '';
	if (isset($_SERVER['CF-Connecting-IP']))
	{
		$ipaddress = $_SERVER['CF-Connecting-IP'];
	} else if (isset($_SERVER['REMOTE_ADDR'])) {
		$ipaddress = $_SERVER['REMOTE_ADDR'];
	} else {
		$ipaddress = "UNKNOWN";
	}
    return $ipaddress;
    }

    /**
     * Adds a support ticket
     *
     * @param array $vars A list of ticket vars, including:
     *  - department_id The ID of the department to assign this ticket
     *  - staff_id The ID of the staff member this ticket is assigned to (optional)
     *  - service_id The ID of the service this ticket is related to (optional)
     *  - client_id The ID of the client this ticket is assigned to (optional)
     *  - email The email address that a ticket was emailed in from (optional)
     *  - summary A brief title/summary of the ticket issue
     *  - priority The ticket priority (i.e. "emergency", "critical", "high", "medium", "low") (optional, default "low")
     *  - status The status of the ticket
     *  (i.e. "open", "awaiting_reply", "in_progress", "on_hold", "closed", "trash") (optional, default "open")
     *  - custom_fields An array containing the ticket custom fields, where the key is the field id
     * @param bool $require_email True to require the email field be given,
     *  false otherwise (optional, default false)
     * @return mixed The ticket ID, or null on error
     */
    public function add(array $vars, $require_email = false)
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$department_id = $vars['department_id'];
		$client_id = $vars['client_id'] ?? $vars['client_email'];
		$priority = $vars['priority'] ?? "low";
		$details = $vars["details"] ?? "";
		$email = $vars["client_email"] ?? "";
		$name = $vars["public_name"] ?? "";
		$cc = $vars["cc"] ?? "null";
		$ipaddress = $this->get_client_ip_server();
		
		if ($cc != "null")
		{
		$ccid = implode(",",$cc);
		$callvars = array('user_id' => $client_id, "subject" => $vars['summary'], "priority" => $priority, "content" => $details, "cc" => $ccid, "assigned" => "0", "department_id" => $department_id, "ip_address" => $ipaddress, 'public_email' => $email, 'public_name' => $name);	
		} else {
		$callvars = array('user_id' => $client_id, "subject" => $vars['summary'], "priority" => $priority, "content" => $details, "assigned" => "0", "department_id" => $department_id, "ip_address" => $ipaddress, 'public_email' => $email, 'public_name' => $name);
		}
		
		$resp = $this->TicagaSettings->callAPIPost("tickets/open/" . $department_id,$callvars, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		return json_decode($resp['response']);	
		} else {
		return false;
		}
    }

    /**
     * Updates a support ticket
     *
     * @param int $ticket_id The ID of the ticket to update
     * @param array $vars A list of ticket vars, including (all optional):
     *  - department_id The department to reassign the ticket to
     *  - staff_id The ID of the staff member to assign the ticket to
     *  - service_id The ID of the client service this ticket relates to
     *  - client_id The ID of the client this ticket is to be assigned to (can only be set if it is currently null)
     *  - summary A brief title/summary of the ticket issue
     *  - priority The ticket priority (i.e. "emergency", "critical", "high", "medium", "low")
     *  - status The status of the ticket (i.e. "open", "awaiting_reply", "in_progress", "on_hold", "closed", "trash")
     *  - by_staff_id The ID of the staff member performing the edit
     *      (optional, defaults to null to signify the edit is performed by the client)
     *  - custom_fields An array containing the ticket custom fields, where the key is the field id
     * @param bool $log True to update the ticket for any loggable changes,
     *  false to explicitly not log changes (optional, default true)
     * @return stdClass An stdClass object representing the ticket (without replies)
     */
    public function edit($ticket_id, array $vars, $log = true)
    {
		//not implemented yet
	   return false;
    }

    /**
     * Reassigns tickets to the given client
     *
     * @param array $vars A list of input variables including:
     *  - ticket_ids An array of ticket IDs to reassign
     *  - client_id The client to reassign the ticket to
     *  - staff_id The staff performing this action
     */
    public function reassignTickets(array $vars)
    {
	   //not implemented yet.
       return false;
    }

    /**
     * Updates multiple support tickets at once
     * @see TicagaTickets::edit()
     *
     * @param array $ticket_ids An array of ticket IDs to update
     * @param array $vars An array consisting of arrays of ticket vars whose index refers to the
     *  index of the $ticket_ids array representing the vars of the specific ticket to update;
     *  or an array of vars to apply to all tickets; each including (all optional):
     *  - department_id The department to reassign the ticket to
     *  - staff_id The ID of the staff member to assign the ticket to
     *  - service_id The ID of the client service this ticket relates to
     *  - client_id The ID of the client this ticket is to be assigned to (can only be set if it is currently null)
     *  - summary A brief title/summary of the ticket issue
     *  - priority The ticket priority (i.e. "emergency", "critical", "high", "medium", "low")
     *  - status The status of the ticket (i.e. "open", "awaiting_reply", "in_progress", "on_hold", "closed", "trash")
     *  - by_staff_id The ID of the staff member performing the edit
     *      (optional, defaults to null to signify the edit is performed by the client)
     */
    public function editMultiple(array $ticket_ids, array $vars)
    {
		//Not Implemented Yet
        return false;
    }

    /**
     * Closes a ticket and logs that it has been closed
     *
     * @param int $ticket_id The ID of the ticket to close
     * @param int $staff_id The ID of the staff that closed the ticket
     *  (optional, default null if client closed the ticket)
     */
    public function close($ticket_id, $staff_id = null)
    {
        // Update the ticket to closed
        $vars = ['status' => 'closed', 'date_closed' => date('c')];

        // Set who closed the ticket
        if ($staff_id !== null) {
            $vars['by_staff_id'] = $staff_id;
        }

        // Set the current assigned ticket staff member as the staff member on edit, so that it does not get removed
        $ticket = $this->get($ticket_id, false);
        if ($ticket) {
            $vars['staff_id'] = $ticket->staff_id;
        }

        //not implemented yet
		return false;
    }

    /**
     * Closes all open tickets (not "in_progress") based on the department settings
     *
     * @param int $department_id The ID of the department whose tickets to close
     */
    public function closeAllByDepartment($department_id)
    {
        Loader::loadModels($this, ['Companies', 'TicagaSupport.TicagaDepartments']);

        $department = $this->TicagaDepartments->get($department_id);
        if ($department && $department->close_ticket_interval !== null) {
            $reply = '';

            $company = $this->Companies->get($department->company_id);
            $hostname = isset($company->hostname) ? $company->hostname : '';
            $last_reply_date = $this->dateToUtc(
                date('c', strtotime('-' . abs($department->close_ticket_interval) . ' minutes'))
            );

            //not implemented yet
			return false;
            // Close the tickets
            foreach ($tickets as $ticket) {
                // Add any reply and email, and close the ticket
                $this->staffReplyEmail($reply, $ticket->id, $hostname, $this->system_staff_id);
                $this->close($ticket->id, $this->system_staff_id);
            }
        }
    }

    /**
     * Permanently deletes the given tickets and everything associated with them
     *
     * @param array $ticket_ids A list of tickets to delete
     */
    public function delete(array $ticket_ids)
    {
		//not implemented yet in Ticaga API
       return false;
    }

    /**
     * Adds a reply to a ticket. If ticket data (e.g. department_id, status, priority, summary) have changed
     * then this will also invoke TicagaTickets::edit() to update the ticket, and record any log entries.
     *
     * Because of this functionality, this method is assumed to (and should) already be in a transaction when called,
     * and TicagaTickets::edit() should not be called separately.
     *
     * @param int $ticket_id The ID of the ticket to reply to
     * @param array $vars A list of reply vars, including:
     *  - staff_id The ID of the staff member this reply is from (optional)
     *  - client_id The ID of the client this reply is from (optional)
     *  - contact_id The ID of a client's contact that this reply is from (optional)
     *  - type The type of reply (i.e. "reply, "note", "log") (optional, default "reply")
     *  - details The details of the ticket (optional)
     *  - department_id The ID of the ticket department (optional)
     *  - summary The ticket summary (optional)
     *  - priority The ticket priority (optional)
     *  - status The ticket status (optional)
     *  - ticket_staff_id The ID of the staff member the ticket is assigned to (optional)
     *  - custom_fields An array containing the ticket custom fields, where the key is the field id
     * @param array $files A list of file attachments that matches the global FILES array,
     *  which contains an array of "attachment" files
     * @param bool $new_ticket True if this reply is apart of ticket being created, false otherwise (default false)
     * @return int The ID of the ticket reply on success, void on error
     */
    public function addReply($ticket_id, array $vars, array $files = null, $new_ticket = false)
    {
        $vars['ticket_id'] = $ticket_id;
        $vars['date_added'] = date('c');
        if (!isset($vars['type'])) {
            $vars['type'] = 'reply';
        }

        // Determine whether or not options have changed that need to be logged
        $log_options = [];
        // "status" should be the last element in case it is set to closed, so it will be the last log entry added
        $loggable_fields = ['department_id' => 'department_id', 'ticket_staff_id' => 'staff_id', 'summary' => 'summary',
            'priority' => 'priority', 'status' => 'status'];

        if (!$new_ticket
            && (
                isset($vars['department_id'])
                || isset($vars['priority']) || isset($vars['status'])
            )
        ) {
            if (($ticket = $this->get($ticket_id, false))) {
                // Determine if any log replies need to be made
                foreach ($loggable_fields as $key => $option) {
                    // Save to be logged iff the field has been changed
                    if (isset($vars[$key]) && property_exists($ticket, $option) && $ticket->{$option} != $vars[$key]) {
                        $log_options[] = $key;
                    }
                }
            }
        }

        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$department_id = $vars['department_id'];
		$client_id = $vars['client_id'] ?? $vars['staff_id'];
		$details = $vars["details"] ?? "";
		$isnote = $vars["isnote"] ?? "false";
		$ipaddress = $this->get_client_ip_server();
		if ($vars['staff_id'] != null)
		{
		$callvars = array('response_user_id' => $client_id, "ticket_number" => $ticket_id, "response_content" => $details, "is_note" => $isnote, "agent_response" => "1");
		
		$resp = $this->TicagaSettings->callAPIPost("responses/reply",$callvars, $apiURL,$apiKey);
		
        return $resp;	
		} else {
		$callvars = array('response_user_id' => $client_id, "ticket_number" => $ticket_id, "response_content" => $details, "is_note" => $isnote, "agent_response" => "0");
		
		$resp = $this->TicagaSettings->callAPIPost("responses/reply",$callvars, $apiURL,$apiKey);
		
        return $resp;
		}
    }

    /**
     * Retrieves the total number of tickets in the given status assigned to the given staff/client
     *
     * @param string $status The status of the support tickets
     *  ('open', 'awaiting_reply', 'in_progress', 'on_hold', 'closed', 'trash')
     * @param int $staff_id The ID of the staff member assigned to the tickets or associated departments (optional)
     * @param int $client_id The ID of the client assigned to the tickets (optional)
     * @param array $filters A list of parameters to filter by, including:
     *
     *  - staff_id The ID of the staff member assigned to the tickets or associated departments (optional)
     *  - client_id The ID of the client assigned to the tickets (optional)
     *  - ticket_id The ID of a specific ticket to fetch
     *  - ticket_number The (partial) ticket number on which to filter tickets
     *  - priority The priority on which to filter tickets
     *  - department_id The department ID on which to filter tickets
     *  - summary The (partial) summary of the ticket line on which to filter tickets
     *  - last_reply The elapsed time from the last reply on which to filter tickets
     *  - status The status of the support tickets
     *      ('open', 'awaiting_reply', 'in_progress', 'on_hold', 'closed', 'trash', 'not_closed')
     * @return int The total number of tickets in the given status
     */
    public function getStatusCount($status, $staff_id = null, $client_id = null, array $filters = [])
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$staff_id = $this->Session->read("blesta_staff_id") ?? $this->Session->read("blesta_client_id");
		$resp = $this->TicagaSettings->callAPI("tickets/countbystatus/" . $status, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		return json_decode($resp['response']);
		} else {
		return false;
		}
    }

    /**
     * Retrieves a specific ticket
     *
     * @param int $ticket_id The ID of the ticket to fetch
     * @param bool $get_replies True to include the ticket replies, false not to
     * @param array $reply_types A list of reply types to include (optional, default null for all)
     *  - "reply", "note", "log"
     * @param int $staff_id The ID of the staff member assigned to the tickets or associated departments (optional)
     * @return mixed An stdClass object representing the ticket, or false if none exist
     */
    public function get($ticket_id, $get_replies = true, array $reply_types = null, $staff_id = null)
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$staff_id = $this->Session->read("blesta_staff_id") ?? $this->Session->read("blesta_client_id");
		$resp = $this->TicagaSettings->callAPI("tickets/" . $ticket_id, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		$replies = $this->getReplies($ticket_id);
		$replies_array = $this->getRepliesAsArray($ticket_id);
		$replies_info_array = [];
		if ($resp_test)
		{
		$ticket_info = json_decode($resp['response']);
		if ($replies)
		{
		  foreach ($replies as $reply)
		  {
			$userinfo_reply = $this->getUserInfo($reply->response_user_id)[0];
			$splice_id = explode(" ",$userinfo_reply->name);
			$first_name = $splice_id[0];
			$last_name = $splice_id[1];
			$a_array = array("first_name" => $first_name, "last_name" => $last_name, "id" => $userinfo_reply->id);
			$replies_info_array[$reply->id] = $a_array;
			array_merge($replies_info_array[$reply->id],$replies_array);
		  }	
		}
		$userinfo = $this->getUserInfo($ticket_info[0]->user_id);

		$deptinfo = $this->getDepartmentsByID($ticket_info[0]->department_id);
		return array("ticket" => $ticket_info, "replies" => $replies_array, "userinfo" => $userinfo, "dept_info" => $deptinfo);
		} else {
		return false;
		}
    }

    /**
     * Retrieves a specific ticket
     *
     * @param int $code The code of the ticket to fetch
     * @param bool $get_replies True to include the ticket replies, false not to
     * @param array $reply_types A list of reply types to include (optional, default null for all)
     *  - "reply", "note", "log"
     * @return mixed An stdClass object representing the ticket, or false if none exist
     */
    public function getTicketByCode($code, $get_replies = true, array $reply_types = null)
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$staff_id = $this->Session->read("blesta_staff_id") ?? $this->Session->read("blesta_client_id");
		$resp = $this->TicagaSettings->callAPI("tickets/" . $code, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		$replies = $this->getReplies($code);
		if ($resp_test && $replies)
		{
		$ticket_info = json_decode($resp['response']);
		$userinfo = $this->getUserInfo($ticket_info->user_id);
		return array("ticket" => $ticket_info, "replies" => $replies, "userinfo" => $userinfo);
		} else {
		return false;
		}
    }
	
	/**
     * Retrieves a specific ticket
     *
     * @param int $code The code of the ticket to fetch
     * @param bool $get_replies True to include the ticket replies, false not to
     * @param array $reply_types A list of reply types to include (optional, default null for all)
     *  - "reply", "note", "log"
     * @return mixed An stdClass object representing the ticket, or false if none exist
     */
    public function doesTicketBelongToClient($code, $get_replies = true, array $reply_types = null)
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$client_id = $this->Session->read("blesta_client_id") ?? false;
		$resp = $this->TicagaSettings->callAPI("tickets/" . $code, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		$replies = $this->getReplies($code);
		if ($resp_test)
		{
		$ticket_info = json_decode($resp['response']);
		$client_var = $this->Clients->get($client_id);
		$client_email = $client_var->email ?? false;
		if ($client_id == false)
		{
			return false;
		} else {
		  if ($client_email != false)
		  {
     	    $userinfo = $this->getUserInfoByEmail($client_email);
			$userinfobyid = $this->getUserInfo($client_id);
			if ($ticket_info[0]->public_email == $client_email || $ticket_info[0]->user_id == $userinfo[0]->id || $ticket_info[0]->user_id == $userinfobyid[0]->id)
			{
				return true;
			} else {
				return false;
			}
		  }
		}
		} else {
		return false;
		}
    }
	
	/**
     * Associates/Syncs Blesta Account with Ticaga Account
     *
     * @param int $id The id of the client to fetch
     * @return mixed An stdClass object representing the ticket, or false if none exist
     */
    public function associateClientToTicaga($id)
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$client_id = $this->Session->read("blesta_client_id") ?? false;
		$client_var = $this->Clients->get($client_id);
		$client_email = $client_var->email ?? false;
		if ($client_email != false)
		  {
			  $userinfo = $this->getUserInfoByEmail($client_email);
			  $this->Record->duplicate("user_ticaga", "=", $userinfo[0]->id)->insert("ticaga_blesta_users", array('user_ticaga' => $userinfo[0]->id,'user_blesta' => $client_id, 'email_ticaga' => $userinfo[0]->email));
			  $lastinsertid = $this->Record->lastInsertId();
			  if ($lastinsertid != null)
			  {
				  return true;
			  } else {
				  return false;
			  }
		  }
    }

    /**
     * Retrieves the total number of tickets
     *
     * @param string $status The status of the support tickets
     *  ('open', 'awaiting_reply', 'in_progress', 'on_hold', 'closed', 'trash', 'not_closed')
     * @param int $staff_id The ID of the staff member assigned to the tickets or associated departments (optional)
     * @param int $client_id The ID of the client assigned to the tickets (optional)
     * @param array $filters A list of parameters to filter by, including:
     *
     *  - ticket_number The (partial) ticket number on which to filter tickets
     *  - priority The priority on which to filter tickets
     *  - department_id The department on which to filter tickets
     *  - summary The (partial) summary of the ticket line on which to filter tickets
     *  - assigned_staff The assigned staff member on which to filter tickets
     *  - last_reply The elapsed time from the last reply on which to filter tickets
     * @return int The total number of tickets
     */
    public function getListCount($status, $staff_id = null, $client_id = null, array $filters = [])
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$staff_id = $this->Session->read("blesta_staff_id") ?? $this->Session->read("blesta_client_id");
		$resp = $this->TicagaSettings->callAPI("tickets/countbystatus/" . $status, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		return json_decode($resp['response']);
		} else {
		return false;
		}
    }

    /**
     * Search tickets
     *
     * @param string $query The value to search tickets for
     * @param int $staff_id The ID of the staff member searching tickets (optional)
     * @param int $page The page number of results to fetch (optional, default 1)
     * @param array $order_by The sort=>$order options
     * @return array An array of tickets that match the search criteria
     */
    public function search($query, $staff_id = null, $page = 1, $order_by = ['last_reply_date' => 'desc'])
    {
        $this->Record = $this->searchTickets($query, $staff_id);
        return $this->Record->order($order_by)->
            limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->
            fetchAll();
    }

    /**
     * Gets all replies to a specific ticket
     *
     * @param $ticket_id The ID of the ticket whose replies to fetch
     * @return array A list of replies to the given ticket
     */
    private function getReplies($ticket_id)
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$staff_id = $this->Session->read("blesta_staff_id") ?? $this->Session->read("blesta_client_id");
		$resp = $this->TicagaSettings->callAPI("responses/" . $ticket_id, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		return json_decode($resp['response']);
		} else {
		return false;
		}
    }
	
	/**
     * Gets all replies as a array to a specific ticket
     *
     * @param $ticket_id The ID of the ticket whose replies to fetch
     * @return array A list of replies to the given ticket
     */
    private function getRepliesAsArray($ticket_id)
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$staff_id = $this->Session->read("blesta_staff_id") ?? $this->Session->read("blesta_client_id");
		$resp = $this->TicagaSettings->callAPI("responses/" . $ticket_id, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		return json_decode($resp['response'],true);
		} else {
		return false;
		}
    }
	
	/**
     * Gets all User Info to a specific ticket
     *
     * @param $user_id The ID of the user whose information to fetch
     * @return array A list of replies to the given ticket
     */
    private function getUserInfo($user_id)
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$staff_id = $this->Session->read("blesta_staff_id") ?? $this->Session->read("blesta_client_id");
		$resp = $this->TicagaSettings->callAPI("tickets/userinfo/" . $user_id, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		return json_decode($resp['response']);
		} else {
		return false;
		}
    }
	
	/**
     * Gets all User Info to a specific ticket
     *
     * @param $user_id The ID of the user whose information to fetch
     * @return array A list of replies to the given ticket
     */
    private function getUserInfoByEmail($email_id)
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$staff_id = $this->Session->read("blesta_staff_id") ?? $this->Session->read("blesta_client_id");
		$resp = $this->TicagaSettings->callAPI("tickets/userinfobyemail/" . $email_id, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		return json_decode($resp['response']);
		} else {
		return false;
		}
    }
	
	/**
     * return Ticaga Specific User ID
     *
     * @param $user_id The ID of the user whose information to fetch
     * @return array A list of replies to the given ticket
     */
    public function retrieveTicagaID($id)
    {
        return $this->Record->select()->from("ticaga_blesta_users")->where("ticaga_blesta_users.user_blesta", "=", $id)->fetch();
    }

    /**
     * Returns a Array object for fetching tickets
     *
     * @param array $filters A list of parameters to filter by, including:
     *
     *  - staff_id The ID of the staff member assigned to the tickets or associated departments (optional)
     *  - client_id The ID of the client assigned to the tickets (optional)
     *  - ticket_id The ID of a specific ticket to fetch
     *  - ticket_number The (partial) ticket number on which to filter tickets
     *  - priority The priority on which to filter tickets
     *  - department_id The department ID on which to filter tickets
     *  - summary The (partial) summary of the ticket line on which to filter tickets
     *  - last_reply The elapsed time from the last reply on which to filter tickets
     *  - status The status of the support tickets
     *      ('open', 'awaiting_reply', 'in_progress', 'on_hold', 'closed', 'trash', 'not_closed')
     *  - type The reply type to fetch ('reply', 'note', 'all')
     * @return Array A partially-constructed Array object for fetching tickets
     */
    public function getTickets(array $vars = [])
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$staff_id = $this->Session->read("blesta_staff_id") ?? $this->Session->read("blesta_client_id");
		if ($staff_id != null)
		{
		$dept_resp = $this->TicagaSettings->callAPI("departments", $apiURL,$apiKey);
		$dept_resp_count = $this->TicagaSettings->callAPI("department/count", $apiURL,$apiKey);
		$jsondec_dept_resp = json_decode($dept_resp['response']);
		if ($dept_resp_count > 0)
		{
		foreach ($jsondec_dept_resp as $dept)
		{
		$resp = $this->TicagaSettings->callAPI("tickets/all/" . $dept->slug, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		return array("response" => json_decode($resp['response']), "dept_name" => $dept->department_name);
		} else {
		return false;
		}	
		}	
		} else {
		return false;
		}
	  } else {
		$dept_resp = $this->TicagaSettings->callAPI("departments", $apiURL,$apiKey);
		$dept_resp_count = $this->TicagaSettings->callAPI("department/count", $apiURL,$apiKey);
		$jsondec_dept_resp = json_decode($dept_resp['response']);
		if ($dept_resp_count > 0)
		{
		foreach ($jsondec_dept_resp as $dept)
		{
		$resp = $this->TicagaSettings->callAPI("tickets/all/" . $dept->slug, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		return array("response" => json_decode($resp['response']), "dept_name" => $dept->department_name);
		} else {
		return false;
		}	
		}
		} else {
		return false;
		}
	  }
    }
	
	/**
     * Returns a value if user exists in Ticaga or not.
     */
    public function doesUserExist()
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$client_id = $this->Session->read("blesta_client_id") ?? null;
		if ($client_id == null)
		{
			return false;
		} else {
		$resp = $this->TicagaSettings->callAPI("tickets/userinfo/" . $client_id, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		return false;
		} else {
		return true;
		}
	  }
    }
	
	/**
     * Returns a value if user exists in Ticaga or not.
     */
    public function getTicketsByUserID($client_id)
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		if ($client_id == null)
		{
			return false;
		} else {
		$resp = $this->TicagaSettings->callAPI("tickets/user/" . $client_id, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		$jsondec = json_decode($resp['response']);
		$dept_resp = $this->TicagaSettings->callAPI("departments/byid/" . $jsondec[0]->department_id, $apiURL,$apiKey);
		$jsondec_dept_resp = json_decode($dept_resp['response']);
		return $jsondec;
		} else {
		return false;
		}
	  }
    }
	
	/**
     * Returns a value if user exists in Ticaga or not.
     */
    public function getTicketsByUserEmail($client_id)
    {
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$ipaddress = $this->get_client_ip_server();
		$clients_var = $this->Clients->get($client_id);
		$client_email = $clients_var->email ?? false;
		if ($client_id == null || $client_email == false)
		{
			return false;
		} else {
		$resp = $this->TicagaSettings->callAPI("tickets/userticketsbyemail/" . $client_email, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		$jsondec = json_decode($resp['response']);
		$dept_resp = $this->TicagaSettings->callAPI("departments/byid/" . $jsondec[0]->department_id, $apiURL,$apiKey);
		$jsondec_dept_resp = json_decode($dept_resp['response']);
		return $jsondec;
		} else {
		return false;
		}
	  }
    }
	
	/**
     * Retrieves department info by department ID
     *
     * @param int $departmentid The ID of the department whose department info to fetch
     * @return Json/boolean response 
     */
    public function getDepartmentsByID($departmentid)
    {
		$company_id = Configure::get('Blesta.company_id');
        $apiKey = $this->getAPIInfoByCompanyId($company_id)->api_key;
		$apiURL = $this->getAPIInfoByCompanyId($company_id)->api_url;
		
		$resp = $this->TicagaSettings->callAPI("departments/byid/" . $departmentid,$apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		
		if ($resp_test)
		{
		return json_decode($resp['response'],true);	
		} else {
		return false;
		}
    }
	
	/**
     * Retrieves department info by department ID
     *
     * @param int $departmentid The ID of the department whose department info to fetch
     * @return Json/boolean response 
     */
    public function getDepartmentsByIDArrayNonJsonDecoded($departmentid)
    {
		$company_id = Configure::get('Blesta.company_id');
        $apiKey = $this->getAPIInfoByCompanyId($company_id)->api_key;
		$apiURL = $this->getAPIInfoByCompanyId($company_id)->api_url;
		
		$resp = $this->TicagaSettings->callAPI("departments/byid/" . $departmentid,$apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		
		if ($resp_test)
		{
		return $resp['response'];	
		} else {
		return false;
		}
    }
	
	/**
     * Retrieves department info by department ID
     *
     * @param int $departmentid The ID of the department whose department info to fetch
     * @return Json/boolean response 
     */
    public function getDepartmentsByIDNonArray($departmentid)
    {
		$company_id = Configure::get('Blesta.company_id');
        $apiKey = $this->getAPIInfoByCompanyId($company_id)->api_key;
		$apiURL = $this->getAPIInfoByCompanyId($company_id)->api_url;
		
		$resp = $this->TicagaSettings->callAPI("departments/byid/" . $departmentid,$apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		
		if ($resp_test)
		{
		return json_decode($resp['response']);	
		} else {
		return false;
		}
    }
	
	/**
     * Retrieves department info by all departments
     *
     * @return Json/boolean response 
     */
    public function getDepartmentsAll()
    {
		$company_id = Configure::get('Blesta.company_id');
        $apiKey = $this->getAPIInfoByCompanyId($company_id)->api_key;
		$apiURL = $this->getAPIInfoByCompanyId($company_id)->api_url;
		
		$resp = $this->TicagaSettings->callAPI("departments",$apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		
		if ($resp_test)
		{
		return json_decode($resp['response'],true);	
		} else {
		return false;
		}
    }
	
	/**
     * Retrieves department info for public depts only.
     *
     * @return Json/boolean response 
     */
    public function getDepartmentsForPublicUseOnly()
    {
		$company_id = Configure::get('Blesta.company_id');
        $apiKey = $this->getAPIInfoByCompanyId($company_id)->api_key;
		$apiURL = $this->getAPIInfoByCompanyId($company_id)->api_url;
		
		$resp = $this->TicagaSettings->callAPI("departments/1",$apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		
		if ($resp_test)
		{
		return json_decode($resp['response'],true);	
		} else {
		return false;
		}
    }

    /**
     * Retrieves department info for public depts only.
     *
     * @return Json/boolean response 
     */
    public function getDepartmentsForClientsUseOnly()
    {
		$company_id = Configure::get('Blesta.company_id');
        $apiKey = $this->getAPIInfoByCompanyId($company_id)->api_key;
		$apiURL = $this->getAPIInfoByCompanyId($company_id)->api_url;
		
		$resp = $this->TicagaSettings->callAPI("departments/2",$apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		
		if ($resp_test)
		{
		return json_decode($resp['response'],true);	
		} else {
		return false;
		}
    }
	
	/**
     * Retrieves a list of department priorities
     *
     * @param int $department_id The ID of the department to filter priorities by (optional)
     * @return array A list of priorities and their language
     */
    public function getPriorities(?int $department_id = null)
    {
            $priorities = [];
			$deptidinfo = $this->getDepartmentsByIDNonArray($department_id);
			$jsondec = $deptidinfo;
			if ($jsondec[0]->allows_high_priority == '0')
			{
			$priorities = [
                'medium' => $this->_('TicagaDepartments.priorities.medium'),
                'low' => $this->_('TicagaDepartments.priorities.low')
            ];
			} else {
			$priorities = [
                'emergency' => $this->_('TicagaDepartments.priorities.emergency'),
                'critical' => $this->_('TicagaDepartments.priorities.critical'),
                'high' => $this->_('TicagaDepartments.priorities.high'),
                'medium' => $this->_('TicagaDepartments.priorities.medium'),
                'low' => $this->_('TicagaDepartments.priorities.low')
            ];
			}

        return $priorities;
    }
	
	/**
     * Retrieves whether high priorities are allowed or not.
     *
     * @param int $department_id The ID of the department to filter priorities by (optional)
     * @return boolean true or false
     */
    public function getPrioritiesHighAllowed(?int $department_id = null)
    {
            $priorities = [];
			$deptidinfo = $this->getDepartmentsByIDNonArray($department_id);
			$jsondec = $deptidinfo;
			if ($jsondec[0]->allows_high_priority == '0')
			{
			return false;
			} else if ($jsondec[0]->allows_high_priority == '1') {
			return true;
			} else {
			return false;
			}
    }

    /**
     * Retrieves a list of statuses and their language
     *
     * @return array A list of status => language statuses
     */
    public function getStatuses()
    {
        return [
            'open' => $this->_('TicagaTickets.status.open'),
            'awaiting_reply' => $this->_('TicagaTickets.status.awaiting_reply'),
            'in_progress' => $this->_('TicagaTickets.status.in_progress'),
            'on_hold' => $this->_('TicagaTickets.status.on_hold'),
            'closed' => $this->_('TicagaTickets.status.closed')
        ];
    }

    /**
     * Retrieves a list of reply types and their language
     *
     * @return array A list of type => language reply types
     */
    public function getReplyTypes()
    {
        return [
            'reply' => $this->_('TicagaTickets.type.reply'),
            'note' => $this->_('TicagaTickets.type.note'),
            'log' => $this->_('TicagaTickets.type.log')
        ];
    }

    /**
     * Fetches the client for the given company using the given email address.
     * Searches first the primary contact of each client, and if no results found
     * then any contact for the clients in the given company. Returns the first
     * client found.
     *
     * @param int $company_id The ID of the company to fetch a client for
     * @param string $email The email address to fetch clients on
     * @return mixed A stdClass object representing the client whose contact
     *  matches the email address, false if no client found
     */
    public function getClientByEmail($company_id, $email)
    {
        // Fetch client based on primary contact email
        $client = $this->Record->select(['clients.*'])->
            from('contacts')->
            innerJoin('clients', 'clients.id', '=', 'contacts.client_id', false)->
            innerJoin('client_groups', 'client_groups.id', '=', 'clients.client_group_id', false)->
            where('client_groups.company_id', '=', $company_id)->
            where('contacts.email', '=', $email)->
            where('contacts.contact_type', '=', 'primary')->fetch();

        // If no client found, fetch client based on any contact email
        if (!$client) {
            $client = $this->Record->select(['clients.*'])->
                from('contacts')->
                innerJoin('clients', 'clients.id', '=', 'contacts.client_id', false)->
                innerJoin('client_groups', 'client_groups.id', '=', 'clients.client_group_id', false)->
                where('client_groups.company_id', '=', $company_id)->
                where('contacts.email', '=', $email)->fetch();
        }
        return $client;
    }

    /**
     * Fetches a client's contact given the contact's email address
     *
     * @param int $client_id The ID of the client whose contact the email address is presumed to be from
     * @param string $email The email address
     * @return mixed An stdClass object representing the contact with the given email address, or false if none exist
     */
    public function getContactByEmail($client_id, $email)
    {
        // Assume contact emails are unique per client, and only choose the first
        return $this->Record->select(['contacts.*'])->
            from('contacts')->
            where('contacts.email', '=', $email)->
            where('contacts.client_id', '=', $client_id)->
            fetch();
    }

    /**
     * Retrieves a list of all contact email addresses that have replied to the given ticket.
     * This does not include the client's primary contact email.
     *
     * @param int $ticket_id The ID of the ticket whose contact emails to fetch
     * @return array A numerically indexed array of email addresses of each contact that has replied to this ticket.
     *  May be an empty array if no contact, or only the primary client contact, has replied.
     */
    public function getContactEmails($ticket_id)
    {
        // Fetch the email addresses of all contacts set on the ticket replies
        $apiKey = $this->getAPIInfoByCompanyId()->api_key;
		$apiURL = $this->getAPIInfoByCompanyId()->api_url;
		$department_id = $vars['department_id'];
		$client_id = $vars['client_id'] ?? $vars['staff_id'];
		$details = $vars["details"] ?? "";
		$isnote = $vars["isnote"] ?? "false";
		$ipaddress = $this->get_client_ip_server();
		$contact_emails_resp = [];
		if ($vars['staff_id'] != null)
		{		
		$resp = $this->TicagaSettings->callAPI("responses/" . $ticket_id, $apiURL,$apiKey);
		$contact_emails_resp = json_decode($resp['response']);
		} else {
		$resp = $this->TicagaSettings->callAPI("responses/" . $ticket_id, $apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
		$contact_emails_resp = json_decode($resp['response']);

        $contact_emails = [];
        foreach ($contact_emails_resp as $email) {
            $contact_emails[] = $email->email;
        }

        return $contact_emails;
		}
        
      }
	}

    /**
     * Generates a pseudo-random hash from an sha256 HMAC of the ticket ID
     *
     * @param int $ticket_id The ID of the ticket to generate the hash for
     * @param mixed $key A key to include in the hash
     * @return string A hexadecimal hash of the given length
     */
    public function generateReplyHash($ticket_id, $key)
    {
        return $this->systemHash($ticket_id . $key);
    }

    /**
     * Forms a link for a customer ticket
     *
     * @param int $ticket_id The ID of the ticket to link
     * @param string $hostname The company hostname to link
     */
    private function getUpdateTicketUrl($ticket_id, $hostname)
    {
        $key = mt_rand();
        $hash = $this->generateReplyHash($ticket_id, $key);

        $url = $hostname . $this->getWebDirectory() . Configure::get('Route.client')
            . '/plugin/ticaga_support/client_main/reply/' . $ticket_id
            . '/?sid=' . rawurlencode($this->systemEncrypt('h=' . substr($hash, -16) . '|k=' . $key));

        return $this->Html->safe($url);
    }

    /**
     *  Retrieves the client contact that replied to this ticket, otherwise the client contact this ticket is assigned to if available
     * @see ::sendTicketByClientEmail, ::sendTicketReceived
     *
     * @param stdClass $ticket An object representing the given ticket
     * @return stdClass An object representing the contact assigned to this ticket
     */
    private function getTicketReplyContact(stdClass $ticket)
    {
        Loader::loadModels($this, ['Clients', 'Contacts']);
        $contact = null;
        if ($ticket->reply_contact_id) {
            $contact = $this->Contacts->get($ticket->reply_contact_id);
        } elseif (!$ticket->reply_staff_id && ($client = $this->Clients->get($ticket->client_id, false))) {
            $contacts = $this->Contacts->getAll($client->id, 'primary');
            if (!empty($contacts)) {
                $contact = $contacts[0];
            }
        }

        return $contact;
    }

    /**
     * Validates that the given reply code is correct for the ticket ID code
     *
     * @param int $ticket_code The ticket code to validate the reply code for
     * @return bool True if the reply code is valid, false otherwise
     */
    public function validateReplyCode($ticket_code, $code)
    {
        $hash = $this->systemHash($ticket_code);
        return strpos($hash, $code) !== false;
    }

    /**
     * Retrieves a list of rules for adding/editing support ticket replies
     *
     * @param array $vars A list of input vars
     * @param bool $new_ticket True to get the rules if this ticket is in the process of
     *  being created, false otherwise (optional, default false)
     * @return array A list of ticket reply rules
     */
    private function getReplyRules(array $vars, $new_ticket = false)
    {
        $rules = [
            'staff_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateStaffExists']],
                    'message' => $this->_('TicagaTickets.!error.staff_id.exists')
                ]
            ],
            'contact_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateExists'], 'id', 'contacts'],
                    'message' => $this->_('TicagaTickets.!error.contact_id.exists')
                ],
                'valid' => [
                    'if_set' => true,
                    'rule' => [
                        [$this, 'validateClientContact'],
                        (isset($vars['ticket_id']) ? $vars['ticket_id'] : null),
                        (isset($vars['client_id']) ? $vars['client_id'] : null)
                    ],
                    'message' => $this->_('TicagaTickets.!error.contact_id.valid')
                ]
            ],
            'type' => [
                'format' => [
                    'if_set' => true,
                    'rule' => ['in_array', array_keys($this->getReplyTypes())],
                    'message' => $this->_('TicagaTickets.!error.type.format')
                ]
            ],
            'details' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => $this->_('TicagaTickets.!error.details.empty')
                ]
            ],
            'date_added' => [
                'format' => [
                    'rule' => true,
                    'message' => '',
                    'post_format' => [[$this, 'dateToUtc']]
                ]
            ]
        ];

        if ($new_ticket) {
            // The reply type must be 'reply' on a new ticket
            $rules['type']['new_valid'] = [
                'if_set' => true,
                'rule' => ['compares', '==', 'reply'],
                'message' => $this->_('TicagaTickets.!error.type.new_valid')
            ];
        } else {
            // Validate ticket exists
            $rules['ticket_id'] = [
                'exists' => [
                    'rule' => [[$this, 'validateExists'], 'id', 'support_tickets'],
                    'message' => $this->_('TicagaTickets.!error.ticket_id.exists')
                ]
            ];
            // Validate client can reply to this ticket
            $rules['client_id'] = [
                'attached_to' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateClientTicket'], (isset($vars['ticket_id']) ? $vars['ticket_id'] : null)],
                    'message' => $this->_('TicagaTickets.!error.client_id.attached_to')
                ]
            ];
        }

        return $rules;
    }

    /**
     * Retrieves a list of rules for adding/editing support tickets
     *
     * @param array $vars A list of input vars
     * @param bool $edit True to get the edit rules, false for the add rules (optional, default false)
     * @param bool $require_email True to require the email field be given, false otherwise (optional, default false)
     * @return array A list of support ticket rules
     */
    private function getRules(array $vars, $edit = false, $require_email = false)
    {
        $rules = [
            'code' => [
                'format' => [
                    'rule' => ['matches', '/^[0-9]+$/'],
                    'message' => $this->_('TicagaTickets.!error.code.format')
                ]
            ],
            'department_id' => [
                'exists' => [
                    'rule' => [[$this, 'validateExists'], 'id', 'support_departments'],
                    'message' => $this->_('TicagaTickets.!error.department_id.exists')
                ]
            ],
            'staff_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateStaffExists']],
                    'message' => $this->_('TicagaTickets.!error.staff_id.exists')
                ]
            ],
            'service_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateExists'], 'id', 'services'],
                    'message' => $this->_('TicagaTickets.!error.service_id.exists')
                ],
                'belongs' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateClientService'], (isset($vars['client_id']) ? $vars['client_id'] : null)],
                    'message' => $this->_('TicagaTickets.!error.service_id.belongs')
                ]
            ],
            'client_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateExists'], 'id', 'clients'],
                    'message' => $this->_('TicagaTickets.!error.client_id.exists')
                ]
            ],
            'email' => [
                'format' => [
                    'rule' => [[$this, 'validateEmail'], $require_email],
                    'message' => $this->_('TicagaTickets.!error.email.format')
                ]
            ],
            'summary' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => $this->_('TicagaTickets.!error.summary.empty')
                ],
                'length' => [
                    'rule' => ['maxLength', 255],
                    'message' => $this->_('TicagaTickets.!error.summary.length')
                ]
            ],
            'priority' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => function ($priority) use ($vars) {
                        $parent = new stdClass();
                        Loader::loadModels($parent, ['TicagaSupport.TicagaDepartments']);

                        if (($department = $parent->TicagaDepartments->get($vars['department_id']))) {
                            return in_array($priority, $department->priorities);
                        }

                        return false;
                    },
                    'message' => $this->_('TicagaTickets.!error.priority.valid')
                ],
                'format' => [
                    'if_set' => true,
                    'rule' => ['in_array', array_keys($this->getPriorities())],
                    'message' => $this->_('TicagaTickets.!error.priority.format')
                ]
            ],
            'status' => [
                'format' => [
                    'if_set' => true,
                    'rule' => ['in_array', array_keys($this->getStatuses())],
                    'message' => $this->_('TicagaTickets.!error.status.format')
                ]
            ],
            'date_added' => [
                'format' => [
                    'rule' => true,
                    'message' => $this->_('TicagaTickets.!error.date_added.format'),
                    'post_format' => [[$this, 'dateToUtc']]
                ]
            ],
            'date_updated' => [
                'format' => [
                    'rule' => true,
                    'message' => $this->_('TicagaTickets.!error.date_updated.format'),
                    'post_format' => [[$this, 'dateToUtc']]
                ]
            ],
            'by_staff_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateStaffExists']],
                    'message' => $this->_('TicagaTickets.!error.by_staff_id.exists')
                ]
            ]
        ];

        if ($edit) {
            // Remove unnecessary rules
            unset($rules['date_added']);

            // Require that a client ID not be set
            $rules['client_id']['set'] = [
                'rule' => [[$this, 'validateTicketUnassigned'], (isset($vars['ticket_id']) ? $vars['ticket_id'] : null)],
                'message' => Language::_('TicagaTickets.!error.client_id.set', true)
            ];

            // Set edit-specific rules
            $rules['date_closed'] = [
                'format' => [
                    'rule' => [[$this, 'validateDateClosed']],
                    'message' => $this->_('TicagaTickets.!error.date_closed.format'),
                    'post_format' => [[$this, 'dateToUtc']]
                ]
            ];

            // Set all rules to optional
            $rules = $this->setRulesIfSet($rules);

            // Require a ticket be given
            $rules['ticket_id'] = [
                'exists' => [
                    'rule' => [[$this, 'validateExists'], 'id', 'support_tickets'],
                    'message' => $this->_('TicagaTickets.!error.ticket_id.exists')
                ]
            ];
        }

        return $rules;
    }

    /**
     * Validates whether the given client can reply to the given ticket
     *
     * @param int $client_id The ID of the client
     * @param int $ticket_id The ID of the ticket
     * @return bool True if the client can reply to the ticket, false otherwise
     */
    public function validateClientTicket($client_id, $ticket_id)
    {
        $company_id = Configure::get('Blesta.company_id');
        $apiKey = $this->getAPIInfoByCompanyIdProvided($company_id)->api_key;
		$apiURL = $this->getAPIInfoByCompanyIdProvided($company_id)->api_url;
		
		$resp = $this->TicagaSettings->callAPI("tickets/" . $ticket_id,$apiURL,$apiKey);
		$resp_test = $this->TicagaSettings->validateAPISuccessResponse($resp);
		if ($resp_test)
		{
				$respjsondec = json_decode($resp['response']);
				$clientid = $this->Clients->get($client_id);
				if ($respjsondec->user_id == $client_id || $respjsondec->cc == $clientid->email)
				{
					return true;
				} else {
					return false;
				}
		} else {
			return false;
		}
    }

    /**
     * Validates whether the given contact can reply to the given ticket for the ticket's client
     *
     * @param int $contact_id The ID of the contact
     * @param int $ticket_id The ID of the ticket
     * @param int $client_id The ID of the client assigned to the ticket if the ticket
     *  is not known (optional, default null)
     * @return bool True if the contact can reply to the ticket, false otherwise
     */
    public function validateClientContact($contact_id, $ticket_id, $client_id = null)
    {
        // Contact does not need to be set
        if ($contact_id === null) {
            return true;
        }

        $ticket = $this->get($ticket_id, false);

        // In case a ticket is not yet known (e.g. in the process of being created), compare with the given client
        $client_id = ($ticket && $ticket->client_id ? $ticket->client_id : $client_id);

        if ($client_id !== null) {
            // The ticket and the contact must belong to a client
            $found = $this->Record->select()->from('contacts')->
                where('id', '=', $contact_id)->
                where('client_id', '=', $client_id)->
                numResults();

            if ($found) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validates that the given client can be assigned to the given ticket
     *
     * @param int $client_id The ID of the client to assign to the ticket
     * @param int $ticket_id The ID of the ticket
     * @return bool True if the client may be assigned to the ticket, false otherwise
     */
    public function validateTicketUnassigned($client_id, $ticket_id)
    {
        // Fetch the ticket
        $ticket = $this->get($ticket_id, false);

        // No ticket found, ignore this error
        if (!$ticket) {
            return true;
        }

        // Ticket may have either no client, or this client
        if ($ticket->client_id === null || $ticket->client_id == $client_id) {
            // Client must also be in the same company as the ticket
            $count = $this->Record->select(['client_groups.id'])->
                from('client_groups')->
                innerJoin('clients', 'clients.client_group_id', '=', 'client_groups.id', false)->
                where('clients.id', '=', $client_id)->
                where('client_groups.company_id', '=', $ticket->company_id)->
                numResults();

            if ($count > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validates that the given staff ID exists when adding/editing tickets
     *
     * @param int $staff_id The ID of the staff member
     * @return bool True if the staff ID exists, false otherwise
     */
    public function validateStaffExists($staff_id)
    {
        if ($staff_id == '' || $staff_id == $this->system_staff_id
            || $this->validateExists($staff_id, 'id', 'staff', false)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Validates that the given service ID is assigned to the given client ID
     *
     * @param int $service_id The ID of the service
     * @param int $client_id The ID of the client
     * @return bool True if the service ID belongs to the client ID, false otherwise
     */
    public function validateClientService($service_id, $client_id)
    {
        $count = $this->Record->select()->from('services')->
            where('id', '=', $service_id)->
            where('client_id', '=', $client_id)->
            numResults();

        return ($count > 0);
    }

    /**
     * Validates the email address given for support tickets
     *
     * @param string $email The support ticket email address
     * @param bool $require_email True to require the email field be given, false otherwise (optional, default false)
     * @return bool True if the email address is valid, false otherwise
     */
    public function validateEmail($email, $require_email = false)
    {
        return (empty($email) && !$require_email ? true : $this->Input->isEmail($email));
    }

    /**
     * Validates the date closed for support tickets
     *
     * @param string $date_closed The date a ticket is closed
     * @return bool True if the date is in a valid format, false otherwise
     */
    public function validateDateClosed($date_closed)
    {
        return (empty($date_closed) ? true : $this->Input->isDate($date_closed));
    }

    /**
     * Validates that the given replies belong to the given ticket and that they are of the reply/note type.
     *
     * @param array $replies A list of IDs representing ticket replies
     * @param int $ticket_id The ID of the ticket to which the replies belong
     * @param bool $all False to require that at least 1 ticket reply not be given for this ticket,
     *  or true to allow all (optional, default false)
     * @return bool True if all of the given replies are valid; false otherwise
     */
    public function validateReplies(array $replies, $ticket_id, $all = false)
    {
        // Must have at least one reply ID
        if (empty($replies) || !($ticket = $this->get($ticket_id))) {
            return false;
        }

        // Fetch replies that are valid
        $valid_replies = $this->getValidTicketReplies($ticket_id);
        $num_notes = 0;
        $num_replies = 0;

        // Count the number of ticket notes and replies
        foreach ($valid_replies as $reply) {
            if ($reply->type == 'note') {
                $num_notes++;
            } else {
                $num_replies++;
            }
        }

        // Check that all replies given are valid replies
        foreach ($replies as $reply_id) {
            if (!array_key_exists($reply_id, $valid_replies)) {
                return false;
            }

            // Decrement the number of notes/replies that would be available to the ticket
            if ($valid_replies[$reply_id]->type == 'note') {
                $num_notes--;
            } else {
                $num_replies--;
            }
        }

        // At least one reply must be left remaining
        if (!$all && $num_replies <= 0) {
            return false;
        }

        // There must be valid replies
        return !empty($valid_replies);
    }

    /**
     * Validates that the given replies belong to the given ticket, that they are of the reply/note type, and that they
     * are not all only note types.
     * i.e. In addition to replies of the 'note' type, at least one 'reply' type must be included
     *
     * @param array $replies A list of IDs representing ticket replies
     * @param int $ticket_id The ID of the ticket to which the replies belong
     * @return bool True if no replies are given, or at least one is of the 'reply' type; false otherwise
     */
    public function validateSplitReplies(array $replies, $ticket_id)
    {
        // No replies, nothing to validate
        if (empty($replies)) {
            return true;
        }

        // Fetch the ticket replies
        $valid_replies = $this->getValidTicketReplies($ticket_id);

        foreach ($replies as $reply_id) {
            // At least one ticket reply must be of the 'reply' type
            if (array_key_exists($reply_id, $valid_replies) && $valid_replies[$reply_id]->type == 'reply') {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves a list of ticket replies of the "reply" and "note" type belonging to the given ticket
     *
     * @param int $ticket_id The ID of the ticket
     * @return array An array of stdClass objects representing each reply, keyed by the reply ID
     */
    private function getValidTicketReplies($ticket_id)
    {
        $valid_replies = [];

        if (($ticket = $this->get($ticket_id))) {
            foreach ($ticket->replies as $reply) {
                if (in_array($reply->type, ['reply', 'note'])) {
                    $valid_replies[$reply->id] = $reply;
                }
            }
        }

        return $valid_replies;
    }

    /**
     * Validates that the given open tickets can be merged into the given ticket
     *
     * @param array $tickets A list of ticket IDs
     * @param int $ticket_id The ID of the ticket the tickets are to be merged into
     * @return bool True if all of the given tickets can be merged into the ticket, or false otherwise
     */
    public function validateTicketsMergeable(array $tickets, $ticket_id)
    {
        // Fetch the ticket
        $ticket = $this->get($ticket_id, false);
        if (!$ticket || $ticket->status == 'closed') {
            return false;
        }

        // Check whether every ticket belongs to the same client (or email address),
        // belongs to the same company, and are open
        foreach ($tickets as $old_ticket_id) {
            // Fetch the ticket
            $old_ticket = $this->get($old_ticket_id, false);
            if (!$old_ticket) {
                return false;
            }

            // Check company matches, client matches, and ticket is open
            if (($old_ticket->company_id != $ticket->company_id) || ($old_ticket->status == 'closed') ||
                ($old_ticket->client_id != $ticket->client_id || $old_ticket->email != $ticket->email)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates that all of the given tickets can be updated to the associated service
     *
     * @param array $vars An array consisting of arrays of ticket vars whose index
     *  refers to the index of the $ticket_ids array representing the vars of the specific
     *  ticket to update; or an array of vars to apply to all tickets; each including:
     *  - service_id The ID of the client service this ticket relates to
     * @param array $ticket_ids An array of ticket IDs to update
     * @return bool True if the service(s) match the tickets, or false otherwise
     */
    public function validateServicesMatchTickets(array $vars, array $ticket_ids)
    {
        // Determine whether to apply vars to all tickets, or whether each ticket has separate vars
        $separate_vars = (isset($vars[0]) && is_array($vars[0]));

        // Check whether the tickets can be assigned to the given service(s)
        foreach ($ticket_ids as $key => $ticket_id) {
            // Each ticket has separate vars specific to that ticket
            $temp_vars = $vars;
            if ($separate_vars) {
                // Since all fields are optional, we don't need to require a service_id be given
                if (!isset($vars[$key]) || empty($vars[$key])) {
                    $vars[$key] = [];
                }

                $temp_vars = $vars[$key];
            }

            // Check whether the client has this service
            if (isset($temp_vars['service_id'])) {
                // Fetch the ticket
                $ticket = $this->get($ticket_id, false);
                if ($ticket && !empty($ticket->client_id)) {
                    // Check whether the client has the service
                    $services = $this->Record->select(['id'])
                        ->from('services')
                        ->where('client_id', '=', $ticket->client_id)
                        ->fetchAll();
                    $temp_services = [];
                    foreach ($services as $service) {
                        $temp_services[] = $service->id;
                    }

                    if (!in_array($temp_vars['service_id'], $temp_services)) {
                        return false;
                    }
                } else {
                    return false;
                }
            }
        }

        return true;
    }
}
