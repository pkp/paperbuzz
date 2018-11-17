{**
 * plugins/generic/alm/templates/settingsForm.tpl
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * ALM plugin settings
 *
 *}
 <script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#almSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="almSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}

	{fbvFormArea id="almApiEmailArea"}
		{fbvFormSection label="plugins.generic.alm.settings.apiEmail" description="plugins.generic.alm.settings.apiEmail.description" inline=true size=$fbvStyles.size.MEDIUM required=true}
			{fbvElement type="text" name="apiEmail" id="apiEmail" value=$apiEmail}
		{/fbvFormSection}
	{/fbvFormArea}
	
	{fbvFormArea id="almGraphSettingsArea"}
		{fbvFormSection label="common.options" description="plugins.generic.alm.settings.graph.description" inline=true size=$fbvStyles.size.MEDIUM required=true}
			{fbvElement type="select" id="showMini" from=$showMiniOptions selected=$showMini|default:false size=$fbvStyles.size.MEDIUM translate=false inline=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="almDownloadsSettingsArea"}
		{fbvFormSection list="true" label="plugins.generic.alm.settings.downloads" inline=true size=$fbvStyles.size.MEDIUM required=true}
			{fbvElement type="checkbox" id="hideDownloads" label="plugins.generic.alm.settings.hideDownloads" checked=$hideDownloads|compare:true}
		{/fbvFormSection}
	{/fbvFormArea}
	
	{fbvFormButtons}
</form>
