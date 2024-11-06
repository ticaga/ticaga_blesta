<?php
/**
 * TicagaSupport parent controller
 *
 * @link https://ticaga.com/ Ticaga
 */
class TicagaSupportController extends AppController
{
    /**
     * Require admin to be login and setup the view
     */
    public function preAction()
    {
        $this->structure->setDefaultView(APPDIR);
        parent::preAction();

        $this->view->view = "default";

        // Auto load language for the controller
        Language::loadLang(
            [Loader::fromCamelCase(parent::class)],
            null,
            dirname(__FILE__) . DS . 'language' . DS
        );
        Language::loadLang(
            'ticaga_support_controller',
            null,
            dirname(__FILE__) . DS . 'language' . DS
        );
    }
}
