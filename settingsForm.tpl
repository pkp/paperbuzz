{**
 * plugins/generic/alm/settingsForm.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * ALM plugin settings
 *
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#almSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<div id="description">{translate key="plugins.generic.alm.description"}</div>

<div class="separator">&nbsp;</div>

<form id="almSettingsForm" class="pkp_form" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="plugin" category="generic" plugin=$pluginName verb="save"}">
{include file="common/formErrors.tpl"}

{fbvFormArea id="almSettingsFormArea"}
	{translate key="plugins.generic.alm.settings.apiKey.description"}
	{fbvFormSection title="plugins.generic.alm.settings.apiKey" for="name" inline=true size=$fbvStyles.size.MEDIUM}
		{fbvElement type="text" name="apiKey" id="apiKey" value=$apiKey}
	{/fbvFormSection}
	{fbvFormSection list=true}
		{if $depositArticles}{assign var="deposit" value="checked"}{/if}
		{fbvElement type="checkbox" id="depositArticles" value="1" checked=$deposit label="plugins.generic.alm.settings.depositArticles" }
	{/fbvFormSection}
	{fbvFormSection title="plugins.generic.alm.settings.depositUrl" for="depositUrl" inline=true size=$fbvStyles.size.MEDIUM}
		{fbvElement type="text" name="depositUrl" id="depositUrl" value=$depositUrl label="plugins.generic.alm.settings.depositUrl.description"}
	{/fbvFormSection}
{/fbvFormArea}

{translate key="plugins.generic.alm.settings.ipAddress"  ip=$smarty.server.SERVER_ADDR}

<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

{fbvFormButtons id="usageStatsSettingsFormSubmit" submitText="common.save" hideCancel=true}
</form>
