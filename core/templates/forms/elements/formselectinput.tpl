<div class="{$element->getClass()} {$element->get('id')}">
	<div class="formelement-labelinputgroup">
		{if $element->get('title')}
			<label for="{$element->get('name')}">{$element->get('title')|escape}</label>
		{/if}

		<select {$element->getInputAttributes()}>
			{foreach from=$element->get('options') item=title key=key}
				<option value="{$key}" {if Core::CompareValues($key, $element->get('value'))}selected{/if}>{$title}</option>
			{/foreach}
		</select>

		<div class="clear"></div>
	</div>
	<div class="clear"></div>

	{if $element->get('description')}
		<p class="formdescription">{$element->get('description')}</p>
	{/if}
</div>

{if $element->get('readonly')}
	{* select options do not support readonly, but no reason why core plus can't ;) *}
	{script library="jqueryui.readonly"}{/script}
	{script location="foot"}<script>
		$('#{$element->get("id")}').readonly();
	</script>{/script}
{/if}