<?php
/**
 * ticaga_blesta_settings Management
 *
 * @link https://ticaga.com/ Ticaga
 */
class TicagaSettings extends TicagaSupportModel
{
    /**
     * Returns a list of records for the given company
     *
     * @param array $filters A list of filters for the query
     *
     *  - api_key
     *  - api_url
     * @param int $page The page number of results to fetch
     * @param array $order A key/value pair array of fields to order the results by
     * @return array An array of stdClass objects
     */
    public function getList(
        array $filters = [],
        $page = 1,
        array $order = ['api_url' => 'desc']
    ) {
        $records = $this->getRecord($filters)
            ->order($order)
            ->limit($this->getPerPage(), (max(1, $page) - 1) * $this->getPerPage())
            ->fetchAll();

        return $records;
    }

    /**
     * Returns the total number of record for the given filters
     *
     * @param array $filters A list of filters for the query
     *
     *  - api_key
     *  - api_url
     * @return int The total number of records for the given filters
     */
    public function getListCount(array $filters = [])
    {
        return $this->getRecord($filters)->numResults();
    }

    /**
     * Returns all records in the system for the given filters
     *
     * @param array $filters A list of filters for the query
     *
     *  - api_key
     *  - api_url
     * @param array $order A key/value pair array of fields to order the results by
     * @return array An array of stdClass objects
     */
    public function getAll(
        array $filters = [],
        array $order = ['api_url' => 'desc']
    ) {
        $records = $this->getRecord($filters)->order($order)->fetchAll();

        return $records;
    }

    /**
     * Fetches the record with the given identifier
     *
     * @param int $api_url The identifier of the record to fetch
     * @return mixed A stdClass object representing the record, false if no such record exists
     */
    public function get($api_url)
    {
        $record = $this->getRecord(['api_url' => $api_url])->fetch();

        return $record;
    }
	
	/**
     * Fetches the record with the given identifier
     *
     * @param int $api_url The identifier of the record to fetch
     * @return mixed A stdClass object representing the record, false if no such record exists
     */
    public function getAPIKey($api_url)
    {
        $record = $this->getRecord(['api_url' => $api_url])->fetch();
		if ($record !== false) {
		$apiKey = $record->api_key;	
		return $apiKey;
		} else {
		return false;
		}
    }
	
	/**
     * Fetches the record with the given identifier
     *
     * @param int $api_url The identifier of the record to fetch
     * @return mixed A stdClass object representing the record, false if no such record exists
     */
    public function getAPIKeyExists($api_url)
    {
        $record = $this->getRecord(['api_url' => $api_url])->fetch();
		if ($record !== false) {
		$apiKey = $record->api_key;	
		return true;
		} else {
		return false;
		}
    }
	
	public function validateAPISuccessResponse($resparray)
	{
		if ($resparray != null)
		{
			if ($resparray['status'] == "success")
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
	Calls the API to do requested Actions(Get Request)
	*/
	public function callAPI($action,$apiURL,$apiKey)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,  $apiURL . "/api/" . $action);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        
		$result = curl_exec($ch);
		if (curl_errno($ch)) {
		echo 'Error:' . curl_error($ch);
		}
		$httprespcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close ($ch);
	if ($result == null)
	{
	return array("response" => $result, "status" => "noresponse");
	} else if ($httprespcode == 403 || $httprespcode == 401) {
	return array("response" => $result, "status" => "autherror");
	} else if ($httprespcode == 404) {
	return array("response" => $result, "status" => "notfound");
	} else {
	return array("response" => $result, "status" => "success");
	}
	}
	
			/**
	Calls the API to do requested Actions(POST Request)
	*/
	public function callAPIPost($action,$params,$apiURL,$apiKey)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $apiURL . "/api/" . $action);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $apiKey,
]);
		$result = curl_exec($ch);
		if (curl_errno($ch)) {
		echo 'Error:' . curl_error($ch);
		}
		$httprespcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close ($ch);
	if ($result == null)
	{
	return array("response" => $result, "status" => "noresponse");
	} else if ($httprespcode == 403 || $httprespcode == 401) {
	return array("response" => $result, "status" => "autherror");
	} else {
	return array("response" => $result, "status" => "success");
	}
	}

    /**
     * Add a record
     *
     * @param array $vars An array of input data including:
     *
     *  - api_key
     *  - api_url
     * @return int The identifier of the record that was created, void on error
     */
    public function add(array $vars)
    {
        $this->Input->setRules($this->getRules($vars));

        if ($this->Input->validates($vars)) {
            $fields = ['api_key','api_url','company_id'];
			$this->Record->duplicate("api_url", "=", $vars['api_url'])->insert("ticaga_blesta_settings", array('api_key' => $vars['api_key'],'api_url' => $vars['api_url'], 'company_id' => $vars['company_id']));

			$apikeyInfo = $this->getAPIKeyExists($vars['api_url']);
			if ($apikeyInfo == true)
			{
				return true;
			} else {
				return $this->Record->lastInsertId();
			}
        }
    }

    /**
     * Edit a record
     *
     * @param int $api_url The identifier of the record to edit
     * @param array $vars An array of input data including:
     *
     *  - api_key
     *  - api_url
     * @return int The identifier of the record that was updated, void on error
     */
    public function edit($api_url, array $vars)
    {
        
        $vars['api_url'] = $api_url;
        $this->Input->setRules($this->getRules($vars, true));

        if ($this->Input->validates($vars)) {
            $fields = ['api_key','api_url'];
            $this->Record->where('api_url', '=', $api_url)->update('ticaga_blesta_settings', $vars, $fields);

            return $api_url;
        }
    }

    /**
     * Permanently deletes the given record
     *
     * @param int $api_url The identifier of the record to delete
     */
    public function delete($api_url)
    {
        // Delete a record
        $this->Record->from('ticaga_blesta_settings')->
            where('ticaga_blesta_settings.api_url', '=', $api_url)->
            delete();
    }

    /**
     * Returns a partial query
     *
     * @param array $filters A list of filters for the query
     *
     *  - api_key
     *  - api_url
     * @return Record A partially built query
     */
    private function getRecord(array $filters = [])
    {
        $this->Record->select()->from('ticaga_blesta_settings');

        if (isset($filters['api_key'])) {
            $this->Record->where('ticaga_blesta_settings.api_key', '=', $filters['api_key']);
        }

        if (isset($filters['api_url'])) {
            $this->Record->where('ticaga_blesta_settings.api_url', '=', $filters['api_url']);
        }
		
		if (isset($filters['company_id'])) {
            $this->Record->where('ticaga_blesta_settings.company_id', '=', $filters['company_id']);
        }

        return $this->Record;
    }

    /**
     * Returns all validation rules for adding/editing extensions
     *
     * @param array $vars An array of input key/value pairs
     *
     *  - api_key
     *  - api_url
     * @param bool $edit True if this if an edit, false otherwise
     * @return array An array of validation rules
     */
    private function getRules(array $vars, $edit = false)
    {
        $rules = [
            'api_key' => [
                'valid' => [
                    'if_set' => $edit,
                    'rule' => true,
                    'message' => Language::_('TicagaBlestaSettings.!error.api_key.valid', true)
                ]
            ],
            'api_url' => [
                'valid' => [
                    'if_set' => $edit,
                    'rule' => true,
                    'message' => Language::_('TicagaBlestaSettings.!error.api_url.valid', true)
                ]
            ],
			'company_id' => [
                'valid' => [
                    'if_set' => $edit,
                    'rule' => true,
                    'message' => Language::_('TicagaBlestaSettings.!error.company_id.valid', true)
                ]
            ]
        ];

        return $rules;
    }
}
