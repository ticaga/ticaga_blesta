<?php
/**
 * TicagaSupport Parent Model
 *
 * @link https://ticaga.com/ Ticaga
 */
class TicagaSupportModel extends AppModel
{
    public function __construct()
    {
        parent::__construct();

        // Auto load language for these models
        Language::loadLang([Loader::fromCamelCase(get_class($this))], null, dirname(__FILE__) . DS . 'language' . DS);
    }
}
