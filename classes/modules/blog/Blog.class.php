<?php
/*-------------------------------------------------------
*
*   LiveStreet Engine Social Networking
*   Copyright © 2008 Mzhelskiy Maxim
*
*--------------------------------------------------------
*
*   Official site: www.livestreet.ru
*   Contact e-mail: rus.engine@gmail.com
*
*   GNU General Public License, version 2:
*   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
---------------------------------------------------------
*/

set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__));
require_once('mapper/Blog.mapper.class.php');

/**
 * Модуль для работы с блогами
 *
 */
class LsBlog extends Module {	
	/**
	 * Возможные роли пользователя в блоге
	 */
	const BLOG_USER_ROLE_GUEST         = 0;
	const BLOG_USER_ROLE_USER          = 1;
	const BLOG_USER_ROLE_MODERATOR     = 2;
	const BLOG_USER_ROLE_ADMINISTRATOR = 4;
		
	protected $oMapperBlog;	
	protected $oUserCurrent=null;
		
	/**
	 * Инициализация
	 *
	 */
	public function Init() {				
		$this->oMapperBlog=new Mapper_Blog($this->Database_GetConnect());
		$this->oMapperBlog->SetUserCurrent($this->User_GetUserCurrent());
		$this->oUserCurrent=$this->User_GetUserCurrent();		
	}
	/**
	 * Получает дополнительные данные(объекты) для блогов по их ID
	 *
	 */
	public function GetBlogsAdditionalData($aBlogId,$aAllowData=array('vote','owner'=>array(),'relation_user')) {
		func_array_simpleflip($aAllowData);
		if (!is_array($aBlogId)) {
			$aBlogId=array($aBlogId);
		}
		/**
		 * Получаем блоги
		 */
		$aBlogs=$this->GetBlogsByArrayId($aBlogId);
		/**
		 * Формируем ID дополнительных данных, которые нужно получить
		 */
		$aUserId=array();		
		foreach ($aBlogs as $oBlog) {
			if (isset($aAllowData['owner'])) {
				$aUserId[]=$oBlog->getOwnerId();
			}						
		}
		/**
		 * Получаем дополнительные данные
		 */
		$aBlogUsers=array();
		$aBlogsVote=array();
		$aUsers=isset($aAllowData['owner']) && is_array($aAllowData['owner']) ? $this->User_GetUsersAdditionalData($aUserId,$aAllowData['owner']) : $this->User_GetUsersAdditionalData($aUserId);				
		if (isset($aAllowData['relation_user']) and $this->oUserCurrent) {
			$aBlogUsers=$this->GetBlogUsersByArrayBlog($aBlogId,$this->oUserCurrent->getId());	
		}
		if (isset($aAllowData['vote']) and $this->oUserCurrent) {			
			$aBlogsVote=$this->Vote_GetVoteByArray($aBlogId,'blog',$this->oUserCurrent->getId());
		}
		/**
		 * Добавляем данные к результату - списку блогов
		 */
		foreach ($aBlogs as $oBlog) {
			if (isset($aUsers[$oBlog->getOwnerId()])) {
				$oBlog->setOwner($aUsers[$oBlog->getOwnerId()]);
			} else {
				$oBlog->setOwner(null); // или $oBlog->setOwner(new UserEntity_User());
			}
			if (isset($aBlogUsers[$oBlog->getId()])) {
				$oBlog->setUserIsJoin(true);
				if ($aBlogUsers[$oBlog->getId()]->getIsAdministrator()) {
					$oBlog->setUserIsAdministrator(true);
				}
				if ($aBlogUsers[$oBlog->getId()]->getIsModerator()) {
					$oBlog->setUserIsModerator(true);
				}
			} else {
				$oBlog->setUserIsJoin(false);
				$oBlog->setUserIsAdministrator(false);
				$oBlog->setUserIsModerator(false);
			}				
			if (isset($aBlogsVote[$oBlog->getId()])) {
				$oBlog->setVote($aBlogsVote[$oBlog->getId()]);				
			} else {
				$oBlog->setVote(null);
			}		
		}
		
		return $aBlogs;
	}
	/**
	 * Список блогов по ID
	 *
	 * @param array $aUserId
	 */
	public function GetBlogsByArrayId($aBlogId) {
		if (!$aBlogId) {
			return array();
		}
		if (1) {
			return $this->GetBlogsByArrayIdSolid($aBlogId);
		}
		if (!is_array($aBlogId)) {
			$aBlogId=array($aBlogId);
		}
		$aBlogId=array_unique($aBlogId);
		$aBlogs=array();
		$aBlogIdNotNeedQuery=array();
		/**
		 * Делаем мульти-запрос к кешу
		 */
		$aCacheKeys=func_build_cache_keys($aBlogId,'blog_');
		if (false !== ($data = $this->Cache_Get($aCacheKeys))) {			
			/**
			 * проверяем что досталось из кеша
			 */
			foreach ($aCacheKeys as $sValue => $sKey ) {
				if (array_key_exists($sKey,$data)) {	
					if ($data[$sKey]) {
						$aBlogs[$data[$sKey]->getId()]=$data[$sKey];
					} else {
						$aBlogIdNotNeedQuery[]=$sValue;
					}
				} 
			}
		}
		/**
		 * Смотрим каких блогов не было в кеше и делаем запрос в БД
		 */		
		$aBlogIdNeedQuery=array_diff($aBlogId,array_keys($aBlogs));		
		$aBlogIdNeedQuery=array_diff($aBlogIdNeedQuery,$aBlogIdNotNeedQuery);		
		$aBlogIdNeedStore=$aBlogIdNeedQuery;
		if ($data = $this->oMapperBlog->GetBlogsByArrayId($aBlogIdNeedQuery)) {
			foreach ($data as $oBlog) {
				/**
				 * Добавляем к результату и сохраняем в кеш
				 */
				$aBlogs[$oBlog->getId()]=$oBlog;
				$this->Cache_Set($oBlog, "blog_{$oBlog->getId()}", array(), 60*60*24*4);
				$aBlogIdNeedStore=array_diff($aBlogIdNeedStore,array($oBlog->getId()));
			}
		}
		/**
		 * Сохраняем в кеш запросы не вернувшие результата
		 */
		foreach ($aBlogIdNeedStore as $sId) {
			$this->Cache_Set(null, "blog_{$sId}", array(), 60*60*24*4);
		}		
		/**
		 * Сортируем результат согласно входящему массиву
		 */
		$aBlogs=func_array_sort_by_keys($aBlogs,$aBlogId);
		return $aBlogs;		
	}
	/**
	 * Список блогов по ID, но используя единый кеш
	 *
	 * @param unknown_type $aBlogId
	 * @return unknown
	 */
	public function GetBlogsByArrayIdSolid($aBlogId) {
		if (!is_array($aBlogId)) {
			$aBlogId=array($aBlogId);
		}
		$aBlogId=array_unique($aBlogId);	
		$aBlogs=array();	
		$s=join(',',$aBlogId);
		if (false === ($data = $this->Cache_Get("blog_id_{$s}"))) {			
			$data = $this->oMapperBlog->GetBlogsByArrayId($aBlogId);
			foreach ($data as $oBlog) {
				$aBlogs[$oBlog->getId()]=$oBlog;
			}
			$this->Cache_Set($aBlogs, "blog_id_{$s}", array("blog_update"), 60*60*24*1);
			return $aBlogs;
		}		
		return $data;
	}
	/**
	 * Получить персональный блог юзера
	 *
	 * @param Entity_User $oUser
	 * @return unknown
	 */
	public function GetPersonalBlogByUserId($sUserId) {
		$id=$this->oMapperBlog->GetPersonalBlogByUserId($sUserId);
		return $this->GetBlogById($id);
	}
	/**
	 * Получить блог по айдишнику(номеру)
	 *
	 * @param unknown_type $sBlogId
	 * @return unknown
	 */
	public function GetBlogById($sBlogId) {
		$aBlogs=$this->GetBlogsAdditionalData($sBlogId);
		if (isset($aBlogs[$sBlogId])) {
			return $aBlogs[$sBlogId];
		}
		return null;		
	}
	/**
	 * Получить блог по УРЛу
	 *
	 * @param unknown_type $sBlogUrl
	 * @return unknown
	 */
	public function GetBlogByUrl($sBlogUrl) {		
		if (false === ($id = $this->Cache_Get("blog_url_{$sBlogUrl}"))) {						
			if ($id = $this->oMapperBlog->GetBlogByUrl($sBlogUrl)) {				
				$this->Cache_Set($id, "blog_url_{$sBlogUrl}", array("blog_update_{$id}"), 60*60*24*2);				
			} else {
				$this->Cache_Set(null, "blog_url_{$sBlogUrl}", array('blog_update','blog_new'), 60*60);
			}
		}		
		return $this->GetBlogById($id);		
	}
	/**
	 * Получить блог по названию
	 *
	 * @param unknown_type $sTitle
	 * @return unknown
	 */
	public function GetBlogByTitle($sTitle) {		
		if (false === ($id = $this->Cache_Get("blog_title_{$sTitle}"))) {						
			if ($id = $this->oMapperBlog->GetBlogByTitle($sTitle)) {				
				$this->Cache_Set($id, "blog_title_{$sTitle}", array("blog_update_{$id}",'blog_new'), 60*60*24*2);				
			} else {
				$this->Cache_Set(null, "blog_title_{$sTitle}", array('blog_update','blog_new'), 60*60);
			}
		}
		return $this->GetBlogById($id);		
	}
	/**
	 * Создаёт персональный блог
	 *
	 * @param Entity_User $oUser
	 * @return unknown
	 */
	public function CreatePersonalBlog(UserEntity_User $oUser) {
		$oBlog=Engine::GetEntity('Blog');
		$oBlog->setOwnerId($oUser->getId());
		$oBlog->setTitle($this->Lang_Get('blogs_personal_title').' '.$oUser->getLogin());
		$oBlog->setType('personal');
		$oBlog->setDescription($this->Lang_Get('blogs_personal_description'));
		$oBlog->setDateAdd(date("Y-m-d H:i:s")); 
		$oBlog->setLimitRatingTopic(-1000);
		$oBlog->setUrl(null);	
		$oBlog->setAvatar(0);
		$oBlog->setAvatarType(null);	
		return $this->AddBlog($oBlog);		
	}
	/**
	 * Добавляет блог
	 *
	 * @param BlogEntity_Blog $oBlog
	 * @return unknown
	 */
	public function AddBlog(BlogEntity_Blog $oBlog) {		
		if ($sId=$this->oMapperBlog->AddBlog($oBlog)) {
			$oBlog->setId($sId);
			//чистим зависимые кеши
			$this->Cache_Clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,array('blog_new',"blog_new_user_{$oBlog->getOwnerId()}"));						
			return $oBlog;
		}
		return false;
	}
	/**
	 * Обновляет блог
	 *
	 * @param BlogEntity_Blog $oBlog
	 * @return unknown
	 */
	public function UpdateBlog(BlogEntity_Blog $oBlog) {
		$oBlog->setDateEdit(date("Y-m-d H:i:s"));
		$res=$this->oMapperBlog->UpdateBlog($oBlog);		
		if ($res) {			
			//чистим зависимые кеши
			$this->Cache_Clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,array('blog_update',"blog_update_{$oBlog->getId()}"));
			$this->Cache_Delete("blog_{$oBlog->getId()}");
			return true;
		}
		return false;
	}	
	/**
	 * Добавляет отношение юзера к блогу, по сути присоединяет к блогу
	 *
	 * @param BlogEntity_BlogUser $oBlogUser
	 * @return unknown
	 */
	public function AddRelationBlogUser(BlogEntity_BlogUser $oBlogUser) {
		if ($this->oMapperBlog->AddRelationBlogUser($oBlogUser)) {		
			$this->Cache_Clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,array("blog_relation_change_{$oBlogUser->getUserId()}","blog_relation_change_blog_{$oBlogUser->getBlogId()}"));	
			$this->Cache_Delete("blog_relation_user_{$oBlogUser->getBlogId()}_{$oBlogUser->getUserId()}");	
			return true;
		}
		return false;
	}
	/**
	 * Удалет отношение юзера к блогу, по сути отключает от блога
	 *
	 * @param BlogEntity_BlogUser $oBlogUser
	 * @return unknown
	 */
	public function DeleteRelationBlogUser(BlogEntity_BlogUser $oBlogUser) {
		if ($this->oMapperBlog->DeleteRelationBlogUser($oBlogUser)) {
			$this->Cache_Clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,array("blog_relation_change_{$oBlogUser->getUserId()}","blog_relation_change_blog_{$oBlogUser->getBlogId()}"));		
			$this->Cache_Delete("blog_relation_user_{$oBlogUser->getBlogId()}_{$oBlogUser->getUserId()}");
			return true;
		}
		return false;
	}
	/**
	 * Получает список блогов по хозяину
	 *
	 * @param unknown_type $sUserId
	 * @return unknown
	 */
	public function GetBlogsByOwnerId($sUserId) {
		$data=$this->oMapperBlog->GetBlogsByOwnerId($sUserId);
		$data=$this->GetBlogsAdditionalData($data);
		return $data;
	}
	/**
	 * Получает список всех НЕ персональных блогов
	 *
	 * @return unknown
	 */
	public function GetBlogs() {
		$data=$this->oMapperBlog->GetBlogs();
		$data=$this->GetBlogsAdditionalData($data);
		return $data;
	}
		
	/**
	 * Получает список пользователей блога
	 *
	 * @param unknown_type $sBlogId
	 * @return unknown
	 */
	public function GetBlogUsersByBlogId($sBlogId,$iRole=null) {
		$aFilter=array(
			'blog_id'=> $sBlogId,			
		);
		if($iRole!==null) {
			$aFilter['user_role']=$iRole;	
		}
		$s=serialize($aFilter);
		if (false === ($data = $this->Cache_Get("blog_relation_user_by_filter_$s"))) {				
			$data = $this->oMapperBlog->GetBlogUsers($aFilter);
			$this->Cache_Set($data, "blog_relation_user_by_filter_$s", array("blog_relation_change_blog_{$sBlogId}"), 60*60*24*3);
		}
		/**
		 * Достаем дополнительные данные, для этого формируем список юзеров и делаем мульти-запрос
		 */
		if ($data) {
			$aUserId=array();
			foreach ($data as $oBlogUser) {
				$aUserId[]=$oBlogUser->getUserId();
			}
			$aUsers=$this->User_GetUsersAdditionalData($aUserId);
			$aBlogs=$this->Blog_GetBlogsAdditionalData($sBlogId);
			foreach ($data as $oBlogUser) {
				if (isset($aUsers[$oBlogUser->getUserId()])) {
					$oBlogUser->setUser($aUsers[$oBlogUser->getUserId()]);
				} else {
					$oBlogUser->setUser(null);
				}
				if (isset($aBlogs[$oBlogUser->getBlogId()])) {
					$oBlogUser->setBlog($aBlogs[$oBlogUser->getBlogId()]);
				} else {
					$oBlogUser->setBlog(null);
				}
			}
		}
		return $data;		
	}
	/**
	 * Получает отношения юзера к блогам(состоит в блоге или нет)
	 *
	 * @param unknown_type $sUserId
	 * @return unknown
	 */
	public function GetBlogUsersByUserId($sUserId,$iRole=null) {
		$aFilter=array(
			'user_id'=> $sUserId			
		);
		if($iRole!==null) {
			$aFilter['user_role']=$iRole;	
		}
		$s=serialize($aFilter);
		if (false === ($data = $this->Cache_Get("blog_relation_user_by_filter_$s"))) {				
			$data = $this->oMapperBlog->GetBlogUsers($aFilter);
			$this->Cache_Set($data, "blog_relation_user_by_filter_$s", array("blog_relation_change_{$sUserId}"), 60*60*24*3);
		}
		/**
		 * Достаем дополнительные данные, для этого формируем список блогов и делаем мульти-запрос
		 */
		if ($data) {
			$aBlogId=array();
			foreach ($data as $oBlogUser) {
				$aBlogId[]=$oBlogUser->getBlogId();
			}
			$aUsers=$this->User_GetUsersAdditionalData($sUserId);
			$aBlogs=$this->Blog_GetBlogsAdditionalData($aBlogId);
			foreach ($data as $oBlogUser) {
				if (isset($aUsers[$oBlogUser->getUserId()])) {
					$oBlogUser->setUser($aUsers[$oBlogUser->getUserId()]);
				} else {
					$oBlogUser->setUser(null);
				}
				if (isset($aBlogs[$oBlogUser->getBlogId()])) {
					$oBlogUser->setBlog($aBlogs[$oBlogUser->getBlogId()]);
				} else {
					$oBlogUser->setBlog(null);
				}
			}
		}
		return $data;
	}
	/**
	 * Состоит ли юзер в конкретном блоге
	 *
	 * @param unknown_type $sBlogId
	 * @param unknown_type $sUserId
	 * @return unknown
	 */
	public function GetBlogUserByBlogIdAndUserId($sBlogId,$sUserId) {		
		if ($aBlogUser=$this->GetBlogUsersByArrayBlog($sBlogId,$sUserId)) {
			if (isset($aBlogUser[$sBlogId])) {
				return $aBlogUser[$sBlogId];
			}
		}
		return null;
	}
	/**
	 * Получить список отношений блог-юзер по списку айдишников
	 *
	 * @param unknown_type $aTopicId
	 */
	public function GetBlogUsersByArrayBlog($aBlogId,$sUserId) {
		if (!$aBlogId) {
			return array();
		}
		if (1) {
			return $this->GetBlogUsersByArrayBlogSolid($aBlogId,$sUserId);
		}
		if (!is_array($aBlogId)) {
			$aBlogId=array($aBlogId);
		}
		$aBlogId=array_unique($aBlogId);
		$aBlogUsers=array();
		$aBlogIdNotNeedQuery=array();
		/**
		 * Делаем мульти-запрос к кешу
		 */
		$aCacheKeys=func_build_cache_keys($aBlogId,'blog_relation_user_','_'.$sUserId);
		if (false !== ($data = $this->Cache_Get($aCacheKeys))) {			
			/**
			 * проверяем что досталось из кеша
			 */
			foreach ($aCacheKeys as $sValue => $sKey ) {
				if (array_key_exists($sKey,$data)) {	
					if ($data[$sKey]) {
						$aBlogUsers[$data[$sKey]->getBlogId()]=$data[$sKey];
					} else {
						$aBlogIdNotNeedQuery[]=$sValue;
					}
				} 
			}
		}
		/**
		 * Смотрим каких блогов не было в кеше и делаем запрос в БД
		 */		
		$aBlogIdNeedQuery=array_diff($aBlogId,array_keys($aBlogUsers));		
		$aBlogIdNeedQuery=array_diff($aBlogIdNeedQuery,$aBlogIdNotNeedQuery);		
		$aBlogIdNeedStore=$aBlogIdNeedQuery;
		if ($data = $this->oMapperBlog->GetBlogUsersByArrayBlog($aBlogIdNeedQuery,$sUserId)) {
			foreach ($data as $oBlogUser) {
				/**
				 * Добавляем к результату и сохраняем в кеш
				 */
				$aBlogUsers[$oBlogUser->getBlogId()]=$oBlogUser;
				$this->Cache_Set($oBlogUser, "blog_relation_user_{$oBlogUser->getBlogId()}_{$oBlogUser->getUserId()}", array(), 60*60*24*4);
				$aBlogIdNeedStore=array_diff($aBlogIdNeedStore,array($oBlogUser->getBlogId()));
			}
		}
		/**
		 * Сохраняем в кеш запросы не вернувшие результата
		 */
		foreach ($aBlogIdNeedStore as $sId) {
			$this->Cache_Set(null, "blog_relation_user_{$sId}_{$sUserId}", array(), 60*60*24*4);
		}		
		/**
		 * Сортируем результат согласно входящему массиву
		 */
		$aBlogUsers=func_array_sort_by_keys($aBlogUsers,$aBlogId);
		return $aBlogUsers;		
	}	
	public function GetBlogUsersByArrayBlogSolid($aBlogId,$sUserId) {
		if (!is_array($aBlogId)) {
			$aBlogId=array($aBlogId);
		}
		$aBlogId=array_unique($aBlogId);	
		$aBlogUsers=array();	
		$s=join(',',$aBlogId);
		if (false === ($data = $this->Cache_Get("blog_relation_user_{$sUserId}_id_{$s}"))) {			
			$data = $this->oMapperBlog->GetBlogUsersByArrayBlog($aBlogId,$sUserId);
			foreach ($data as $oBlogUser) {
				$aBlogUsers[$oBlogUser->getBlogId()]=$oBlogUser;
			}
			$this->Cache_Set($aBlogUsers, "blog_relation_user_{$sUserId}_id_{$s}", array("blog_relation_change_{$sUserId}"), 60*60*24*1);
			return $aBlogUsers;
		}		
		return $data;
	}
	public function UpdateRelationBlogUser(BlogEntity_BlogUser $oBlogUser) {
		$this->Cache_Clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,array("blog_relation_change_{$oBlogUser->getUserId()}","blog_relation_change_blog_{$oBlogUser->getBlogId()}"));		
		$this->Cache_Delete("blog_relation_user_{$oBlogUser->getBlogId()}_{$oBlogUser->getUserId()}");
		return $this->oMapperBlog->UpdateRelationBlogUser($oBlogUser);
	}
	/**
	 * Получает список блогов по рейтингу
	 *
	 * @param unknown_type $iLimit
	 * @return unknown
	 */
	public function GetBlogsRating($iCurrPage,$iPerPage) {		
		if (false === ($data = $this->Cache_Get("blog_rating_{$iCurrPage}_{$iPerPage}"))) {				
			$data = array('collection'=>$this->oMapperBlog->GetBlogsRating($iCount,$iCurrPage,$iPerPage),'count'=>$iCount);
			$this->Cache_Set($data, "blog_rating_{$iCurrPage}_{$iPerPage}", array("blog_update","blog_new"), 60*60*24*2);
		}
		$data['collection']=$this->GetBlogsAdditionalData($data['collection'],array('owner'=>array(),'relation_user'));
		return $data;
	}
	/**
	 * Список подключенных блогов по рейтингу
	 *
	 * @param unknown_type $sUserId
	 * @param unknown_type $iLimit
	 * @return unknown
	 */
	public function GetBlogsRatingJoin($sUserId,$iLimit) { 		
		if (false === ($data = $this->Cache_Get("blog_rating_join_{$sUserId}_{$iLimit}"))) {				
			$data = $this->oMapperBlog->GetBlogsRatingJoin($sUserId,$iLimit);			
			$this->Cache_Set($data, "blog_rating_join_{$sUserId}_{$iLimit}", array('blog_update',"blog_relation_change_{$sUserId}"), 60*60*24);
		}
		return $data;		
	}
	/**
	 * Список сових блогов по рейтингу
	 *
	 * @param unknown_type $sUserId
	 * @param unknown_type $iLimit
	 * @return unknown
	 */
	public function GetBlogsRatingSelf($sUserId,$iLimit) { 		
		if (false === ($data = $this->Cache_Get("blog_rating_self_{$sUserId}_{$iLimit}"))) {				
			$data = $this->oMapperBlog->GetBlogsRatingSelf($sUserId,$iLimit);			
			$this->Cache_Set($data, "blog_rating_self_{$sUserId}_{$iLimit}", array('blog_update',"blog_new_user_{$sUserId}"), 60*60*24);
		}
		return $data;		
	}	
	/**
	 * Получает список блогов в которые может постить юзер
	 *
	 * @param unknown_type $oUser	 
	 * @return unknown
	 */
	public function GetBlogsAllowByUser($oUser) {		
		if ($oUser->isAdministrator()) {
			return $this->GetBlogs();
		} else {						
			$aAllowBlogsUser=$this->GetBlogsByOwnerId($oUser->getId());
			$aBlogUsers=$this->GetBlogUsersByUserId($oUser->getId());			
			foreach ($aBlogUsers as $oBlogUser) {
				$oBlog=$oBlogUser->getBlog();
				if ($this->ACL_CanAddTopic($oUser,$oBlog) or $oBlogUser->getIsAdministrator() or $oBlogUser->getIsModerator()) {
					$aAllowBlogsUser[$oBlog->getId()]=$oBlog;
				}
			}
			return 	$aAllowBlogsUser;
		}		
	}	
	/**
	 * Проверяет можно или нет юзеру постить в данный блог
	 *
	 * @param unknown_type $oBlog
	 * @param unknown_type $oUser
	 * @param unknown_type $sBlogIdAllow
	 */
	public function IsAllowBlog($oBlog,$oUser) {		
		if ($oUser->isAdministrator()) {
			return true;
		}
		if ($oBlog->getOwnerId()==$oUser->getId()) {
			return true;
		}
		if ($oBlogUser=$this->GetBlogUserByBlogIdAndUserId($oBlog->getId(),$oUser->getId())) {
			if ($this->ACL_CanAddTopic($oUser,$oBlog) or $oBlogUser->getIsAdministrator() or $oBlogUser->getIsModerator()) {
				return true;
			}
		}
		return false;
	}
	/**
	 * Получаем массив блогов, 
	 * которые являются открытыми для пользователя
	 *
	 * @param  UserEntity_User $oUser
	 * @return array
	 */
	public function GetOpenBlogsByUser($oUser) {
		if ($oUser->isAdministrator()) {
			return $this->GetBlogs();
		}

		/**
		 * Заносим блоги, созданные пользователем
		 */
		$aOpenBlogsUser=$this->GetBlogsByOwnerId($oUser->getId());

		/**
		 * Добавляем блоги, в которых сооит пользователь
		 * (читателем, модератором, или администратором)
		 */
		$aBlogUsers=$this->GetBlogUsersByUserId($oUser->getId());
		foreach ($aBlogUsers as $oBlogUser) {
			$oBlog=$oBlogUser->getBlog();
			if($oBlogUser->getUserRole()>self::BLOG_USER_ROLE_GUEST) {
				$aOpenBlogsUser[$oBlog->getId()]=$oBlog;
			}
		}
		return 	$aOpenBlogsUser;
	}

	/**
	 * Получаем массив идентификаторов блогов, 
	 * которые являются закрытыми для пользователя
	 *
	 * @param  UserEntity_User $oUser
	 * @return array
	 */	
	public function GetCloseBlogsByUser($oUser=null) {
		if ($oUser&&$oUser->isAdministrator()) {
			return array();
		}
		$aCloseBlogs = $this->oMapperBlog->GetCloseBlogs();
		
		if(!$oUser) {
			return $aCloseBlogs;
		}
		
		/**
		 * Получаем массив идентификаторов блогов, 
		 * которые являются откытыми для данного пользователя
		 */
		$aOpenBlogs = array();
		$aBlogUsers=$this->GetBlogUsersByUserId($oUser->getId());
		foreach ($aBlogUsers as $oBlogUser) {
			$oBlog=$oBlogUser->getBlog();
			if($oBlogUser->getUserRole()>self::BLOG_USER_ROLE_GUEST) {
				$aOpenBlogs[]=$oBlog->getId();
			}
		}
		
		return array_diff($aCloseBlogs,$aOpenBlogs);
	}
}
?>