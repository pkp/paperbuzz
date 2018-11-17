<?php

/**
 * @file plugins/generic/alm/AlmSettingsForm.inc.php
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AlmSettingsForm
 * @ingroup plugins_generic_alm
 *
 * @brief Form for journal managers to modify ALM plugin settings
 */


import('lib.pkp.classes.form.Form');

class AlmSettingsForm extends Form {

	/** @var $plugin AlmPlugin */
	var $plugin;

	/**
	 * Constructor
	 * @param $plugin AlmPlugin
	 */
	function __construct($plugin) {
		$this->plugin = $plugin;
		parent::__construct($plugin->getTemplatePath() . 'settingsForm.tpl');
		$this->addCheck(new FormValidator($this, 'apiEmail', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.alm.settings.apiEmail.required'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * @copydoc Form::initData()
	 */
	function initData($request) {
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
	function readInputData() {
		$this->readUserVars(array_keys($this->getFormFields()));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$plugin = $this->plugin;
		$showMiniOptions = array(
			false => __('plugins.generic.alm.settings.showGraph'),
			true => __('plugins.generic.alm.settings.showMini'),
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
	function execute($request) {
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
	function getFormFields() {
		return array(
			'apiEmail' => 'string',
			'hideDownloads' => 'bool',
			'showMini' => 'bool'
		);
	}
}

?>
