{script library="jquery"}{/script}
{script library="jqueryui"}{/script}
{script src="js/updater/index.js"}{/script}
{css src="css/updater.css"}{/css}

{if $sitecount == 0}
	<p class="message-info">
		There are no update repositories available! {a href='updater/repos/add'}Add One?{/a}
	</p>
{else}
	<p>
		{if $sitecount == 1}There is {$sitecount} update repository{/if}
		{if $sitecount > 1}There are {$sitecount} update repositories{/if}
		currently available.  {a href='updater/repos'}Manage Them{/a}
	</p>

	<p>
		<span id="updates"></span>
	</p>

	{script location="foot"}<script>
		$(function(){ Updater.PerformCheck($('#updates')); });
	</script>{/script}
{/if}

<!-- This will get populated with the update progress for installs and updates. -->
<div id="update-terminal" style="display:none;"></div>

<!-- This will get cloned by javascript into the link when checking. -->
<span id="loading-replacement-text" style="display:none;">
	Checking Upgrade/Install
	{img src="assets/images/loading-bar-small.gif"}
</span>

<table class="listing" id="core-list">
	<tr type="core">
		<td>Core {$core->getVersion()}</td>
		<td>
			<a href="#" class="update-link perform-update" style="display:none;">Update</a>
		</td>
	</tr>
</table>
<br/>

<table class="listing" id="component-list">
	<tr>
		<th>Component</th>
		<th>Version</th>
		<th>Enabled</th>
		<th>&nbsp;</th>
	</tr>
	{foreach from=$components item=c}
		<tr componentname="{$c->getName()|lower}" type="components">
			<td>{$c->getName()}</td>
			<td>{$c->getVersion()}</td>
			<td>
				{if $c->isEnabled()}
					<i title="Yes" style="color:green;" class="icon-ok"></i>
				{else}
					<i title="No" style="color:red;" class="icon-remove"></i>
				{/if}
			</td>
			<td>
				{if $c->isEnabled()}
					<a href="#" class="disable-link">Disable</a>
					<a href="#" class="update-link perform-update" style="display:none;">Update</a>
				{else}
					{if $c->isInstalled()}
						<a href="#" class="enable-link">Enable</a>
					{else}
						<a href="#" class="perform-update" type="components" name="{$c->getName()}" version="{$c->getVersion()}">Install</a>
					{/if}
				{/if}
			</td>
		</tr>
	{/foreach}
</table>
<br/>

<table class="listing" id="theme-list">
	<tr>
		<th>Theme</th>
		<th>Version</th>
		<th>&nbsp;</th>
	</tr>
	{foreach from=$themes item=t}
		<tr themename="{$t->getName()|lower}" type="themes">
			<td>{$t->getName()}</td>
			<td>{$t->getVersion()}</td>
			<td>
				<a href="#" class="update-link perform-update" style="display:none;">Update</a>
			</td>
		</tr>
	{/foreach}
</table>