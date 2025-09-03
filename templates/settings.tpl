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
    $(function () {
        $('#PidManagerPluginSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    });
</script>

{assign var=tokenName value=\APP\plugins\generic\openCitations\classes\Constants::token}
{assign var="references" value={translate key="submission.citations"}}
{assign var="citationsMetadataLookup" value={translate key="submission.citations.structured.citationsMetadataLookup"}}

<form class="pkp_form" method="POST" id="PidManagerPluginSettings"
      action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic"
      plugin=$pluginName verb="settings" save=true}">

	{csrf}

	{fbvFormArea id="openCitationsSettingsArea"}

        <div class="py-4">{translate
            key="plugins.generic.openCitations.settings.description"
            references=$references
            citationsMetadataLookup=$citationsMetadataLookup}
        </div>

        {fbvFormSection}
            {fbvElement
                type="text"
                password=true
                id="{\APP\plugins\generic\openCitations\classes\Constants::token}"
                value=${\APP\plugins\generic\openCitations\classes\Constants::token}
                label="plugins.generic.openCitations.settings.token.label"
                placeholder="plugins.generic.openCitations.settings.token.placeholder"
            }
        {/fbvFormSection}

	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
