<script type="text/javascript" src="{$DIR_STATIC_SKIN}/js/comments.js"></script>

			<!-- Comments -->
			<div class="comments">
				<div class="update" id="update">
					<div class="tl"></div>
					<div class="wrapper">
						<div class="refresh">
							<img class="update-comments" id="update-comments" alt="" src="{$DIR_STATIC_SKIN}/images/update.gif" onclick="lsCmtTree.responseNewComment({$oTopic->getId()},this); return false;"/>
						</div>
						<div class="new-comments" id="new-comments" style="display: none;" onclick="lsCmtTree.goNextComment();">							
						</div>
					</div>
					<div class="bl"></div>
				</div>
			
				<!-- Comments Header -->
				<div class="header">
					<h3>Комментарии (<span id="count-comments">{$oTopic->getCountComment()}</span>)</h3>
					<a href="#" class="rss">По RSS</a>
				</div>
				<!-- /Comments Header -->			
				
				{literal}
				<script>
					window.addEvent('domready', function() {
						{/literal}
						lsCmtTree.setIdCommentLast({$iMaxIdComment});
						{literal}
					});					
				</script>
				{/literal}
				
				{assign var="nesting" value="-1"}
				{foreach from=$aCommentsNew item=aComment name=rublist}
   					{if $nesting < $aComment.level}        
    				{elseif $nesting > $aComment.level}    	
        				{section name=closelist1  loop=`$nesting-$aComment.level+1`}</div></div>{/section}
    				{elseif not $smarty.foreach.rublist.first}
        				</div></div>
    				{/if}    
    				<div class="comment" id="comment_id_{$aComment.obj->getId()}">
							<img src="{$DIR_STATIC_SKIN}/images/close.gif" alt="+" title="Свернуть ветку комментариев" class="folding" />
							<a name="comment{$aComment.obj->getId()}" ></a>
							<div class="voting {if $aComment.obj->getRating()>0}positive{elseif $aComment.obj->getRating()<0}negative{/if} {if !$oUserCurrent || $aComment.obj->getUserId()==$oUserCurrent->getId()}guest{/if}   {if $aComment.obj->getUserIsVote()} voted {if $aComment.obj->getUserVoteDelta()>0}plus{else}minus{/if}{/if}  ">
								<div class="total">{if $aComment.obj->getRating()>0}+{/if}{$aComment.obj->getRating()}</div>
								<a href="#" class="plus" onclick="lsVote.vote({$aComment.obj->getId()},this,1,'topic_comment'); return false;"></a>
								<a href="#" class="minus" onclick="lsVote.vote({$aComment.obj->getId()},this,-1,'topic_comment'); return false;"></a>
							</div>						
							<div class="content {if $oUserCurrent and $aComment.obj->getUserId()==$oUserCurrent->getId()}self{/if}">
								<div class="tb"><div class="tl"><div class="tr"></div></div></div>								
								<div class="text">
									{$aComment.obj->getText()}
								</div>				
								<div class="bl"><div class="bb"><div class="br"></div></div></div>
							</div>							
							<div class="info">
								<a href="{$DIR_WEB_ROOT}/{$ROUTE_PAGE_PROFILE}/{$aComment.obj->getUserLogin()}/"><img src="{$aComment.obj->getUserProfileAvatarPath(24)}" alt="avatar" class="avatar" /></a>
								<p><a href="{$DIR_WEB_ROOT}/{$ROUTE_PAGE_PROFILE}/{$aComment.obj->getUserLogin()}/" class="author">{$aComment.obj->getUserLogin()}</a></p>
								<ul>
									<li class="date">{date_format date=$aComment.obj->getDate()}</li>
									<li><a href="javascript:lsCmtTree.toggleCommentForm({$aComment.obj->getId()});" class="reply-link">Ответить</a></li>									
									<li><a href="#comment{$aComment.obj->getId()}" class="imglink link"></a></li>									
								</ul>
							</div>							
							<div class="reply" id="reply_{$aComment.obj->getId()}" style="display: none;"></div>							
							<div class="comment-children" id="comment-children-{$aComment.obj->getId()}">    
    				{assign var="nesting" value="`$aComment.level`"}    
    				{if $smarty.foreach.rublist.last}
        				{section name=closelist2 loop=`$nesting+1`}</div></div>{/section}    
    				{/if}
				{/foreach}
				
				<span id="comment-children-0"></span>				
				<br>
				<h3 class="reply-title"><a href="javascript:lsCmtTree.toggleCommentForm(0);">Комментировать</a></h3>
				<div style="display: block;" id="reply_0" class="reply">
				
				<form action="" method="POST" id="form_comment" onsubmit="return false;" enctype="multipart/form-data">
    				<textarea name="comment_text" id="form_comment_text" style="width: 100%; height: 100px;"></textarea>    	
    				<input type="submit" name="submit_comment" value="добавить" onclick="lsCmtTree.addComment('form_comment',{$oTopic->getId()}); return false;">    	
    				<input type="hidden" name="reply" value="" id="form_comment_reply">
    				<input type="hidden" name="cmt_topic_id" value="{$oTopic->getId()}" id="cmt_topic_id">
    			</form>				
				
				</div>
			</div>
			<!-- /Comments -->