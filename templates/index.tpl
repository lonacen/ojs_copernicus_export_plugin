{extends file="layouts/backend.tpl"}

{block name="page"}
    <h1 class="app__pageHeading">
        {$pageTitle|escape}
    </h1>

    <script type="text/javascript">
        // Attach the JS file tab handler.
        $(function() {ldelim}
            $('#exportTabs').pkpHandler('$.pkp.controllers.TabHandler');
            $('#exportTabs').tabs('option', 'cache', true);
        {rdelim});
    </script>

    <div id="exportTabs">
        <ul>
            <li><a href="#exportIssues-tab">{translate key="plugins.importexport.native.exportIssues"}</a></li>
        </ul>
        <div id="exportIssues-tab">
            <script type="text/javascript">
                $(function() {ldelim}
                    // Attach the form handler.
                    $('#exportIssuesXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
                {rdelim});
            </script>
            <form id="exportIssuesXmlForm" class="pkp_form" action="{plugin_url path="exportIssues"}" method="post">
                {csrf}
                {fbvFormArea id="issuesXmlForm"}
                    {capture assign=issuesListGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.issues.ExportableIssuesListGridHandler" op="fetchGrid" escape=false}{/capture}
                    {load_url_in_div id="issuesListGridContainer" url=$issuesListGridUrl}
                    {fbvFormButtons submitText="plugins.importexport.native.exportIssues" hideCancel="true"}
                {/fbvFormArea}
            </form>
        </div>
    </div>

{/block}
