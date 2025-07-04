<?php

/**
 * @file PaperbuzzSettingsForm.inc.php
 *
 * Copyright (c) 2013-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PaperbuzzSettingsForm
 * @brief Form for journal managers to modify Paperbuzz plugin settings
 */

namespace APP\plugins\generic\paperbuzz;

use PKP\form\Form;
use APP\core\Application;
use APP\template\TemplateManager;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorCSRF;

class PaperbuzzSettingsForm extends Form
{
    protected PaperbuzzPlugin $plugin;

    /**
     * Constructor
     * @param PaperbuzzPlugin $plugin
     */
    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidator($this, 'apiEmail', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.paperbuzz.settings.apiEmail.required'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData(): void
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if ($context) {
            $plugin = $this->plugin;
            foreach ($this->getFormFields() as $fieldName => $fieldType) {
                $fieldValue = $plugin->getSetting($context->getId(), $fieldName);
                if ($fieldName == 'apiEmail' && empty($fieldValue)) {
                    $fieldValue = $context->getData('supportEmail');
                }
                $this->setData($fieldName, $fieldValue);
            }
        }
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData(): void
    {
        $this->readUserVars(array_keys($this->getFormFields()));
    }

    /**
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = null, $display = false): string
    {
        $plugin = $this->plugin;
        $showMiniOptions = array(
            false => __('plugins.generic.paperbuzz.settings.showGraph'),
            true => __('plugins.generic.paperbuzz.settings.showMini'),
        );
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $plugin->getName());
        $templateMgr->assign('showMiniOptions', $showMiniOptions);
        return parent::fetch($request);
    }

    /**
     * Save settings.
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs): void
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if ($context) {
            $plugin = $this->plugin;
            foreach ($this->getFormFields() as $fieldName => $fieldType) {
                $plugin->updateSetting($context->getId(), $fieldName, $this->getData($fieldName), $fieldType);
            }
        }
    }

    /**
     * Get form fields
     * @return array (field name => field type)
     */
    public function getFormFields(): array
    {
        return [
            'apiEmail' => 'string',
            'hideDownloads' => 'bool',
            'showMini' => 'bool'
        ];
    }
}
