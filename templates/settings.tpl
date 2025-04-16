{**
 * templates/settings.tpl
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Settings form for the openCitations plugin.
 *}

<script>
	$(function () {ldelim}
		$('#OpenCitationsPluginSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		{rdelim});
</script>

<form
	class="pkp_form"
	id="OpenCitationsPluginSettingsSettings"
	method="POST"
	action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
>
	<!-- Always add the csrf token to secure your form -->
	{csrf}

	{fbvFormArea id="openCitationsSettingsArea"}

	{fbvFormSection title="plugins.generic.openCitations.settings.description"}{/fbvFormSection}

	{fbvFormSection}
		{fbvElement
			type="text"
			password=true
			id="{\APP\plugins\generic\openCitations\classes\Constants::token}"
			value=${\APP\plugins\generic\openCitations\classes\Constants::token}
			label="plugins.generic.openCitations.settings.token"
			description="plugins.generic.openCitations.settings.token"
			placeholder="plugins.generic.openCitations.settings.token"
		}
	{/fbvFormSection}

	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
