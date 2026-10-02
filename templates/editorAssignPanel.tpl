{**
 * templates/editorAssignPanel.tpl
 *
 * Copyright (c) 2017-2023 Simon Fraser University
 * Copyright (c) 2017-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Editor assignment panel — rendered in the mini-modal from the future issues grid.
 * Does NOT include isOpen checkbox or issue data fields.
 *}
<form method="post" id="issueEditorAssignForm" class="pkp_form">
    {csrf}
    <input type="hidden" name="verb"     value="issuePreselectionEditors">
    <input type="hidden" name="plugin"   value="issuepreselectionplugin">
    <input type="hidden" name="category" value="generic">
    <input type="hidden" name="issueId"  value="{$issueId|escape}">

    <div class="section">
        {if $issuePreselectionIsOpen}
            <p class="pkp_notification">
                {translate key="plugins.generic.issuePreselection.grid.isOpen"}
            </p>
        {else}
            <p class="pkp_notification">
                {translate key="plugins.generic.issuePreselection.grid.isClosed"}
            </p>
        {/if}
    </div>

    <div class="section">
    <fieldset class="border border-light m-0 p-0 min-w-0" id="issuePreselectionParticipantManager" {if !$issuePreselectionIsOpen}disabled{/if}>
        <div class="flex items-start justify-between bg-default p-5">
            <div>
                <h3 class="text-2xl-bold uppercase text-heading">
                    {translate key="plugins.generic.issuePreselection.settings.editedBy"}
                </h3>
                <p class="text-sm-normal text-secondary">{translate key="plugins.generic.issuePreselection.settings.editedByClosedHelp"}</p>
            </div>
            <details id="issuePreselectionAssign" class="relative">
                <summary id="issuePreselectionAssignBtn"
                         class="pkpButton inline-flex items-center text-lg-semibold text-primary border-light hover:text-hover bg-secondary py-[0.4375rem] px-3 border rounded cursor-pointer">
                    {translate key="common.assign"}
                </summary>
                <label for="issuePreselectionEditorSelect" class="pkp_screen_reader">
                    {translate key="plugins.generic.issuePreselection.settings.editedBy"}
                </label>
                <select id="issuePreselectionEditorSelect" class="field select mt-2">
                    <option value="">-- {translate key="common.select"} --</option>
                    {foreach from=$issuePreselectionAllEditors item=editor}
                        <option value="{$editor.id}">{$editor.fullName|escape}{if $editor.roleName} ({$editor.roleName|escape}){/if}</option>
                    {/foreach}
                </select>
            </details>
        </div>

        <ul class="flex flex-col" role="list" id="issuePreselectionParticipantsList">
            {foreach from=$issuePreselectionAllEditors item=editor name=editorLoop}
                {assign var="assigned" value=in_array($editor.id, $issuePreselectionEditors)}
                {if $assigned}{assign var="anyAssigned" value=true}{/if}
                <li class="border-t border-light p-4 text-base-normal" data-editor-id="{$editor.id}" {if !$assigned}hidden{/if}>
                    <input type="hidden" name="editedBy[]" value="{$editor.id}" {if !$assigned}disabled{/if}>
                    <div class="flex items-center justify-between">
                        <div class="flex w-full min-w-0 flex-1">
                            <div class="inline-flex h-11 w-11 items-center justify-center rounded-full shadow bg-profile-{($smarty.foreach.editorLoop.index % 6) + 1}">
                                <span class="text-on-dark text-2xl-bold">{$editor.initials|escape}</span>
                            </div>
                            <div class="ms-2 flex min-w-0 flex-1 flex-col justify-center">
                                <div class="break-words text-base-bold">{$editor.fullName|escape}</div>
                                <div class="text-sm-normal text-secondary">{$editor.roleName|escape}</div>
                            </div>
                        </div>
                        <button type="button"
                                class="issuePreselectionRemoveBtn ms-2 text-secondary p-2 rounded cursor-pointer"
                                aria-label="{translate key="common.remove"} {$editor.fullName|escape}">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                </li>
            {/foreach}
            <li id="issuePreselectionNoParticipants" class="border-t border-light p-4 text-base-normal text-secondary italic"
                {if $anyAssigned|default:false}hidden{/if}>
                {translate key="common.noneAssigned"}
            </li>
        </ul>
    </fieldset>

    {literal}
    <script type="text/javascript">
        (function() {
            const $ = id => document.getElementById(id);
            const list = $('issuePreselectionParticipantsList');
            if (!list) return;
            const toggle = (id, on) => {
                const li = list.querySelector('li[data-editor-id="' + id + '"]');
                if (!li) return;
                li.hidden = !on;
                li.querySelector('input').disabled = !on;
                $('issuePreselectionNoParticipants').hidden = !!list.querySelector('li[data-editor-id]:not([hidden])');
            };
            const sel = $('issuePreselectionEditorSelect');
            if (sel) sel.addEventListener('change', e => {
                if (e.target.value) toggle(e.target.value, true);
                e.target.value = '';
                $('issuePreselectionAssign').open = false;
            });
            list.addEventListener('click', e => {
                const li = e.target.closest('.issuePreselectionRemoveBtn')?.closest('li');
                if (li) toggle(li.dataset.editorId, false);
            });
        })();
    </script>
    {/literal}
    </div>

    {assign var="submitDisabled" value=!$issuePreselectionIsOpen}
    {fbvFormButtons submitText="common.save" hideCancel=true submitDisabled=$submitDisabled}
</form>
