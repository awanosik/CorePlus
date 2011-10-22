{script library="tinymce"}{/script}
<div class="{$element->getClass()}">
	{if $element->get('title')}
		<label for="{$element->get('name')}">{$element->get('title')|escape}</label>
	{/if}
	
	{if $element->get('description')}
		<p class="formdescription">{$element->get('description')}</p>
	{/if}
	
	<textarea{$element->getInputAttributes()} class="tinymce">{$element->get('value')}</textarea>
</div>