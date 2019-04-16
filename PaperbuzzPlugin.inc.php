<?php

/**
 * @file plugins/generic/paperbuzz/PaperbuzzPlugin.inc.php
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PaperbuzzPlugin
 * @ingroup plugins_generic_paperbuzz
 *
 * @brief Paperbuzz plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.webservice.WebService');

DEFINE('PAPERBUZZ_API_URL', 'https://api.paperbuzz.org/v0/');

class PaperbuzzPlugin extends GenericPlugin {

	/** @var $_paperbuzzCache FileCache */
	var $_paperbuzzCache;

	/** @var $_downloadsCache FileCache */
	var $_downloadsCache;

	/** @var $_article PublishedArticle */
	var $_article;

	/**
	 * @see LazyLoadPlugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed')) return false;

		$request = $this->getRequest();
		$context = $request->getContext();
		if ($success && $this->getEnabled($mainContextId)) {
			$this->_registerTemplateResource();
			if ($context && $this->getSetting($context->getId(), 'apiEmail')) {
				// Add visualization to article view page
				HookRegistry::register('Templates::Article::Main', array($this, 'articleMainCallback'));
				// Add JavaScript and CSS needed, when the article template is displyed
				HookRegistry::register('TemplateManager::display',array(&$this, 'templateManagerDisplayCallback'));
			}
		}
		return $success;
	}

	/**
	 * @see LazyLoadPlugin::getName()
	 */
	function getName() {
		return 'PaperbuzzPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.paperbuzz.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.paperbuzz.description');
	}

	/**
	 * @see Plugin::getActions()
	 */
	public function getActions($request, $actionArgs) {
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
	 * @see Plugin::manage()
	 */
	public function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$this->import('PaperbuzzSettingsForm');
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
	 * @param $hookName string
	 * @param $params array
	 */
	function templateManagerDisplayCallback($hookName, $params) {
		$templateMgr =& $params[0];
		$template =& $params[1];
		if ($template == 'frontend/pages/article.tpl') {
			$request = $this->getRequest();
			$baseImportPath = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/' .'paperbuzzviz' . '/';
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->addJavaScript('d3','https://d3js.org/d3.v4.min.js', array('context' => 'frontend-article-view'));
			$templateMgr->addJavaScript('d3-tip', 'https://cdnjs.cloudflare.com/ajax/libs/d3-tip/0.9.1/d3-tip.min.js', array('context' => 'frontend-article-view'));
			$templateMgr->addJavaScript('paperbuzzvizJS', $baseImportPath . 'paperbuzzviz.js', array('context' => 'frontend-article-view'));
			$templateMgr->addStyleSheet('paperbuzzvizCSS', $baseImportPath . 'assets/css/paperbuzzviz.css', array('context' => 'frontend-article-view'));
		}
	}

	/**
	 * Adds the visualization of the article level metrics.
	 * @param $hookName string
	 * @param $params array
	 * @return boolean
	 */
	function articleMainCallback($hookName, $params) {
		$smarty =& $params[1];
		$output =& $params[2];

		$article = $smarty->getTemplateVars('article');
		assert(is_a($article, 'PublishedArticle'));
		$this->_article = $article;

		$request = $this->getRequest();
		$context = $request->getContext();

		$paperbuzzJsonDecoded = $this->_getPaperbuzzJsonDecoded();
		$downloadJsonDecoded = array();
		if (!$this->getSetting($context->getId(), 'hideDownloads')) {
			$downloadJsonDecoded = $this->_getDownloadsJsonDecoded();
		}

		if (!empty($downloadJsonDecoded) || !empty($paperbuzzJsonDecoded)) {
			$allStatsJson = $this->_buildRequiredJson($paperbuzzJsonDecoded, $downloadJsonDecoded);
			$smarty->assign('allStatsJson', $allStatsJson);

			$datePublished = $this->_article->getDatePublished();
			if (!empty($datePublished)) {
				$datePublishedShort = date('[Y, n, j]', strtotime($datePublished));
			}

			$smarty->assign('datePublished', $datePublishedShort);
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
	 * @return string JSON message
	 */
	function _getPaperbuzzJsonDecoded() {
		if (!isset($this->_paperbuzzCache)) {
			$cacheManager = CacheManager::getManager();
			$this->_paperbuzzCache = $cacheManager->getCache('paperbuzz', $this->_article->getId(), array(&$this, '_paperbuzzCacheMiss'));
		}
		if (time() - $this->_paperbuzzCache->getCacheTime() > 60 * 60 * 24) {
			// Cache is older than one day, erase it.
			$this->_paperbuzzCache->flush();
		}
		$cacheContent = $this->_paperbuzzCache->getContents();
		return $cacheContent;
	}

	/**
	* Cache miss callback.
	* @param $cache Cache
	* @param $articleId int
	* @return JSON
	*/
	function _paperbuzzCacheMiss($cache, $articleId) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$apiEmail = $this->getSetting($context->getId(), 'apiEmail');
		// Construct the parameters to send to the web service
		$searchParams = array(
			'email' => $apiEmail,
		);

		// Call the web service (URL defined at top of this file)
		$resultJson = $this->_callWebService(PAPERBUZZ_API_URL . 'doi/' . $this->_article->getStoredPubId('doi'), $searchParams);
		// For teting use one of the following two lines instead of the line above and do not forget to clear the cache
		//$resultJson = $this->_callWebService(PAPERBUZZ_API_URL . 'doi/' . '10.1787/180d80ad-en', $searchParams);
		//$resultJson = $this->_callWebService(PAPERBUZZ_API_URL . 'doi/' . '10.1371/journal.pmed.0020124', $searchParams);

		$paperbuzzStatsJsonDecoded = array();
		if ($resultJson) {
			$paperbuzzStatsJsonDecoded = @json_decode($resultJson, true);
		}
		$cache->setEntireCache($paperbuzzStatsJsonDecoded);
		return $paperbuzzStatsJsonDecoded;
	}

	/**
	 * Call web service with the given parameters
	 * @param $url string
	 * @param $params array GET or POST parameters
	 * @param $method string (optional)
	 * @return JSON or null in case of error
	 */
	function &_callWebService($url, &$params, $method = 'GET') {
		// Create a request
		if (!is_array($params)) {
			$params = array();
		}
		$webServiceRequest = new WebServiceRequest($url, $params, $method);
		// Configure and call the web service
		$webService = new WebService();
		$webService->setSslVersion(CURL_SSLVERSION_TLSv1_2);
		$result =& $webService->call($webServiceRequest);

		return $result;
	}

	/**
	 * Get OJS download stats for the article.
	 * @return array
	 */
	function _getDownloadsJsonDecoded() {
		if (!isset($this->_downloadsCache)) {
			$cacheManager = CacheManager::getManager();
			$this->_downloadsCache = $cacheManager->getCache('paperbuzz-downloads', $this->_article->getId(), array(&$this, '_downloadsCacheMiss'));
		}
		if (time() - $this->_downloadsCache->getCacheTime() > 60 * 60 * 24) {
			// Cache is older than one day, erase it.
			$this->_downloadsCache->flush();
		}
		$cacheContent = $this->_downloadsCache->getContents();
		return $cacheContent;
	}

	/**
	 * Callback to fill cache with data, if empty.
	 * @param $cache FileCache
	 * @param $articleId int
	 * @return array
	 */
	function _downloadsCacheMiss($cache, $articleId) {
		$downloadStatsByMonth = $this->_getDownloadStats();
		$downloadStatsByDay = $this->_getDownloadStats(true);

		// We use a helper method to aggregate stats instead of retrieving the needed
		// aggregation directly from metrics DAO because we need a custom array format.
		list($totalHtml, $totalPdf, $totalOther, $byDay, $byMonth, $byYear) = $this->_aggregateDownloadStats($downloadStatsByMonth, $downloadStatsByDay);
		$downloadsArray = $this->_buildDownloadStatsJsonDecoded($totalHtml, $totalPdf, $totalOther, $byDay, $byMonth, $byYear);

		$cache->setEntireCache($downloadsArray);
		return $downloadsArray;
	}

	/**
	 * Get download stats for the passed article id.
	 * @param $byDay boolean
	 * @return array MetricsDAO::getMetrics() result.
	 */
	function _getDownloadStats($byDay = false) {
		// Pull in download stats for each article galley.
		$request = $this->getRequest();
		$context = $request->getContext(); /* @var $context Journal */

		$metricsDao =& DAORegistry::getDAO('MetricsDAO'); /* @var $metricsDao MetricsDAO */

		// Load the metric type constant.
		PluginRegistry::loadCategory('reports');

		// Only consider the journal's default metric type, mostly ojs::counter
		$dateColumn = $byDay ? STATISTICS_DIMENSION_DAY : STATISTICS_DIMENSION_MONTH;
		$metricTypes = array($context->getDefaultMetricType());
		$columns = array($dateColumn, STATISTICS_DIMENSION_FILE_TYPE);
		$filter = array(STATISTICS_DIMENSION_ASSOC_TYPE => ASSOC_TYPE_SUBMISSION_FILE, STATISTICS_DIMENSION_SUBMISSION_ID => $this->_article->getId());
		$orderBy = array($dateColumn => STATISTICS_ORDER_ASC);

		if ($byDay) {
			// Consider only the first 30 days after the article publication
			$datePublished = $this->_article->getDatePublished();
			if (empty($datePublished)) {
				$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
				$issue = $issueDao->getById($this->_article->getIssueId());
				$datePublished = $issue->getDatePublished();
			}
			$startDate = date('Ymd', strtotime($datePublished));
			$endDate = date('Ymd', strtotime('+30 days', strtotime($datePublished)));
			// This would be for the last 30 days:
			//$startDate = date('Ymd', strtotime('-30 days'));
			//$endDate = date('Ymd');
			$filter[STATISTICS_DIMENSION_DAY]['from'] = $startDate;
			$filter[STATISTICS_DIMENSION_DAY]['to'] = $endDate;
		}

		return $metricsDao->getMetrics($metricTypes, $columns, $filter, $orderBy);
	}

	/**
	 * Aggregate stats and return data in a format
	 * that can be used to build the statistics JSON response
	 * for the article page.
	 * @param $statsByMonth null|array A _getDownloadStats return value.
	 * @param $statsByDay null|array A _getDownloadStats return value.
	 * @return array
	 */
	function _aggregateDownloadStats($statsByMonth, $statsByDay) {
		$totalHtml = 0;
		$totalPdf = 0;
		$totalOther = 0;
		$byMonth = array();
		$byYear = array();

		if (!is_array($stats)) $stats = array();

		if ($statsByMonth) foreach ($statsByMonth as $record) {
			$views = $record[STATISTICS_METRIC];
			$fileType = $record[STATISTICS_DIMENSION_FILE_TYPE];
			switch($fileType) {
				case STATISTICS_FILE_TYPE_HTML:
					$totalHtml += $views;
					break;
				case STATISTICS_FILE_TYPE_PDF:
					$totalPdf += $views;
					break;
				case STATISTICS_FILE_TYPE_OTHER:
					$totalOther += $views;
					break;
				default:
					// switch is considered a loop for purposes of continue
					continue 2;
			}
			$year = date('Y', strtotime($record[STATISTICS_DIMENSION_MONTH]. '01'));
			$month = date('n', strtotime($record[STATISTICS_DIMENSION_MONTH] . '01'));
			$yearMonth = date('Y-m', strtotime($record[STATISTICS_DIMENSION_MONTH] . '01'));

			if (!isset($byYear[$year])) $byYear[$year] = array();
			if (!isset($byYear[$year][$fileType])) $byYear[$year][$fileType] = 0;
			$byYear[$year][$fileType] += $views;

			if (!isset($byMonth[$yearMonth])) $byMonth[$yearMonth] = array();
			if (!isset($byMonth[$yearMonth][$fileType])) $byMonth[$yearMonth][$fileType] = 0;
			$byMonth[$yearMonth][$fileType] += $views;
		}

		// Get daily download statistics
		$byDay = array();
		if ($statsByDay) foreach ($statsByDay as $recordByDay) {
			$views = $recordByDay[STATISTICS_METRIC];
			$fileType = $recordByDay[STATISTICS_DIMENSION_FILE_TYPE];
			$yearMonthDay = date('Y-m-d', strtotime($recordByDay[STATISTICS_DIMENSION_DAY]));
			if (!isset($byDay[$yearMonthDay])) $byDay[$yearMonthDay] = array();
			if (!isset($byDay[$yearMonthDay][$fileType])) $byDay[$yearMonthDay][$fileType] = 0;
			$byDay[$yearMonthDay][$fileType] += $views;
		}

		return array($totalHtml, $totalPdf, $totalOther, $byDay, $byMonth, $byYear);
	}


	/**
	 * Get statistics by time dimension (month or year) for JSON response.
	 * @param $data array the download statistics in an array (date => file type)
	 * @param $dimension string day | month | year
	 * @param $fileType STATISTICS_FILE_TYPE_PDF | STATISTICS_FILE_TYPE_HTML | STATISTICS_FILE_TYPE_OTHER
	 * @return array|null
	 */
	function _getDownloadStatsByTime($data, $dimension, $fileType) {
		switch ($dimension) {
			case 'day':
				$isDayDimension = true;
				break;
			case 'month':
				$isMonthDimension = true;
				break;
			case 'year':
				$isYearDimension = false;
				break;
			default:
				return null;
		}

		if (count($data)) {
			$byTime = array();
			foreach ($data as $date => $fileTypes) {
				if ($isDayDimension) {
					$dateIndex = date('Y-m-d', strtotime($date));
				} elseif ($isMonthDimension) {
					$dateIndex = date('Y-m', strtotime($date));
				} elseif ($isYearDimension) {
					$dateIndex = date('Y', strtotime($date));
				}
				if (isset($fileTypes[$fileType])) {
					$event = array();
					$event['count'] = $fileTypes[$fileType];
					$event['date'] = $dateIndex;

					$byTime[] = $event;
				}
			}
		} else {
			$byTime = null;
		}
		return $byTime;
	}

	/**
	 * Build article stats JSON response based
	 * on parameters returned from _aggregateStats().
	 * @param $totalHtml array
	 * @param $totalPdf array
	 * @param $totalOther array
	 * @param $byDay array
	 * @param $byMonth array
	 * @param $byYear array
	 * @return array ready for JSON encoding
	 */
	function _buildDownloadStatsJsonDecoded($totalHtml, $totalPdf, $totalOther, $byDay, $byMonth, $byYear) {
		$response = array();
		$eventPdf = array();
		if ($totalPdf > 0) {
			$eventPdf['events'] = null;
			$eventPdf['events_count'] = $totalPdf;
			$eventPdf['events_count_by_day'] = $this->_getDownloadStatsByTime($byDay, 'day', STATISTICS_FILE_TYPE_PDF);
			$eventPdf['events_count_by_month'] = $this->_getDownloadStatsByTime($byMonth, 'month', STATISTICS_FILE_TYPE_PDF);
			$eventPdf['events_count_by_year'] = $this->_getDownloadStatsByTime($byYear, 'year', STATISTICS_FILE_TYPE_PDF);
			$eventPdf['source']['display_name'] = __('plugins.generic.paperbuzz.sourceName.pdf');
			$eventPdf['source_id'] = 'pdf';
			$response[] = $eventPdf;
		}

		$eventHtml = array();
		if ($totalHtml > 0) {
			$eventHtml['events'] = null;
			$eventHtml['events_count'] = $totalHtml;
			$eventHtml['events_count_by_day'] = $this->_getDownloadStatsByTime($byDay, 'day', STATISTICS_FILE_TYPE_HTML);
			$eventHtml['events_count_by_month'] = $this->_getDownloadStatsByTime($byMonth, 'month', STATISTICS_FILE_TYPE_HTML);
			$eventHtml['events_count_by_year'] = $this->_getDownloadStatsByTime($byYear, 'year', STATISTICS_FILE_TYPE_HTML);
			$eventHtml['source']['display_name'] = __('plugins.generic.paperbuzz.sourceName.html');
			$eventHtml['source_id'] = 'html';
			$response[] = $eventHtml;
		}

		$eventOther = array();
		if ($totalOther > 0) {
			$eventOther['events'] = null;
			$eventOther['events_count'] = $totalOther;
			$eventOther['events_count_by_day'] = $this->_getDownloadStatsByTime($byDay, 'day', STATISTICS_FILE_TYPE_OTHER);
			$eventOther['events_count_by_month'] = $this->_getDownloadStatsByTime($byMonth, 'month', STATISTICS_FILE_TYPE_OTHER);
			$eventOther['events_count_by_year'] = $this->_getDownloadStatsByTime($byYear, 'year', STATISTICS_FILE_TYPE_OTHER);
			$eventOther['source']['display_name'] = __('plugins.generic.paperbuzz.sourceName.other');
			$eventOther['source_id'] = 'other';
			$response[] = $eventOther;
		}

		return $response;
	}

	/**
	 * Build the required article information for the
	 * metrics visualization.
	 * @param $eventsData array (optional) Decoded JSON result from Paperbuzz
	 * @param $downloadData array (optional) Download stats data ready for JSON encoding
	 * @return string JSON response
	 */
	function _buildRequiredJson($eventsData = array(), $downloadData = array()) {
		if (empty($eventsData['altmetrics_sources'])) $eventsData['altmetrics_sources'] = array();
		$allData = array_merge($downloadData, $eventsData['altmetrics_sources']);
		$eventsData['altmetrics_sources'] = $allData;
		return json_encode($eventsData);
	}
}

?>
