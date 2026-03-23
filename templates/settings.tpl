{**
 * templates/settings.tpl
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Settings form for the plugin.
 *}
<script>
    $(function () {
        $('#{$pluginName}Settings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    });
</script>

<form class="pkp_form" method="POST" id="{$pluginName}Settings"
      action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic"
      plugin=$pluginName verb="settings" save=true}">

    {csrf}

    {fbvFormArea id="{$pluginName}SettingsArea"}

    {translate
    key="plugins.generic.openCitations.settings.description"
    references="{translate key="submission.citations"}"
    citationsMetadataLookup="{translate key="submission.citations.structured.citationsMetadataLookup"}"}

    {fbvFormSection}

    {fbvElement
    type="text"
    password=true
    id="{APP\plugins\generic\openCitations\classes\Constants::token}"
    value=${APP\plugins\generic\openCitations\classes\Constants::token}
    label="plugins.generic.openCitations.settings.token.label"
    placeholder="plugins.generic.openCitations.settings.token.placeholder"}

    {/fbvFormSection}

    {/fbvFormArea}

    {fbvFormButtons submitText="common.save"}
</form>
