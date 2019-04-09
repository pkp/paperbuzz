{**
 * plugins/generic/paperbuzz/templates/settingsForm.tpl
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Paperbuzz plugin settings
 *
 *}
 <script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#paperbuzzSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="paperbuzzSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}

	{fbvFormArea id="paperbuzzApiEmailArea"}
		{fbvFormSection label="plugins.generic.paperbuzz.settings.apiEmail" description="plugins.generic.paperbuzz.settings.apiEmail.description" inline=true size=$fbvStyles.size.MEDIUM required=true}
			{fbvElement type="text" name="apiEmail" id="apiEmail" value=$apiEmail}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="paperbuzzGraphSettingsArea"}
		{fbvFormSection label="common.options" description="plugins.generic.paperbuzz.settings.graph.description" inline=true size=$fbvStyles.size.MEDIUM required=true}
			{fbvElement type="select" id="showMini" from=$showMiniOptions selected=$showMini|default:false size=$fbvStyles.size.MEDIUM translate=false inline=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="paperbuzzDownloadsSettingsArea"}
		{fbvFormSection list="true" label="plugins.generic.paperbuzz.settings.downloads" inline=true size=$fbvStyles.size.MEDIUM required=true}
			{fbvElement type="checkbox" id="hideDownloads" label="plugins.generic.paperbuzz.settings.hideDownloads" checked=$hideDownloads|compare:true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>
