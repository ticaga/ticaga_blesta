<?php
/**
 * Ticaga ClientExec Snapin
 *
 * Provides a single file integration that keeps ClientExec clients in sync
 * with Ticaga and exposes ticket actions from the dashboard.
 */

if (!class_exists('Snapin')) {
    require_once 'modules/admin/models/Snapin.php';
}

class PluginTicaga extends Snapin
{
    /** @var string */
    private $version = '1.0.1';

    /** @var array */
    private $settings = array();

    /** @var bool */
    private $allowGuests = true;

    public function __construct()
    {
        parent::__construct();

        $this->setName('Ticaga Support');
        $this->setDescription('Synchronises ClientExec clients with Ticaga and exposes ticket functions.');
        $this->addTag('support');

        $this->settings = $this->loadSettings();
        $allowValue = isset($this->settings['allow_guests']) ? $this->settings['allow_guests'] : '1';
        $this->allowGuests = $allowValue !== '0' && $allowValue !== 0;
    }

    public function getVariables()
    {
        $apiUrl = isset($this->settings['api_url']) ? $this->settings['api_url'] : '';
        $apiEmail = isset($this->settings['api_email']) ? $this->settings['api_email'] : '';
        $apiKey = isset($this->settings['api_key']) ? $this->settings['api_key'] : '';

        return array(
            lang('Plugin Name') => array(
                'type' => 'hidden',
                'value' => 'Ticaga Support'
            ),
            lang('Description') => array(
                'type' => 'hidden',
                'value' => lang('Synchronises ClientExec users with Ticaga and exposes support ticket functionality.')
            ),
            lang('API URL') => array(
                'type' => 'text',
                'description' => lang('Base Ticaga URL (e.g. https://support.example.com).'),
                'value' => $apiUrl
            ),
            lang('API Email') => array(
                'type' => 'text',
                'description' => lang('Ticaga API user email address.'),
                'value' => $apiEmail
            ),
            lang('API Key') => array(
                'type' => 'text',
                'description' => lang('Ticaga API key.'),
                'value' => $apiKey
            ),
            lang('Allow Guest Tickets') => array(
                'type' => 'yesno',
                'description' => lang('If enabled, visitors without a ClientExec account may submit tickets.'),
                'value' => $this->allowGuests ? '1' : '0'
            )
        );
    }

    public function install()
    {
        $db = CE_Lib::db();
        $sql = array(
            'CREATE TABLE IF NOT EXISTS plugin_ticaga_accounts (',
            'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,',
            'clientexec_user_id INT UNSIGNED NOT NULL,',
            'ticaga_user_id INT UNSIGNED NOT NULL,',
            'email VARCHAR(255) NOT NULL,',
            'created_at DATETIME NOT NULL,',
            'updated_at DATETIME NOT NULL,',
            'UNIQUE KEY clientexec_user_id (clientexec_user_id),',
            'INDEX ticaga_user_id (ticaga_user_id),',
            'INDEX email (email)',
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $db->query(implode(' ', $sql));
        return true;
    }

    public function getHooks()
    {
        return array(
            array(
                'event' => 'ClientExec.client.login',
                'callback' => 'onClientLogin'
            ),
            array(
                'event' => 'ClientExec.client.view',
                'callback' => 'onClientLogin'
            )
        );
    }

    public function onClientLogin($args)
    {
        if (empty($this->settings['api_url']) || empty($this->settings['api_key']) || empty($this->settings['api_email'])) {
            return;
        }

        if (!is_array($args) || empty($args['customer'])) {
            return;
        }

        $customer = $args['customer'];
        $email = method_exists($customer, 'getEmail') ? $customer->getEmail() : null;
        $first = method_exists($customer, 'getFirstName') ? $customer->getFirstName() : '';
        $last = method_exists($customer, 'getLastName') ? $customer->getLastName() : '';
        $userId = method_exists($customer, 'getId') ? $customer->getId() : null;

        if (empty($userId) || empty($email)) {
            return;
        }

        $this->ensureSynced($userId, $email, $first, $last);
    }

    public function init()
    {
        $request = CE_Lib::getRequest();

        if ($request->isPost()) {
            $this->handlePost($request);
        }

        $this->setContent($this->render());
    }

    private function handlePost($request)
    {
        $action = $request->get('ticaga_action', '');

        if ($action === 'create_ticket') {
            $this->processTicketCreation($request);
        }
    }

    private function render()
    {
        $customer = CE_Lib::getLoggedInUser();
        $isAuthenticated = $customer !== null;

        $html = '<div class="ticaga-snapin">';
        $html .= '<h3>' . $this->escape(lang('Ticaga Support')) . '</h3>';

        if (!empty($this->settings['api_url'])) {
            $html .= '<p class="ticaga-meta">' . $this->escape(lang('Connected to:')) . ' ' . $this->escape($this->settings['api_url']) . '</p>';
        }

        if (!$isAuthenticated && !$this->allowGuests) {
            $html .= '<p>' . $this->escape(lang('Please log in to create or view support tickets.')) . '</p>';
            $html .= '</div>';
            return $html;
        }

        if ($isAuthenticated) {
            $html .= $this->renderAuthenticatedContent($customer);
        } else {
            $html .= $this->renderGuestContent();
        }

        $html .= '</div>';
        return $html;
    }

    private function renderAuthenticatedContent($customer)
    {
        $email = method_exists($customer, 'getEmail') ? $customer->getEmail() : '';
        $first = method_exists($customer, 'getFirstName') ? $customer->getFirstName() : '';
        $last = method_exists($customer, 'getLastName') ? $customer->getLastName() : '';
        $userId = method_exists($customer, 'getId') ? $customer->getId() : 0;

        $mapping = $this->ensureSynced($userId, $email, $first, $last);

        $tickets = array();
        if (is_array($mapping) && isset($mapping['ticaga_user_id'])) {
            $tickets = $this->fetchTickets($mapping['ticaga_user_id']);
        }

        $departments = $this->fetchDepartments();

        $html = '<div class="ticaga-auth">';

        if (!empty($tickets)) {
            $html .= '<h4>' . $this->escape(lang('My Tickets')) . '</h4>';
            $html .= '<ul class="ticaga-ticket-list">';
            foreach ($tickets as $ticket) {
                if (!is_array($ticket)) {
                    continue;
                }
                $ticketId = isset($ticket['id']) ? $ticket['id'] : '';
                $subject = isset($ticket['subject']) ? $ticket['subject'] : '';
                $status = isset($ticket['status']) ? $ticket['status'] : '';
                $html .= '<li>';
                $html .= '<strong>#' . $this->escape($ticketId) . '</strong> - ' . $this->escape($subject);
                $html .= '<br/><span class="ticaga-ticket-meta">' . $this->escape(lang('Status:')) . ' ' . $this->escape($status) . '</span>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>' . $this->escape(lang('No tickets found.')) . '</p>';
        }

        $defaults = array(
            'public_name' => trim($first . ' ' . $last),
            'public_email' => $email,
            'client_id' => is_array($mapping) && isset($mapping['ticaga_user_id']) ? $mapping['ticaga_user_id'] : '',
            'is_guest' => false
        );

        $html .= '<h4>' . $this->escape(lang('Open Ticket')) . '</h4>';
        $html .= $this->buildTicketForm($departments, $defaults);
        $html .= '</div>';

        return $html;
    }

    private function renderGuestContent()
    {
        $departments = $this->fetchDepartments();
        $defaults = array(
            'public_name' => '',
            'public_email' => '',
            'client_id' => '',
            'is_guest' => true
        );

        $html = '<div class="ticaga-guest">';
        $html .= '<p>' . $this->escape(lang('You may submit a support request without logging in.')) . '</p>';
        $html .= $this->buildTicketForm($departments, $defaults);
        $html .= '</div>';
        return $html;
    }

    private function buildTicketForm($departments, $defaults)
    {
        $clientId = isset($defaults['client_id']) ? $defaults['client_id'] : '';
        $name = isset($defaults['public_name']) ? $defaults['public_name'] : '';
        $email = isset($defaults['public_email']) ? $defaults['public_email'] : '';

        $html = '<form method="post" class="ticaga-ticket-form">';
        $html .= '<input type="hidden" name="ticaga_action" value="create_ticket" />';
        $html .= '<input type="hidden" name="client_id" value="' . $this->escape($clientId) . '" />';
        $html .= '<div class="ticaga-field">';
        $html .= '<label>' . $this->escape(lang('Your Name')) . '</label>';
        $html .= '<input type="text" name="public_name" value="' . $this->escape($name) . '" required />';
        $html .= '</div>';
        $html .= '<div class="ticaga-field">';
        $html .= '<label>' . $this->escape(lang('Email Address')) . '</label>';
        $html .= '<input type="email" name="public_email" value="' . $this->escape($email) . '" required />';
        $html .= '</div>';
        $html .= '<div class="ticaga-field">';
        $html .= '<label>' . $this->escape(lang('Department')) . '</label>';
        $html .= '<select name="department_slug" required>';

        if (is_array($departments)) {
            foreach ($departments as $department) {
                if (!is_array($department)) {
                    continue;
                }
                $slug = isset($department['slug']) ? $department['slug'] : '';
                $label = isset($department['name']) ? $department['name'] : $slug;
                $html .= '<option value="' . $this->escape($slug) . '">' . $this->escape($label) . '</option>';
            }
        }

        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="ticaga-field">';
        $html .= '<label>' . $this->escape(lang('Subject')) . '</label>';
        $html .= '<input type="text" name="subject" required />';
        $html .= '</div>';
        $html .= '<div class="ticaga-field">';
        $html .= '<label>' . $this->escape(lang('Priority')) . '</label>';
        $html .= '<select name="priority">';

        $priorities = array('low', 'medium', 'high', 'emergency');
        foreach ($priorities as $priority) {
            $html .= '<option value="' . $this->escape($priority) . '">' . $this->escape(ucfirst($priority)) . '</option>';
        }

        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="ticaga-field">';
        $html .= '<label>' . $this->escape(lang('Message')) . '</label>';
        $html .= '<textarea name="message" rows="6" required></textarea>';
        $html .= '</div>';
        $html .= '<div class="ticaga-actions">';
        $html .= '<button type="submit" class="ticaga-submit">' . $this->escape(lang('Submit Ticket')) . '</button>';
        $html .= '</div>';
        $html .= '</form>';
        return $html;
    }

    private function processTicketCreation($request)
    {
        $data = array(
            'client_id' => trim($request->get('client_id', '')),
            'public_name' => trim($request->get('public_name', '')),
            'client_email' => trim($request->get('public_email', '')),
            'department_slug' => trim($request->get('department_slug', '')),
            'subject' => trim($request->get('subject', '')),
            'priority' => trim($request->get('priority', 'low')),
            'message' => trim($request->get('message', ''))
        );

        if ($data['public_name'] === '' || $data['client_email'] === '' || $data['subject'] === '' || $data['message'] === '') {
            CE_Lib::addErrorMessage(lang('All fields are required.'));
            return;
        }

        $payload = array(
            'organize' => 'clientexec',
            'user_id' => $data['client_id'] !== '' ? $data['client_id'] : '0',
            'public_name' => $data['public_name'],
            'public_email' => $data['client_email'],
            'department_slug' => $data['department_slug'],
            'subject' => $data['subject'],
            'priority' => $data['priority'],
            'message' => $data['message'],
            'assigned' => '0'
        );

        $response = $this->apiRequest('tickets/create', 'POST', $payload);

        if (is_array($response) && isset($response['status']) && $response['status'] === 'success') {
            CE_Lib::addSuccessMessage(lang('Your ticket has been created successfully.'));
        } else {
            CE_Lib::addErrorMessage(lang('Unable to create ticket. Please contact support.'));
        }
    }

    private function ensureSynced($clientexecId, $email, $first, $last)
    {
        $mapping = $this->getMapping($clientexecId);

        if (is_array($mapping)) {
            return $mapping;
        }

        $existing = $this->findTicagaUserByEmail($email);
        if (!$existing) {
            $existing = $this->createTicagaUser($email, $first, $last);
        }

        if (is_array($existing) && !empty($existing['id'])) {
            $this->storeMapping($clientexecId, $existing['id'], $email);
            return array(
                'clientexec_user_id' => $clientexecId,
                'ticaga_user_id' => $existing['id'],
                'email' => $email
            );
        }

        return null;
    }

    private function getMapping($clientexecId)
    {
        $db = CE_Lib::db();
        $query = 'SELECT clientexec_user_id, ticaga_user_id, email FROM plugin_ticaga_accounts WHERE clientexec_user_id = ?';
        $result = $db->query($query, $clientexecId);

        if ($result && $result->getNumRows() > 0) {
            return $result->getRow();
        }

        return null;
    }

    private function storeMapping($clientexecId, $ticagaId, $email)
    {
        $db = CE_Lib::db();
        $sql = 'INSERT INTO plugin_ticaga_accounts (clientexec_user_id, ticaga_user_id, email, created_at, updated_at) '
            . 'VALUES (?, ?, ?, NOW(), NOW()) '
            . 'ON DUPLICATE KEY UPDATE ticaga_user_id = VALUES(ticaga_user_id), email = VALUES(email), updated_at = NOW()';

        $db->query($sql, $clientexecId, $ticagaId, $email);
    }

    private function findTicagaUserByEmail($email)
    {
        $response = $this->apiRequest('tickets/userinfobyemail/' . urlencode($email));
        if (is_array($response) && isset($response['status']) && $response['status'] === 'success') {
            $data = json_decode($response['response'], true);
            if (is_array($data) && isset($data['user']) && is_array($data['user']) && !empty($data['user']['id'])) {
                return $data['user'];
            }
        }
        return null;
    }

    private function createTicagaUser($email, $first, $last)
    {
        $payload = array(
            'email' => $email,
            'first_name' => $first,
            'last_name' => $last
        );

        $response = $this->apiRequest('customers/create', 'POST', $payload);
        if (is_array($response) && isset($response['status']) && $response['status'] === 'success') {
            $data = json_decode($response['response'], true);
            if (is_array($data) && isset($data['user']) && is_array($data['user']) && !empty($data['user']['id'])) {
                return $data['user'];
            }
        }

        return null;
    }

    private function fetchTickets($ticagaUserId)
    {
        $response = $this->apiRequest('tickets/list/' . (int)$ticagaUserId);
        if (is_array($response) && isset($response['status']) && $response['status'] === 'success') {
            $data = json_decode($response['response'], true);
            if (is_array($data) && isset($data['tickets']) && is_array($data['tickets'])) {
                return $data['tickets'];
            }
        }
        return array();
    }

    private function fetchDepartments()
    {
        $response = $this->apiRequest('departments/list');
        if (is_array($response) && isset($response['status']) && $response['status'] === 'success') {
            $data = json_decode($response['response'], true);
            if (is_array($data) && isset($data['departments']) && is_array($data['departments'])) {
                return $data['departments'];
            }
        }
        return array();
    }

    private function apiRequest($path, $method = 'GET', $payload = array())
    {
        if (empty($this->settings['api_url']) || empty($this->settings['api_key']) || empty($this->settings['api_email'])) {
            return null;
        }

        $url = rtrim($this->settings['api_url'], '/') . '/api/' . ltrim($path, '/');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        }

        $auth = base64_encode($this->settings['api_email'] . ':' . $this->settings['api_key']);
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $auth
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return array(
                'status' => 'error',
                'response' => json_encode(array('error' => $error))
            );
        }

        $status = 'success';
        if ($httpCode === 401 || $httpCode === 403) {
            $status = 'autherror';
        } elseif ($httpCode >= 400) {
            $status = 'error';
        }

        return array(
            'status' => $status,
            'response' => $result
        );
    }

    private function escape($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    private function loadSettings()
    {
        if (!class_exists('CE_Settings')) {
            require_once 'modules/admin/models/Settings.php';
        }

        $settings = new CE_Settings();
        $keys = array('api_url', 'api_email', 'api_key', 'allow_guests');
        $result = array();

        foreach ($keys as $key) {
            $result[$key] = $settings->get('plugin_ticaga_' . $key);
        }

        return $result;
    }
}
