<?php

/**
 * @file plugins/generic/paperbuzz/PaperbuzzSettingsForm.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PaperbuzzSettingsForm
 * @ingroup plugins_generic_paperbuzz
 *
 * @brief Form for journal managers to modify Paperbuzz plugin settings
 */

use PKP\form\Form;
use APP\core\Application;
use APP\template\TemplateManager;

class PaperbuzzSettingsForm extends Form {

	protected PaperbuzzPlugin $plugin;

	/**
	 * Constructor
	 * @param PaperbuzzPlugin $plugin
	 */
	function __construct($plugin)
	{
		$this->plugin = $plugin;
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
		$this->addCheck(new \PKP\form\validation\FormValidator($this, 'apiEmail', \PKP\form\validation\FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.paperbuzz.settings.apiEmail.required'));
		$this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
		$this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
	}

	/**
	 * @copydoc Form::initData()
	 */
	function initData()
	{
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		if ($context) {
			$plugin = $this->plugin;
			foreach($this->getFormFields() as $fieldName => $fieldType) {
				$fieldValue = $plugin->getSetting($context->getId(), $fieldName);
				if ($fieldName == 'apiEmail' && empty($fieldValue)) {
					$fieldValue = $context->getSetting('supportEmail');
				}
				$this->setData($fieldName, $fieldValue);
			}
		}
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData()
	{
		$this->readUserVars(array_keys($this->getFormFields()));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = NULL, $display = false)
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
	function execute(...$functionArgs)
	{
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		if ($context) {
			$plugin = $this->plugin;
			foreach($this->getFormFields() as $fieldName => $fieldType) {
				$plugin->updateSetting($context->getId(), $fieldName, $this->getData($fieldName), $fieldType);
			}
		}
	}

	/**
	 * Get form fields
	 * @return array (field name => field type)
	 */
	function getFormFields(): array
	{
		return [
			'apiEmail' => 'string',
			'hideDownloads' => 'bool',
			'showMini' => 'bool'
		];
	}
}
