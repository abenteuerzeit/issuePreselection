{**
 * templates/issueFormFields.tpl
 *
 * Additional fields for issue form
 *}
<div class="section">
    <h3>{translate key="plugins.generic.issuePreselection.settings.isOpen"}</h3>
    <p>{translate key="plugins.generic.issuePreselection.settings.isOpenDescription"}</p>
    
    <div class="pkpFormField pkpFormField--options" style="cursor: default;">
        <label style="cursor: pointer;">
            <input type="checkbox" 
                   name="isOpen" 
                   value="1" 
                   {if $issuePreselectionIsOpen}checked="checked"{/if}
                   class="pkpFormField__input"
                   style="cursor: pointer;">
            <span class="label">{translate key="plugins.generic.issuePreselection.settings.isOpenLabel"}</span>
        </label>
    </div>
</div>

<div class="section">
    <h3>{translate key="plugins.generic.issuePreselection.settings.editedBy"}</h3>
    <p>{translate key="plugins.generic.issuePreselection.settings.editedByDescription"}</p>
    
    <div class="pkpFormField pkpFormField--select">
        <select name="editedBy[]" 
                id="editedBy" 
                multiple="multiple" 
                size="10"
                class="pkpFormField__input">
            {foreach from=$issuePreselectionEditorOptions key=editorId item=editorName}
                <option value="{$editorId}" 
                        {if in_array($editorId, $issuePreselectionEditors)}selected="selected"{/if}>
                    {$editorName|escape}
                </option>
            {/foreach}
        </select>
        <p class="description">Hold Ctrl (Windows) or Cmd (Mac) to select multiple editors.</p>
    </div>
</div>
