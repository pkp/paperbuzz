<?php

/**
 * @file plugins/generic/paperbuzz/PaperbuzzPlugin.inc.php
 *
 * Copyright (c) 2013-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PaperbuzzPlugin
 * @ingroup plugins_generic_paperbuzz
 *
 * @brief Paperbuzz plugin class
 */

namespace APP\plugins\generic\paperbuzz;

use APP\core\Application;
use APP\core\Services;
use APP\plugins\generic\paperbuzz\PaperbuzzSettingsForm;
use APP\statistics\StatisticsHelper;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\cache\CacheManager;
use PKP\cache\FileCache;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;


class PaperbuzzPlugin extends GenericPlugin {

	public const PAPERBUZZ_API_URL = 'https://api.paperbuzz.org/v0/';

	private FileCache $_paperbuzzCache;
	private FileCache $_downloadsCache;
	private Submission $_article;

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null)
	{
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed')) return false;

		$request = $this->getRequest();
		$context = $request->getContext();
		if ($success && $this->getEnabled($mainContextId)) {
			$this->_registerTemplateResource();
			if ($context && $this->getSetting($context->getId(), 'apiEmail')) {
				// Add visualization to article view page
				Hook::add('Templates::Article::Main', array($this, 'articleMainCallback'));
				// Add visualization to preprint view page
				Hook::add('Templates::Preprint::Main', array(&$this, 'preprintMainCallback'));
				// Add JavaScript and CSS needed, when the article template is displyed
				Hook::add('TemplateManager::display', array(&$this, 'templateManagerDisplayCallback'));
			}
		}
		return $success;
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	function getName()
	{
		return 'PaperbuzzPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName()
	{
		return __('plugins.generic.paperbuzz.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription()
	{
		return __('plugins.generic.paperbuzz.description');
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	public function getActions($request, $actionArgs)
	{
		$actions = parent::getActions($request, $actionArgs);
		// Settings are only context-specific
		if (!$this->getEnabled()) {
			return $actions;
		}
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$linkAction = new LinkAction(
			'settings',
			new AjaxModal(
				$router->url(
					$request,
					null,
					null,
					'manage',
					null,
					array(
						'verb' => 'settings',
						'plugin' => $this->getName(),
						'category' => 'generic'
					)
				),
				$this->getDisplayName()
			),
			__('manager.plugins.settings'),
			null
		);
		array_unshift($actions, $linkAction);
		return $actions;
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	public function manage($args, $request)
	{
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$form = new PaperbuzzSettingsForm($this);
				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute($request);
						return new JSONMessage(true);
					}
				}
				$form->initData($request);
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	/**
	 * Template manager hook callback.
	 * Add JavaScript and CSS required for the visualization.
	 */
	function templateManagerDisplayCallback(string $hookName, array $params)
	{
		$templateMgr =& $params[0];
		$template =& $params[1];
		$application = Application::get();
		$applicationName = $application->getName();
		($applicationName == 'ops' ? $publication = 'preprint' : $publication = 'article');
		if ($template == 'frontend/pages/' . $publication . '.tpl') {
			$request = $this->getRequest();
			$baseImportPath = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/' . 'paperbuzzviz' . '/';
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->addJavaScript('d3', 'https://d3js.org/d3.v4.min.js', array('context' => 'frontend-'.$publication.'-view'));
			$templateMgr->addJavaScript('d3-tip', 'https://cdnjs.cloudflare.com/ajax/libs/d3-tip/0.9.1/d3-tip.min.js', array('context' => 'frontend-'.$publication.'-view'));
			$templateMgr->addJavaScript('paperbuzzvizJS', $baseImportPath . 'paperbuzzviz.js', array('context' => 'frontend-'.$publication.'-view'));
			$templateMgr->addStyleSheet('paperbuzzvizCSS', $baseImportPath . 'assets/css/paperbuzzviz.css', array('context' => 'frontend-'.$publication.'-view'));
		}
	}

	/**
	 * Adds the visualization of the preprint level metrics.
	 */
	function preprintMainCallback(string $hookName, array $params): bool
	{
		$smarty = &$params[1];
		$output = &$params[2];

		$preprint = $smarty->getTemplateVars('preprint');
		$this->_article = $preprint;

		$originalPublication = $this->_article->getOriginalPublication();

		$request = $this->getRequest();
		$context = $request->getContext();

		$paperbuzzJsonDecoded = $this->_getPaperbuzzJsonDecoded();
		$downloadJsonDecoded = [];
		if (!$this->getSetting($context->getId(), 'hideDownloads')) {
			$downloadJsonDecoded = $this->_getDownloadsJsonDecoded();
		}

		if (!empty($downloadJsonDecoded) || !empty($paperbuzzJsonDecoded)) {
			$allStatsJson = $this->_buildRequiredJson($paperbuzzJsonDecoded, $downloadJsonDecoded);
			$smarty->assign('allStatsJson', $allStatsJson);

			if (!empty($originalPublication->getData('datePublished'))) {
				$datePublishedShort = date('[Y, n, j]', strtotime($originalPublication->getData('datePublished')));
				$smarty->assign('datePublished', $datePublishedShort);
			}

			$showMini = $this->getSetting($context->getId(), 'showMini') ? 'true' : 'false';
			$smarty->assign('showMini', $showMini);
			$metricsHTML = $smarty->fetch($this->getTemplateResource('output.tpl'));
			$output .= $metricsHTML;
		}

		return false;
	}

	/**
	 * Adds the visualization of the article level metrics.
	 */
	function articleMainCallback(string $hookName, array $params): bool
	{
		$smarty =& $params[1];
		$output =& $params[2];

		$article = $smarty->getTemplateVars('article');
		$this->_article = $article;

		$originalPublication = $this->_article->getOriginalPublication();

		$request = $this->getRequest();
		$context = $request->getContext();

		$paperbuzzJsonDecoded = $this->_getPaperbuzzJsonDecoded();
		$downloadJsonDecoded = [];
		if (!$this->getSetting($context->getId(), 'hideDownloads')) {
			$downloadJsonDecoded = $this->_getDownloadsJsonDecoded();
		}

		if (!empty($downloadJsonDecoded) || !empty($paperbuzzJsonDecoded)) {
			$allStatsJson = $this->_buildRequiredJson($paperbuzzJsonDecoded, $downloadJsonDecoded);
			$smarty->assign('allStatsJson', $allStatsJson);

			if (!empty($originalPublication->getData('datePublished'))) {
				$datePublishedShort = date('[Y, n, j]', strtotime($originalPublication->getData('datePublished')));
				$smarty->assign('datePublished', $datePublishedShort);
			}

			$showMini = $this->getSetting($context->getId(), 'showMini') ? 'true' : 'false';
			$smarty->assign('showMini', $showMini);
			$metricsHTML = $smarty->fetch($this->getTemplateResource('output.tpl'));
			$output .= $metricsHTML;
		}

		return false;
	}

	//
	// Private helper methods.
	//
	/**
	 * Get Paperbuzz events for the article.
	 *
	 * @return array JSON decoded paperbuzz result or an empty array
	 */
	function _getPaperbuzzJsonDecoded(): array
	{
		if (!isset($this->_paperbuzzCache)) {
			$cacheManager = CacheManager::getManager();
			$this->_paperbuzzCache = $cacheManager->getCache('paperbuzz', $this->_article->getId(), array(&$this, '_paperbuzzCacheMiss'));
		}
		if (time() - $this->_paperbuzzCache->getCacheTime() > 60 * 60 * 24) {
			// Cache is older than one day, erase it.
			$this->_paperbuzzCache->flush();
		}
		$cacheContent = $this->_paperbuzzCache->getContents() ?? [];
		return $cacheContent;
	}

	/**
	* Cache miss callback.
	*
	* @param FileCache $cache
	* @return array JSON decoded paperbuzz result or an empty array
	*/
	function _paperbuzzCacheMiss(FileCache $cache): array
	{
		$request = $this->getRequest();
		$context = $request->getContext();
		$apiEmail = $this->getSetting($context->getId(), 'apiEmail');

		$url = self::PAPERBUZZ_API_URL . 'doi/' . $this->_article->getCurrentPublication()->getDoi() . '?email=' . urlencode($apiEmail);
		// For teting use one of the following two lines instead of the line above and do not forget to clear the cache
		// $url = self::PAPERBUZZ_API_URL . 'doi/10.1787/180d80ad-en?email=' . urlencode($apiEmail);
		//$url = self::PAPERBUZZ_API_URL . 'doi/10.1371/journal.pmed.0020124?email=' . urlencode($apiEmail);

		$paperbuzzStatsJsonDecoded = [];
		$httpClient = Application::get()->getHttpClient();
		try {
			$response = $httpClient->request('GET', $url);
		} catch (\GuzzleHttp\Exception\RequestException $e) {
			return $paperbuzzStatsJsonDecoded;
		}
		$resultJson = $response->getBody()->getContents();
		if ($resultJson) {
			$paperbuzzStatsJsonDecoded = @json_decode($resultJson, true);
		}
		$cache->setEntireCache($paperbuzzStatsJsonDecoded);

		return $paperbuzzStatsJsonDecoded;
	}

	/**
	 * Get OJS download stats for the article.
	 */
	function _getDownloadsJsonDecoded(): array
	{
		if (!isset($this->_downloadsCache)) {
			$cacheManager = CacheManager::getManager();
			$this->_downloadsCache = $cacheManager->getCache('paperbuzz-downloads', $this->_article->getId(), array(&$this, '_downloadsCacheMiss'));
		}
		if (time() - $this->_downloadsCache->getCacheTime() > 60 * 60 * 24) {
			// Cache is older than one day, erase it.
			$this->_downloadsCache->flush();
		}
		$cacheContent = $this->_downloadsCache->getContents() ?? [];
		return $cacheContent;
	}

	/**
	 * Callback to fill cache with data, if empty.
	 */
	function _downloadsCacheMiss(FileCache $cache): array
	{
		// Note: monthly and daily stats needs to be separated into different calls, because
		// we need daily stats only for the first 30 days of the publication.
		// Stats per year could be calculated from the montly stats, but we use the extra call for this too.
		$downloadStatsByYear = $this->_getDownloadStats( StatisticsHelper::STATISTICS_DIMENSION_YEAR);
		$downloadStatsByMonth = $this->_getDownloadStats( StatisticsHelper::STATISTICS_DIMENSION_MONTH);
		$downloadStatsByDay = $this->_getDownloadStats(StatisticsHelper::STATISTICS_DIMENSION_DAY);

		// Prepare stats data so that they can be overtaken for the custom array format i.e. JSON response.
		list($total, $byDay, $byMonth, $byYear) = $this->_prepareDownloadStats($downloadStatsByYear, $downloadStatsByMonth, $downloadStatsByDay);
		$downloadsArray = $this->_buildDownloadStatsJsonDecoded($total, $byDay, $byMonth, $byYear);

		$cache->setEntireCache($downloadsArray);
		return $downloadsArray;
	}

	/**
	 * Get download stats for the passed article ID, aggregated by the given time interval.
	 */
	function _getDownloadStats(string $timelineInterval): array
	{
		$context = $this->getRequest()->getContext();
        $datePublished = $this->_article->getOriginalPublication()->getData('datePublished');
		$dateStart = date('Y-m-d', strtotime($datePublished));
		$dateEnd = date('Y-m-d', strtotime('yesterday'));
		if ($timelineInterval == StatisticsHelper::STATISTICS_DIMENSION_DAY) {
			// Consider only the first 30 days after the article publication
			$dateEnd = date('Y-m-d', strtotime('+30 days', strtotime($datePublished)));
		}

		$filters = [
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'contextIds' => [$context->getId()],
			'submissionIds' => [$this->_article->getId()],
            'assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE]
        ];

		$metricsQB = Services::get('publicationStats')->getQueryBuilder($filters);
        $metricsQB = $metricsQB->getSum([$timelineInterval]);
        $metricsQB->orderBy($timelineInterval, StatisticsHelper::STATISTICS_ORDER_ASC);
        $data = $metricsQB->get()->toArray();
		return $data;
	}

	/**
	 * Prepare stats to return data in a format
	 * that can be used to build the statistics JSON response
	 * for the article page.
	 */
	function _prepareDownloadStats(array $statsByYear, array $statsByMonth, array $statsByDay): array
	{
		$total = 0;
		$byYear = [];
		$byMonth = [];
		$byDay = [];

		foreach ($statsByYear as $yearlyData) {
			$yearlyData = (array) $yearlyData;
			$yearEvent = [];
			$yearEvent['count'] = $yearlyData['metric'];
			$date = $yearlyData['year'];
			$yearEvent['date'] = substr($date, 0, 4);
			$byYear[] = $yearEvent;
			$total += $yearlyData['metric'];
		}

		foreach ($statsByMonth as $monthlyData) {
			$monthlyData = (array) $monthlyData;
			$monthEvent = [];
			$monthEvent['count'] = $monthlyData['metric'];
			$date = $monthlyData['month'];
			$monthEvent['date'] = substr($date, 0, 7);
			$byMonth[] = $monthEvent;
		}

		foreach ($statsByDay as $dailyData) {
			$dailyData = (array) $dailyData;
			$dayEvent = [];
			$dayEvent['count'] = $dailyData['metric'];
			$date = $dailyData['day'];
			$dayEvent['date'] = $date;
			$byDay[] = $dayEvent;
		}

		return array($total, $byDay, $byMonth, $byYear);
	}

	/**
	 * Build article download statistics array ready for JSON response
	 */
	function _buildDownloadStatsJsonDecoded(int $total, array $byDay, array $byMonth, array $byYear): array
	{
		$response = [];
		$event = [];
		if ($total > 0) {
			$event['events'] = null;
			$event['events_count'] = $total;
			$event['events_count_by_day'] = $byDay;
			$event['events_count_by_month'] = $byMonth;
			$event['events_count_by_year'] = $byYear;
			$event['source']['display_name'] = __('plugins.generic.paperbuzz.sourceName.fileDownloads');
			$event['source_id'] = 'fileDownloads';
			$response[] = $event;
		}
		return $response;
	}

	/**
	 * Build the required article information for the
	 * metrics visualization.
	 *
	 * @param array $eventsData Decoded JSON result from Paperbuzz
	 * @param array $downloadData Download stats data ready for JSON encoding
	 * @return string JSON response
	 */
	function _buildRequiredJson(array $eventsData = [], array $downloadData = []): string
	{
		if (empty($eventsData['altmetrics_sources'])) $eventsData['altmetrics_sources'] = [];
		$allData = array_merge($downloadData, $eventsData['altmetrics_sources']);
		$eventsData['altmetrics_sources'] = $allData;
		return json_encode($eventsData);
	}
}
