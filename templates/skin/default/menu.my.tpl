<li {if $aParams[0]=='album'}class="active"{/if}><a href="{router page='my'}{$oUserProfile->getLogin()}/album/">{$aLang.lsgallery_albums}</a></li>