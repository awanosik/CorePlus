<div class="tags-display-widget">
	<span class="tags-display-widget-title">
		{$title}
	</span>
	<span itemprop="keywords">
		{foreach $tags as $keyword}

			<span clss="tags-display-widget-keyword">
				{if strpos($keyword.meta_value, 'u:') === 0}
					{assign var="keyworduser" value=User::Construct(substr($keyword.meta_value,2))}

					<a href="{UserSocialHelper::ResolveProfileLink($keyworduser)}">
						{img src="public/user/`$keyworduser->get('avatar')`" placeholder="person" dimensions="24x24" alt="`$keyworduser->getDisplayName()|escape`"}
						{$keyword.meta_value_title}
					</a>
				{else}
					{*<a href="#somewhere-{$keyword.meta_value}">*}
						{$keyword.meta_value_title}
					{*</a>*}
				{/if}
			</span>

		{/foreach}
	</span>
</div>