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

	{fbvFormSection label="plugins.generic.openCitations.settings.open_citations_title"}
		<p>
			{fbvElement
			type="text"
			id="{\APP\plugins\generic\openCitations\classes\Constants::owner}"
			value=${\APP\plugins\generic\openCitations\classes\Constants::owner}
			label="plugins.generic.openCitations.settings.open_citations_owner"
			description="plugins.generic.openCitations.settings.open_citations_owner"
			placeholder="plugins.generic.openCitations.settings.open_citations_owner"
			}
		</p>
		<p>
			{fbvElement
			type="text"
			id="{\APP\plugins\generic\openCitations\classes\Constants::repository}"
			value=${\APP\plugins\generic\openCitations\classes\Constants::repository}
			label="plugins.generic.openCitations.settings.open_citations_repository"
			description="plugins.generic.openCitations.settings.open_citations_repository"
			placeholder="plugins.generic.openCitations.settings.open_citations_repository"
			}
		</p>
		<p>
			{fbvElement
			type="text"
			password=true
			id="{\APP\plugins\generic\openCitations\classes\Constants::token}"
			value=${\APP\plugins\generic\openCitations\classes\Constants::token}
			label="plugins.generic.openCitations.settings.open_citations_token"
			description="plugins.generic.openCitations.settings.open_citations_token"
			placeholder="plugins.generic.openCitations.settings.open_citations_token"
			}
		</p>
	{/fbvFormSection}

	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
