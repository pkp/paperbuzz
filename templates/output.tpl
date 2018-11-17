{**
 * plugins/generic/alm/templates/output.tpl
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * ALM visualization, embedded in the article view page
 *
 *}

<div class="item downloads_chart">
	<h3 class="label">
		{translate key="plugins.generic.alm.visualizations"}
	</h3>
	<div id="paperbuzz"><div id="loading">{translate key="plugins.generic.alm.loading"}</div></div>
	<script type="text/javascript">
		window.onload = function () {ldelim}
			var options = {ldelim}
				paperbuzzStatsJson: JSON.parse('{$allStatsJson|escape:"javascript"}'),
				minItemsToShowGraph: {ldelim}
					minEventsForYearly: 10,
					minEventsForMonthly: 10,
					minEventsForDaily: 6,
					minYearsForYearly: 3,
					minMonthsForMonthly: 2,
					minDaysForDaily: 1 //first 30 days only
				{rdelim},
				graphheight: 150,
				graphwidth: 300,
				showTitle: false,
				showMini: {$showMini|escape:"javascript"},
				published_date: {$datePublished|escape:"javascript"},
			{rdelim}
		
			var paperbuzzviz = undefined;
			paperbuzzviz = new PaperbuzzViz(options);
			paperbuzzviz.initViz();
		{rdelim}
	</script>
	<div id="built-with"><p>Built with <a href="http://d3js.org/">d3.js</a></p></div>
</div>